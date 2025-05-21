<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog size="normal"
		:no-close="loading"
		:name="t('libresign', 'Sign with your email.')"
		@closing="close">
		<div v-if="signMethodsStore.blurredEmail().length > 0" class="email">
			{{ signMethodsStore.blurredEmail() }}
		</div>
		<div v-if="signMethodsStore.settings.emailToken.hasConfirmCode">
			{{ t('libresign', 'Enter the code you received') }}
			<NcTextField v-model="token"
				maxlength="6"
				:disabled="loading"
				:label="t('libresign', 'Enter your code')"
				:placeholder="t('libresign', 'Enter your code')"
				:helper-text="errorMessage"
				:error="errorMessage.length > 0"
				name="code"
				type="text"
				@keyup.enter="sendCode">
				<FormTextboxPasswordIcon :size="20" />
			</NcTextField>
		</div>
		<NcTextField v-else
			v-model="sendTo"
			:disabled="loading"
			:label="t('libresign', 'Email')"
			:placeholder="t('libresign', 'Email')"
			:helper-text="errorMessage"
			:error="errorMessage.length > 0"
			@keyup.enter="requestCode"
			@input="onChangeEmail">
			<EmailIcon :size="20" />
		</NcTextField>
		<template #actions>
			<NcButton v-if="signMethodsStore.settings.emailToken.hasConfirmCode"
				:disabled="loading && !canRequestCode"
				type="submit"
				@click="requestNewCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Request new code') }}
			</NcButton>
			<NcButton v-if="!signMethodsStore.settings.emailToken.hasConfirmCode"
				:disabled="loading || !canRequestCode"
				type="submit"
				variant="primary"
				@click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Request code.') }}
			</NcButton>
			<NcButton v-if="signMethodsStore.settings.emailToken.hasConfirmCode"
				:disabled="!canSendCode"
				type="submit"
				variant="primary"
				@click="sendCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send code.') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import md5 from 'blueimp-md5'

import EmailIcon from 'vue-material-design-icons/Email.vue'
import FormTextboxPasswordIcon from 'vue-material-design-icons/FormTextboxPassword.vue'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { useSignStore } from '../../../store/sign.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import { validateEmail } from '../../../utils/validators.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalEmailManager',
	components: {
		NcDialog,
		NcTextField,
		NcLoadingIcon,
		FormTextboxPasswordIcon,
		EmailIcon,
		NcButton,
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		return { signStore, signMethodsStore }
	},
	data: () => ({
		loading: false,
		tokenLength: loadState('libresign', 'token_length', 6),
		errorMessage: '',
		token: '',
		sendTo: '',
	}),
	computed: {
		canRequestCode() {
			if (validateEmail(this.sendTo)) {
				if (md5(this.sendTo) !== this.signMethodsStore.settings.emailToken.hashOfEmail) {
					return false
				}
				return true
			}
			return false
		},
		canSendCode() {
			return this.signMethodsStore.settings.emailToken.hasConfirmCode
				&& !this.loading
				&& this.token.length === this.tokenLength
		},
	},
	watch: {
		token(token) {
			this.signMethodsStore.setEmailToken(token)
		},
	},
	methods: {
		onChangeEmail() {
			if (!validateEmail(this.sendTo) || md5(this.sendTo) !== this.signMethodsStore.settings.emailToken.hashOfEmail) {
				this.errorMessage = t('libresign', 'Invalid email')
				return
			}

			this.errorMessage = ''
		},
		requestNewCode() {
			this.signMethodsStore.setHasEmailConfirmCode(false)
			this.signMethodsStore.setEmailToken('')
		},
		async requestCode() {
			this.loading = true
			this.signMethodsStore.setHasEmailConfirmCode(false)

			await this.$nextTick()
			if (!this.canRequestCode) {
				this.onChangeEmail()
				this.loading = false
				return
			}

			try {
				if (this.signStore.document.fileId) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
							fileId: this.signStore.document.fileId,
						}),
						{
							identify: this.sendTo,
							identifyMethod: this.signMethodsStore.settings.emailToken.identifyMethod,
							signMethod: 'emailToken',
						},
					)
					showSuccess(data.ocs.data.message)
				} else {
					const signer = this.signStore.document.signers.find(row => row.me) || {}
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
							uuid: signer.sign_uuid,
						}),
						{
							identify: this.sendTo,
							identifyMethod: this.signMethodsStore.settings.emailToken.identifyMethod,
							signMethod: 'emailToken',
						},
					)
					showSuccess(data.ocs.data.message)
				}
				this.signMethodsStore.setHasEmailConfirmCode(true)
			} catch (err) {
				showError(err.response.data.ocs.data.message)
			} finally {
				this.loading = false
			}
		},
		sendCode() {
			if (!this.canSendCode) {
				return
			}
			this.$emit('change')
			this.close()
		},
		close() {
			this.$emit('close')
		},
		sanitizeNumber() {
			this.phoneNumber = sanitizeNumber(this.phoneNumber)
		},
	},
}
</script>

<style lang="scss" scoped>
.email {
	font-family: monospace;
	text-align: center;
}
</style>
