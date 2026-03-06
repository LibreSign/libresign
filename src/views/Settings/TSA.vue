<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Timestamp Authority (TSA)')"
		:description="t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.')">
		<NcCheckboxRadioSwitch type="switch"
			v-model="enabled"
			@update:model-value="toggleTsa">
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="tsa-config-container">
			<NcTextField :modelValue="tsa_url"
				:label="t('libresign', 'TSA Server URL')"
				:placeholder="t('libresign', 'Enter the timestamp server URL')"
				:disabled="loading"
				:loading="loading"
				:error="!!errors.tsa_url"
				:helper-text="getHelperText('tsa_url')"
				@update:modelValue="(value) => updateField('tsa_url', value)" />

			<NcTextField :modelValue="tsa_policy_oid"
				:label="t('libresign', 'TSA Policy OID')"
				:placeholder="t('libresign', 'Optional')"
				:disabled="loading"
				:loading="loading"
				:error="!!errors.tsa_policy_oid"
				:helper-text="getHelperText('tsa_policy_oid')"
				@update:modelValue="(value) => updateField('tsa_policy_oid', value)" />

			<NcSelect v-model="selectedAuthType"
				:options="authOptions"
				input-label="TSA Authentication"
				:disabled="loading"
				:loading="loading"
				clearable />

			<template v-if="tsa_auth_type === AUTH_TYPES.BASIC">
				<NcTextField :modelValue="tsa_username"
					:label="t('libresign', 'Username')"
					:placeholder="t('libresign', 'Username')"
					:disabled="loading"
					:loading="loading"
					:error="!!errors.tsa_username"
					:helper-text="getHelperText('tsa_username')"
					@update:modelValue="(value) => updateField('tsa_username', value)" />

				<NcPasswordField :modelValue="tsa_password"
					:label="t('libresign', 'Password')"
					:placeholder="t('libresign', 'Password')"
					:disabled="loading"
					:loading="loading"
					:error="!!errors.tsa_password"
					:helper-text="getHelperText('tsa_password')"
					@update:modelValue="(value) => updateField('tsa_password', value)" />
			</template>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { confirmPassword } from '@nextcloud/password-confirmation'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { computed, reactive, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/style.css'

defineOptions({
	name: 'TSA',
})

const AUTH_TYPES = {
	NONE: 'none',
	BASIC: 'basic',
} as const

type AuthType = typeof AUTH_TYPES[keyof typeof AUTH_TYPES]
type TsaField = 'tsa_url' | 'tsa_policy_oid' | 'tsa_username' | 'tsa_password'
type SavableField = TsaField | 'tsa_auth_type'
type AuthOption = {
	id: AuthType
	label: string
}

type ErrorMapping = Record<string, Array<{ field: TsaField, message: string }>>

const DEFAULT_TSA_URL = 'https://freetsa.org/tsr'
const DEBOUNCE_DELAY = 1000

const enabled = ref(loadState('libresign', 'tsa_url', '').length > 0)
const tsa_url = ref(loadState('libresign', 'tsa_url', ''))
const tsa_policy_oid = ref(loadState('libresign', 'tsa_policy_oid', ''))
const tsa_auth_type = ref<AuthType>(loadState('libresign', 'tsa_auth_type', AUTH_TYPES.NONE) as AuthType)
const tsa_username = ref(loadState('libresign', 'tsa_username', ''))
const tsa_password = ref(loadState('libresign', 'tsa_password', ''))
const loading = ref(false)
const errors = reactive<Record<TsaField, string>>({
	tsa_url: '',
	tsa_policy_oid: '',
	tsa_username: '',
	tsa_password: '',
})

const authOptions: AuthOption[] = [
	{ id: AUTH_TYPES.NONE, label: t('libresign', 'Without authentication') },
	{ id: AUTH_TYPES.BASIC, label: t('libresign', 'Username / Password') },
]

const selectedAuthType = computed<AuthOption>({
	get() {
		return authOptions.find(option => option.id === tsa_auth_type.value) || authOptions[0]
	},
	set(value) {
		const newAuthType = value?.id || AUTH_TYPES.NONE

		if (tsa_auth_type.value === AUTH_TYPES.NONE && newAuthType === AUTH_TYPES.NONE) {
			return
		}

		tsa_auth_type.value = newAuthType

		if (newAuthType === AUTH_TYPES.NONE) {
			tsa_username.value = ''
			tsa_password.value = ''
		}

		debouncedSaveField('tsa_auth_type')
	},
})

function updateField(field: TsaField, value: string) {
	if (field === 'tsa_url') {
		tsa_url.value = value
	} else if (field === 'tsa_policy_oid') {
		tsa_policy_oid.value = value
	} else if (field === 'tsa_username') {
		tsa_username.value = value
	} else if (field === 'tsa_password') {
		tsa_password.value = value
	}

	clearFieldError(field)
	validateField(field, value)
	debouncedSaveField(field)
}

function clearFieldError(field: TsaField) {
	errors[field] = ''
}

function clearAllErrors() {
	;(Object.keys(errors) as TsaField[]).forEach(field => {
		errors[field] = ''
	})
}

function setFieldError(field: TsaField, message: string) {
	errors[field] = message
}

function validateField(field: TsaField, value: string) {
	const validators: Partial<Record<TsaField, () => boolean>> = {
		tsa_url: () => !!value && !isValidUrl(value),
		tsa_policy_oid: () => !!value && !isValidOid(value),
	}

	if (validators[field]?.()) {
		errors[field] = getFieldHelperTexts()[field]?.error || ''
	} else {
		errors[field] = ''
	}
}

function getFieldHelperTexts() {
	return {
		tsa_url: {
			error: t('libresign', 'Invalid URL'),
			normal: t('libresign', 'Format: https://example.com/tsa'),
		},
		tsa_policy_oid: {
			error: t('libresign', 'Invalid OID format. Expected pattern: %s', '1.2.3.4.1'),
			normal: t('libresign', 'Example: 1.2.3.4.1 or leave empty for default'),
		},
		tsa_username: {
			error: t('libresign', 'Name is mandatory'),
			normal: t('libresign', 'Username'),
		},
		tsa_password: {
			error: t('libresign', 'Password is mandatory'),
			normal: t('libresign', 'Password'),
		},
	}
}

function getHelperText(field: TsaField) {
	if (errors[field] && errors[field] !== '●') {
		return errors[field]
	}

	const config = getFieldHelperTexts()[field]
	return config ? config.normal : ''
}

async function toggleTsa() {
	clearAllErrors()
	if (!enabled.value) {
		await clearTsaConfig()
		return
	}

	if (!tsa_url.value) {
		tsa_url.value = DEFAULT_TSA_URL
	}

	await saveTsaConfig()
}

async function saveTsaConfig() {
	await confirmPassword()
	loading.value = true
	clearAllErrors()

	const data = {
		tsa_url: tsa_url.value,
		tsa_policy_oid: tsa_policy_oid.value,
		tsa_auth_type: tsa_auth_type.value,
		tsa_username: tsa_username.value,
		tsa_password: tsa_password.value,
	}

	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/tsa'), data)
		clearAllErrors()
	} catch (error) {
		console.error('Error saving TSA configuration:', error)
		handleSaveError(error)
	} finally {
		loading.value = false
	}
}

async function saveField(field: SavableField) {
	if (field !== 'tsa_auth_type' && errors[field]) {
		return
	}

	await saveTsaConfig()
}

function getErrorMappings(): ErrorMapping {
	return {
		'Username and password are required for basic authentication': [
			{ field: 'tsa_username', message: t('libresign', 'Name is mandatory') },
			{ field: 'tsa_password', message: t('libresign', 'Password is mandatory') },
		],
		'Username is required': [
			{ field: 'tsa_username', message: t('libresign', 'Name is mandatory') },
		],
		'Password is required': [
			{ field: 'tsa_password', message: t('libresign', 'Password is mandatory') },
		],
		'Invalid URL format': [
			{ field: 'tsa_url', message: t('libresign', 'Invalid URL') },
		],
		'Invalid OID format': [
			{ field: 'tsa_policy_oid', message: t('libresign', 'Invalid OID format. Expected pattern: %s', '1.2.3.4.1') },
		],
	}
}

function handleSaveError(error: any) {
	if (error?.response?.status === 400) {
		const message = error.response?.data?.ocs?.data?.message || ''
		const errorMappings = getErrorMappings()
		const mappingKey = errorMappings[message]
			? message
			: Object.keys(errorMappings).find(key => message.includes(key))

		if (mappingKey) {
			for (const { field, message: errorMessage } of errorMappings[mappingKey]) {
				setFieldError(field, errorMessage)
			}
		} else {
			setFieldError('tsa_url', message)
		}
		return
	}

	setFieldError('tsa_url', '●')
}

async function clearTsaConfig() {
	await confirmPassword()
	loading.value = true
	clearAllErrors()

	try {
		await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/tsa'))
		tsa_url.value = ''
		tsa_policy_oid.value = ''
		tsa_auth_type.value = AUTH_TYPES.NONE
		tsa_username.value = ''
		tsa_password.value = ''
		clearAllErrors()
	} catch (error) {
		console.error('Error clearing TSA configuration:', error)
		setFieldError('tsa_url', '●')
	} finally {
		loading.value = false
	}
}

function isValidUrl(value: string) {
	try {
		const url = new URL(value)
		return url.protocol === 'http:' || url.protocol === 'https:'
	} catch {
		return false
	}
}

function isValidOid(oid: string) {
	const oidRegex = /^[0-9]+(\.[0-9]+)*$/
	return oidRegex.test(oid.trim())
}

function debounce<TArgs extends unknown[]>(func: (...args: TArgs) => void | Promise<void>, wait: number) {
	let timeout: ReturnType<typeof setTimeout> | undefined

	return (...args: TArgs) => {
		if (timeout) {
			clearTimeout(timeout)
		}

		timeout = setTimeout(() => {
			void func(...args)
		}, wait)
	}
}

const debouncedSaveField = debounce((field: SavableField) => saveField(field), DEBOUNCE_DELAY)

defineExpose({
	t,
	AUTH_TYPES,
	DEFAULT_TSA_URL,
	DEBOUNCE_DELAY,
	enabled,
	tsa_url,
	tsa_policy_oid,
	tsa_auth_type,
	tsa_username,
	tsa_password,
	loading,
	errors,
	authOptions,
	selectedAuthType,
	updateField,
	clearFieldError,
	clearAllErrors,
	setFieldError,
	validateField,
	getFieldHelperTexts,
	getHelperText,
	toggleTsa,
	saveTsaConfig,
	saveField,
	getErrorMappings,
	handleSaveError,
	clearTsaConfig,
	isValidUrl,
	isValidOid,
	debouncedSaveField,
})
</script>

<style lang="scss" scoped>
.tsa-config-container {
	margin-top: 16px;
}
</style>
