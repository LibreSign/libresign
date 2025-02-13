<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="signMethodsStore.modal.sms"
		:name="t('libresign', 'Sign with your cellphone number.')"
		@closing="signMethodsStore.closeModal('sms')">
		<div v-if="newPhoneNumber" class="code-request">
			<h3 class="phone">
				{{ newPhoneNumber }}
			</h3>

			<NcTextField v-if="tokenRequested"
				v-model="token"
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
			<NcButton v-if="newPhoneNumber && !tokenRequested" :disabled="loading" @click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Request code.') }}
			</NcButton>
			<NcButton v-if="newPhoneNumber && tokenRequested" :disabled="loading" @click="sendCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send code.') }}
			</NcButton>
			<NcButton v-if="!newPhoneNumber" :disabled="loading || newPhoneNumber.length < 10" @click="saveNumber">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Save your number.') }}
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

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalSMSManager',
	components: {
		NcDialog,
		NcLoadingIcon,
		NcTextField,
		NcButton,
	},
	props: {
		phoneNumber: {
			type: String,
			required: true,
		},
	},
	setup() {
		const signStore = useSignStore()
		return { signStore }
	},
	data: () => ({
		token: '',
		newPhoneNumber: this.phoneNumber,
		tokenRequested: false,
		loading: false,
	}),
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
				if (this.signStore.document.fileId) {
					const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
						fileId: this.signStore.document.fileId,
					}))
					showSuccess(data.ocs.data.message)
				} else {
					const signer = this.signStore.document.signers.find(row => row.me) || {}
					const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{fileId}/code', {
						uuid: signer.sign_uuid,
					}))
					showSuccess(data.ocs.data.message)
				}
				this.tokenRequested = true
			} catch (err) {
				showError(err.response.data.ocs.data.message)
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
