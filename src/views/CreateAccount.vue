<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="wrapper">
		<header>
			<div class="logo" />
		</header>
		<div class="create-account">
			<h2 class="create-account__headline">
				{{ t('libresign', 'Create account') }}
			</h2>
			<NcNoteCard type="info">
				{{ t('libresign', 'You need to create an account with the same email address you received the invitation from.') }}
			</NcNoteCard>
			<NcNoteCard v-if="errorMessage" type="error">
				{{ errorMessage }}
			</NcNoteCard>
			<fieldset class="create-account__fieldset">
				<NcTextField v-model="email"
					:label="t('libresign', 'Email')"
					autocapitalize="none"
					:spellchecking="false"
					autocomplete="off"
					:disabled="loading"
					:helper-text="emailError"
					:error="showErrorEmail"
					required>
					<NcIconSvgWrapper :path="mdiEmail" :size="20" />
				</NcTextField>
				<NcPasswordField v-model="password"
					:label="t('libresign', 'Password')"
					:spellchecking="false"
					autocapitalize="none"
					autocomplete="off"
					:disabled="loading"
					:helper-text="passwordError"
					:error="passwordError.length > 0"
					required />
				<NcPasswordField v-model="passwordConfirm"
					:label="t('libresign', 'Confirm password')"
					:spellchecking="false"
					autocapitalize="none"
					autocomplete="off"
					:disabled="loading"
					:helper-text="confirmPasswordError"
					:error="confirmPasswordError.length > 0"
					required />
				<NcButton :wide="true"
					variant="primary"
					:disabled="!canSave"
					@click="createAccount">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiChevronRight" :size="20" />
					</template>
					{{ t('libresign', 'Next') }}
				</NcButton>
			</fieldset>
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, getCurrentInstance, onBeforeMount, reactive, ref, toRefs } from 'vue'

// eslint-disable-next-line n/no-missing-import
import md5 from 'crypto-js/md5'


import axios from '@nextcloud/axios'
import { showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiEmail,
	mdiChevronRight,
} from '@mdi/js'

defineOptions({
	name: 'CreateAccount',
})

const instance = getCurrentInstance()
const route = computed(() => instance?.proxy?.$route ?? { params: {} })
const router = computed(() => instance?.proxy?.$router)

const state = reactive({
	loading: false,
	email: '',
	password: '',
	passwordConfirm: '',
	settings: loadState('libresign', 'settings'),
	message: loadState('libresign', 'message'),
	errorMessage: '',
	enabledFeatures: [] as unknown[],
})

const {
	loading,
	email,
	password,
	passwordConfirm,
	errorMessage,
} = toRefs(state)

const emailTouched = ref(false)
const passwordTouched = ref(false)
const passwordConfirmTouched = ref(false)

const isRequired = (value: string) => value.length > 0
const isEmailValid = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
const hasMinLength = (value: string, length: number) => value.length >= length

function createValidationField(source: typeof email, touched: typeof emailTouched, validators: Record<string, () => boolean>) {
	const requiredValidator = validators.required ?? (() => true)
	const emailValidator = validators.email ?? (() => true)
	const minLengthValidator = validators.minLength ?? (() => true)

	return {
		get $model() {
			return source.value
		},
		set $model(value: string) {
			source.value = value
		},
		async $touch() {
			touched.value = true
		},
		get $error() {
			return touched.value && [requiredValidator, emailValidator, minLengthValidator].some((validator) => !validator())
		},
		get required() {
			return {
				get $invalid() {
					return !requiredValidator()
				},
			}
		},
		get email() {
			return {
				get $invalid() {
					return !emailValidator()
				},
			}
		},
		get minLength() {
			return {
				get $invalid() {
					return !minLengthValidator()
				},
			}
		},
	}
}

const v$ = {
	email: createValidationField(email, emailTouched, {
		required: () => isRequired(email.value),
		email: () => isEmailValid(email.value),
	}),
	password: createValidationField(password, passwordTouched, {
		required: () => isRequired(password.value),
		minLength: () => hasMinLength(password.value, 4),
	}),
	passwordConfirm: createValidationField(passwordConfirm, passwordConfirmTouched, {
		required: () => isRequired(passwordConfirm.value),
		minLength: () => hasMinLength(passwordConfirm.value, 4),
	}),
}

const emailError = computed(() => {
	if (email.value) {
		if (v$.email.$error) {
			return t('libresign', 'This is not a valid email')
		}
		if (!isEqualEmail.value) {
			return t('libresign', 'The email entered is not the same as the email in the invitation')
		}
	}
	return ''
})

const showErrorEmail = computed(() => emailError.value.length > 2)

const passwordError = computed(() => {
	if (state.password && state.passwordConfirm && state.password.length <= 4) {
		return t('libresign', 'Your password must be greater than 4 digits')
	}
	return ''
})

const confirmPasswordError = computed(() => {
	if (state.password && state.passwordConfirm && state.password !== state.passwordConfirm) {
		return t('libresign', 'Passwords does not match')
	}
	return ''
})

const isEqualEmail = computed(() => state.settings.accountHash === md5(email.value).toString())

const canSave = computed(() => {
	return state.password.length > 0
		&& state.passwordConfirm.length > 0
		&& passwordError.value.length === 0
		&& confirmPasswordError.value.length === 0
		&& state.email.length > 0
		&& !showErrorEmail.value
		&& !state.loading
})

onBeforeMount(() => {
	if (state.message) {
		showWarning(state.message)
	}
})

async function createAccount() {
	state.loading = true
	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/create/{uuid}'), {
			uuid: route.value.params.uuid,
			email: state.email,
			password: state.password,
		})
		const url = router.value.resolve({ name: 'SignPDF' })
		window.location.href = url.href
	} catch (error: any) {
		state.errorMessage = error.response.data.ocs.data.message
	}
	state.loading = false
}

defineExpose({
	v$,
	email,
	password,
	passwordConfirm,
	loading,
	errorMessage,
	settings: state.settings,
	emailError,
	showErrorEmail,
	passwordError,
	confirmPasswordError,
	canSave,
	isEqualEmail,
	createAccount,
})
</script>

<style lang="scss">
body {
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	.sign-external-page {
		width: unset !important;
		max-width: 700px !important;
		.app-content {
			background-color: unset !important;
			overflow: unset !important;
		}
	}
}
</style>
<style lang="scss" scoped>
.wrapper {
	max-width: 700px;
	margin-block: 10vh auto;

	header {
		text-align: center;
		.logo {
			height: 120px;
			background-image: var(--image-logo, url('../../img/logo-white.svg'));
			background-repeat: no-repeat;
			background-position: center;
			background-size: contain;
			position: relative;
			margin-bottom: 10px;
			width: 175px;
			display: inline-flex;
		}
	}
	.create-account & {
		--color-text-maxcontrast: var(--color-text-maxcontrast-background-blur, var(--color-main-text));
		color: var(--color-main-text);
		background-color: var(--color-main-background-blur);
		padding: 16px;
		border-radius: var(--border-radius-rounded);
		box-shadow: 0 0 10px var(--color-box-shadow);
		display: inline-block;
		backdrop-filter: var(--filter-background-blur);
		width: 320px;
		box-sizing: border-box;
	}
	.create-account {
		h2 {
			font-size: 20px;
			font-weight: bold;
		}
		&__headline{
			text-align: center;
			overflow-wrap: anywhere;
		}
		&__fieldset{
			width: 100%;
			display: flex;
			flex-direction: column;
			gap: .5rem;
		}
		.button-vue{
			margin-top: 0.5rem;
		}
	}
}
</style>
