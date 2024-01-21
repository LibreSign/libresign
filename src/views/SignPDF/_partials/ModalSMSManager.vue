<template>
	<NcModal size="normal" @close="close">
		<NcContent class="modal-view">
			<template slot="header">
				<h2>{{ t('libresign', 'Sign with your cellphone number.') }}</h2>
				<!-- <p>{{ t('libresign', 'Sign the document.') }}</p> -->
			</template>

			<div v-if="newPhoneNumber" class="code-request">
				<h3 class="phone">
					{{ newPhoneNumber }}
				</h3>

				<div v-if="tokenRequested">
					<input v-model="token"
						:disabled="loading"
						name="code"
						type="text">
				</div>

				<div>
					<button v-if="!tokenRequested" :disabled="loading" @click="requestCode">
						{{ t('libresign', 'Request code.') }}
					</button>

					<button v-if="tokenRequested" :disabled="loading" @click="sendCode">
						{{ t('libresign', 'Send code.') }}
					</button>
				</div>
			</div>
			<div v-else class="store-number">
				<div>
					<input v-model="newPhoneNumber"
						:disabled="loading"
						name="cellphone"
						placeholder="+55 00 0 0000 0000"
						type="tel"
						@change="sanitizeNumber">
				</div>

				<div>
					<button :disabled="loading || newPhoneNumber.length < 10" @click="saveNumber">
						{{ t('libresign', 'Save your number.') }}
					</button>
				</div>
			</div>
		</NcContent>
	</NcModal>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { confirmPassword } from '@nextcloud/password-confirmation'
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
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
		NcModal,
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
				if (this.uuid.length > 0) {
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

button {
	width: 60%;
	max-width: 200px;
	margin-top: 5px;
	margin-left: auto;
	margin-right: auto;
	display: block;

}

.code-request, .store-number {
	width: 100%;
	display: flex;
	flex-direction: column;
	input {
		font-family: monospace;
		font-size: 1.1em;
		width: 50%;
		max-width: 250px;
		height: auto !important;
		display: block;
		margin: 0 auto;
	}
}

.code-request input {
	font-size: 1.3em;
}
</style>
