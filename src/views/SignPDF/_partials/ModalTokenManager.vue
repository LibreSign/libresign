<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="signMethodsStore.modal.sms"
		:name="t('libresign', 'Sign with your phone number.')"
		@closing="signMethodsStore.closeModal('sms')">
		<div v-if="tokenRequested" class="code-request">
			<h3 class="phone">
				{{ newPhoneNumber }}
			</h3>

			<NcTextField v-model="token"
				:disabled="loading"
				name="code"
				type="text" />
		</div>
		<div v-else class="store-number">
			<NcTextField v-model="newPhoneNumber"
				:disabled="loading"
				name="cellphone"
				placeholder="+55 00 0 0000 0000"
				type="tel"
				@change="sanitizeNumber" />
		</div>
		<template #actions>
			<NcButton v-if="!tokenRequested" :disabled="loading || newPhoneNumber.length < 10" @click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Request code.') }}
			</NcButton>
			<NcButton v-else :disabled="loading || token.length < 3" @click="sendCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send code.') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { settingsService } from '../../../domains/settings/index.js'
import { useSignStore } from '../../../store/sign.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalTokenManager',
	components: {
		NcDialog,
		NcLoadingIcon,
		NcTextField,
		NcButton,
	},
	props: {
		phoneNumber: {
			type: String,
			required: false,
			default: '',
		},
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		return { signStore, signMethodsStore }
	},
	data() {
		return {
			token: '',
			newPhoneNumber: this.phoneNumber || '',
			tokenRequested: false,
			loading: false,
		}
	},
	computed: {
		activeTokenMethod() {
			const tokenMethods = ['sms', 'whatsapp', 'signal', 'telegram', 'xmpp']
			return tokenMethods.find(method =>
				Object.hasOwn(this.signMethodsStore.settings, method)
			) || 'sms'
		},
		activeIdentifyMethod() {
			return this.activeTokenMethod
		},
	},
	methods: {
		async saveNumber() {
			this.loading = true
			this.sanitizeNumber()

			await this.$nextTick()

			try {
				await confirmPassword()
				const { data: { phone }, success } = await settingsService.saveUserNumber(this.newPhoneNumber)

				this.newPhoneNumber = phone
				this.$emit('update:phone', phone)

				if (!success) {
					showError(t('libresign', 'Review the entered number.'))
					return
				}
				showSuccess(t('libresign', 'Phone stored.'))
			} catch (err) {
				showError(err.response.data.ocs.data.message)
			} finally {
				this.loading = false
			}
		},
		async requestCode() {
			this.loading = true
			this.tokenRequested = false

			await this.$nextTick()

			try {
				const params = {
					identifyMethod: this.activeIdentifyMethod,
					signMethod: this.activeTokenMethod,
				}

				if (this.signStore.document.fileId) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
							fileId: this.signStore.document.fileId,
						}),
						params
					)
					showSuccess(data.ocs.data.message)
				} else {
				const signer = this.signStore.document.signers.find(row => row.me) || {}
				const { data } = await axios.post(
					generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
						uuid: signer.sign_uuid,
					}),
					params
				)
					showSuccess(data.ocs.data.message)
				}
			this.tokenRequested = true
		} catch (err) {
			const errorMessage = err.response?.data?.ocs?.data?.message || err.response?.data?.message || err.message

			if (errorMessage && errorMessage.includes('Invalid configuration')) {
				const method = this.activeTokenMethod.charAt(0).toUpperCase() + this.activeTokenMethod.slice(1)
				showError(t('libresign', '{method} is not configured. Please contact your administrator.', { method }))
			} else {
				showError(errorMessage)
			}
		} finally {
				this.loading = false
			}
		},
		sendCode() {
			this.$emit('change', this.token)

			this.$nextTick(() => {
				this.close()
			})
		},
		close() {
			this.$emit('close')
		},
		sanitizeNumber() {
			this.newPhoneNumber = sanitizeNumber(this.newPhoneNumber)
		},
	},
}
</script>

<style lang="scss" scoped>
h3.phone {
	font-family: monospace;
	text-align: center;
}

.code-request, .store-number {
	width: 100%;
	display: flex;
	flex-direction: column;
}

.code-request input {
	font-size: 1.3em;
}
</style>
