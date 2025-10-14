<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="enabled"
			@update:checked="toggleTsa">
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="tsa-config-container">
			<NcTextField :value="tsa_url"
				:label="t('libresign', 'TSA Server URL')"
				:placeholder="t('libresign', 'Enter the timestamp server URL')"
				:disabled="loading"
				:loading="loading"
				:error="!!errors.tsa_url"
				:helper-text="getHelperText('tsa_url')"
				@update:value="(value) => updateField('tsa_url', value)" />

			<NcTextField :value="tsa_policy_oid"
				:label="t('libresign', 'TSA Policy OID')"
				:placeholder="t('libresign', 'Optional')"
				:disabled="loading"
				:loading="loading"
				:error="!!errors.tsa_policy_oid"
				:helper-text="getHelperText('tsa_policy_oid')"
				@update:value="(value) => updateField('tsa_policy_oid', value)" />

			<NcSelect v-model="selectedAuthType"
				:options="authOptions"
				input-label="TSA Authentication"
				:disabled="loading"
				:loading="loading"
				clearable />

			<template v-if="tsa_auth_type === AUTH_TYPES.BASIC">
				<NcTextField :value="tsa_username"
					:label="t('libresign', 'Username')"
					:placeholder="t('libresign', 'Username')"
					:disabled="loading"
					:loading="loading"
					:error="!!errors.tsa_username"
					:helper-text="getHelperText('tsa_username')"
					@update:value="(value) => updateField('tsa_username', value)" />

				<NcPasswordField :value="tsa_password"
					:label="t('libresign', 'Password')"
					:placeholder="t('libresign', 'Password')"
					:disabled="loading"
					:loading="loading"
					:error="!!errors.tsa_password"
					:helper-text="getHelperText('tsa_password')"
					@update:value="(value) => updateField('tsa_password', value)" />
			</template>
		</div>
	</NcSettingsSection>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'TSA',
	components: {
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcSelect,
		NcSettingsSection,
		NcTextField,
	},

	data() {
		const AUTH_TYPES = {
			NONE: 'none',
			BASIC: 'basic'
		}

		const DEFAULT_TSA_URL = 'https://freetsa.org/tsr'
		const DEBOUNCE_DELAY = 1000

		return {
			AUTH_TYPES,
			DEFAULT_TSA_URL,
			DEBOUNCE_DELAY,
			name: t('libresign', 'Timestamp Authority (TSA)'),
			description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
			enabled: loadState('libresign', 'tsa_url', '').length > 0,
			tsa_url: loadState('libresign', 'tsa_url', ''),
			tsa_policy_oid: loadState('libresign', 'tsa_policy_oid', ''),
			tsa_auth_type: loadState('libresign', 'tsa_auth_type', AUTH_TYPES.NONE),
			tsa_username: loadState('libresign', 'tsa_username', ''),
			tsa_password: loadState('libresign', 'tsa_password', ''),
			loading: false,
			errors: {
				tsa_url: '',
				tsa_policy_oid: '',
				tsa_username: '',
				tsa_password: '',
			},
			authOptions: [
				{
					id: AUTH_TYPES.NONE,
					label: t('libresign', 'Without authentication'),
				},
				{
					id: AUTH_TYPES.BASIC,
					label: t('libresign', 'Username / Password'),
				},
			],
		}
	},

	computed: {
		selectedAuthType: {
			get() {
				return this.authOptions.find(option => option.id === this.tsa_auth_type) || this.authOptions[0]
			},
			set(value) {
				const newAuthType = value?.id || this.AUTH_TYPES.NONE

				if (this.tsa_auth_type === this.AUTH_TYPES.NONE && newAuthType === this.AUTH_TYPES.NONE) {
					return
				}

				this.tsa_auth_type = newAuthType

				if (newAuthType === this.AUTH_TYPES.NONE) {
					this.tsa_username = ''
					this.tsa_password = ''
				}

				this.debouncedSaveField('tsa_auth_type')
			}
		}
	},

	methods: {
		updateField(field, value) {
			this[field] = value
			this.clearFieldError(field)
			this.validateField(field, value)
			this.debouncedSaveField(field)
		},

		clearFieldError(field) {
			this.errors[field] = ''
		},

		clearAllErrors() {
			Object.keys(this.errors).forEach(field => {
				this.errors[field] = ''
			})
		},

		setFieldError(field, message) {
			this.errors[field] = message
		},

		validateField(field, value) {
			const validators = {
				tsa_url: () => !!value && !this.isValidUrl(value),
				tsa_policy_oid: () => !!value && !this.isValidOid(value)
			}

			if (validators[field] && validators[field]()) {
				this.errors[field] = this.getFieldHelperTexts()[field]?.error || ''
			} else {
				this.errors[field] = ''
			}
		},

		getFieldHelperTexts() {
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
				}
			}
		},

		getHelperText(field) {
			if (this.errors[field] && this.errors[field] !== '●') {
				return this.errors[field]
			}

			const config = this.getFieldHelperTexts()[field]
			return config ? config.normal : ''
		},

		async toggleTsa() {
			this.clearAllErrors()
			if (!this.enabled) {
				await this.clearTsaConfig()
			} else {
				if (!this.tsa_url) {
					this.tsa_url = this.DEFAULT_TSA_URL
				}
				await this.saveTsaConfig()
			}
		},

		async saveTsaConfig() {
			await confirmPassword()
			this.loading = true
			this.clearAllErrors()

			const data = {
				tsa_url: this.tsa_url,
				tsa_policy_oid: this.tsa_policy_oid,
				tsa_auth_type: this.tsa_auth_type,
				tsa_username: this.tsa_username,
				tsa_password: this.tsa_password,
			}

			axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/tsa'), data)
				.then(() => {
					this.clearAllErrors()
				})
				.catch(error => {
					console.error(`Error saving TSA configuration:`, error)
					this.handleSaveError(error)
				})
				.finally(() => {
					this.loading = false
				})
		},

		async saveField(field) {
			if (this.errors[field]) {
				return
			}

			await this.saveTsaConfig()
		},

		getErrorMappings() {
			return {
				'Username and password are required for basic authentication': [
					{ field: 'tsa_username', message: t('libresign', 'Name is mandatory') },
					{ field: 'tsa_password', message: t('libresign', 'Password is mandatory') }
				],
				'Username is required': [
					{ field: 'tsa_username', message: t('libresign', 'Name is mandatory') }
				],
				'Password is required': [
					{ field: 'tsa_password', message: t('libresign', 'Password is mandatory') }
				],
				'Invalid URL format': [
					{ field: 'tsa_url', message: t('libresign', 'Invalid URL') }
				],
				'Invalid OID format': [
					{ field: 'tsa_policy_oid', message: t('libresign', 'Invalid OID format. Expected pattern: %s', '1.2.3.4.1') }
				]
			}
		},

		handleSaveError(error) {
			if (error.response?.status === 400) {
				const message = error.response?.data?.ocs?.data?.message || ''
				const errorMappings = this.getErrorMappings()

				const mapping = errorMappings[message] ||
					Object.keys(errorMappings).find(key => message.includes(key))

				if (mapping) {
					const errors = errorMappings[mapping] || errorMappings[message]
					errors.forEach(({ field, message: errorMessage }) => {
						this.setFieldError(field, errorMessage)
					})
				} else {
					this.setFieldError('tsa_url', message)
				}
			} else {
				this.setFieldError('tsa_url', '●')
			}
		},

		async clearTsaConfig() {
			await confirmPassword()
			this.loading = true
			this.clearAllErrors()

			axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/tsa'))
				.then(() => {
					this.tsa_url = ''
					this.tsa_policy_oid = ''
					this.tsa_auth_type = this.AUTH_TYPES.NONE
					this.tsa_username = ''
					this.tsa_password = ''
					this.clearAllErrors()
				})
				.catch(error => {
					console.error('Error clearing TSA configuration:', error)
					this.setFieldError('tsa_url', '●')
				})
				.finally(() => {
					this.loading = false
				})
		},

		isValidUrl(string) {
			try {
				const url = new URL(string)
				return url.protocol === 'http:' || url.protocol === 'https:'
			} catch (_) {
				return false
			}
		},

		isValidOid(oid) {
			const oidRegex = /^[0-9]+(\.[0-9]+)*$/
			return oidRegex.test(oid.trim())
		},
	},

	created() {
		const debounce = (func, wait) => {
			let timeout
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout)
					func.apply(this, args)
				}
				clearTimeout(timeout)
				timeout = setTimeout(later, wait)
			}
		}

		this.debouncedSaveField = debounce((field) => this.saveField(field), this.DEBOUNCE_DELAY)
	},

}
</script>

<style lang="scss" scoped>
.tsa-config-container {
	margin-top: 16px;
}
</style>
