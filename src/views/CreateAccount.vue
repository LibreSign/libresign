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
					<EmailIcon :size="20" />
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
						<RightIcon v-else :size="20" />
					</template>
					{{ t('libresign', 'Next') }}
				</NcButton>
			</fieldset>
		</div>
	</div>
</template>

<script>
// eslint-disable-next-line n/no-missing-import
import md5 from 'crypto-js/md5'
// eslint-disable-next-line n/no-missing-import
import { required, email, minLength } from 'vuelidate/lib/validators'

import RightIcon from 'vue-material-design-icons/ArrowRight.vue'
import EmailIcon from 'vue-material-design-icons/Email.vue'

import axios from '@nextcloud/axios'
import { showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'CreateAccount',
	components: {
		NcNoteCard,
		NcTextField,
		EmailIcon,
		NcPasswordField,
		NcButton,
		NcLoadingIcon,
		RightIcon,
	},

	data() {
		return {
			loading: false,
			email: '',
			password: '',
			passwordConfirm: '',
			settings: loadState('libresign', 'settings'),
			message: loadState('libresign', 'message'),
			errorMessage: '',
			enabledFeatures: [],
		}
	},

	validations: {
		email: { required, email },
		password: { required, minLength: minLength(4) },
		passwordConfirm: { required, minLength: minLength(4) },
	},

	computed: {
		emailError() {
			if (this.$v.email.$model) {
				if (this.$v.email.$error) {
					return t('libresign', 'This is not a valid email')
				} else if (this.isEqualEmail === false) {
					return t('libresign', 'The email entered is not the same as the email in the invitation')
				}
			}
			return ''
		},
		showErrorEmail() {
			return this.emailError.length > 2
		},
		passwordError() {
			if (this.password && this.passwordConfirm) {
				if (this.password.length <= 4) {
					return t('libresign', 'Your password must be greater than 4 digits')
				}
			}
			return ''
		},
		confirmPasswordError() {
			if (this.password && this.passwordConfirm) {
				if (this.password !== this.passwordConfirm) {
					return t('libresign', 'Passwords does not match')
				}
			}
			return ''
		},
		canSave() {
			return this.password.length > 0
				&& this.passwordConfirm.length > 0
				&& this.passwordError.length === 0
				&& this.confirmPasswordError.length === 0
				&& this.email.length > 0
				&& !this.showErrorEmail
				&& !this.loading
		},
		isEqualEmail() {
			return this.settings.accountHash === md5(this.email).toString()
		},
	},

	created() {
		showWarning(t('libresign', this.message))
	},

	methods: {
		async createAccount() {
			this.loading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/create/{uuid}'), {
				uuid: this.$route.params.uuid,
				email: this.email,
				password: this.password,
			})
				.then(() => {
					const url = this.$router.resolve({ name: 'SignPDF' })
					window.location.href = url.href
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data.message
				})
			this.loading = false
		},
	},
}
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
