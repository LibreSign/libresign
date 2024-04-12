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
				type="text">
			</NcTextField>
		</div>
		<div v-else class="store-number">
			<NcTextField v-model="newPhoneNumber"
				:disabled="loading"
				name="cellphone"
				placeholder="+55 00 0 0000 0000"
				type="tel"
				@change="sanitizeNumber">
			</NcTextField>
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
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { onError } from '../../../helpers/errors.js'
import { settingsService } from '../../../domains/settings/index.js'

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
		fileId: {
			type: Number,
			required: false,
			default: 0,
		},
		uuid: {
			type: String,
			required: false,
			default: '',
		},
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
				onError(err)
			} finally {
				this.loading = false
			}
		},
		async requestCode() {
			this.loading = true
			this.tokenRequested = false

			await this.$nextTick()

			try {
				if (this.fileId.length > 0) {
					const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
						fileId: this.fileId,
					}))
					showSuccess(data.message)
				} else {
					const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{fileId}/code', {
						uuid: this.uuid,
					}))
					showSuccess(data.message)
				}
				this.tokenRequested = true
			} catch (err) {
				onError(err)
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
