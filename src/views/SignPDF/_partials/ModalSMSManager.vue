<script>
import { confirmPassword } from '@nextcloud/password-confirmation'
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { isEmpty, castArray } from 'lodash-es'
import Content from '../../../Components/Modals/ModalContent.vue'
import { onError } from '../../../helpers/errors.js'
import { settingsService } from '../../../domains/settings/index.js'
import { service as signService } from '../../../domains/sign/index.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalSMSManager',
	components: {
		Content,
		Modal,
	},
	props: {
		settings: {
			type: Object,
			required: true,
		},
		fileId: {
			type: Number,
			required: true,
		},
	},
	data: () => ({
		token: '',
		phoneNumber: '',
		tokenRequested: false,
		loading: false,
	}),
	computed: {
		hasNumber() {
			return !isEmpty(this.settings.phoneNumber)
		},
	},
	watch: {
		'settings.phoneNumber'(val) {
			this.phoneNumber = val || ''
		},
	},
	mounted() {
		this.phoneNumber = this.settings.phoneNumber || ''
	},
	methods: {
		async saveNumber() {
			this.loading = true
			this.sanitizeNumber()

			await this.$nextTick()

			try {
				await confirmPassword()
				const { data: { phone }, success } = await settingsService.saveUserNumber(this.phoneNumber)

				this.phoneNumber = phone
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
				const { message } = await signService.requestSignCode(this.fileId)

				this.tokenRequested = true

				castArray(message)
					.forEach(showSuccess)
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
			this.phoneNumber = sanitizeNumber(this.phoneNumber)
		},
	},
}
</script>

<template>
	<Modal size="normal" @close="close">
		<NcContent class="modal-view">
			<template slot="header">
				<h2>{{ t('libresign', 'Sign with your cellphone number.') }}</h2>
				<!-- <p>{{ t('libresign', 'Sign the document.') }}</p> -->
			</template>

			<div v-if="hasNumber" class="code-request">
				<h3 class="phone">
					{{ phoneNumber }}
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
					<input v-model="phoneNumber"
						:disabled="loading"
						name="cellphone"
						placeholder="+55 00 0 0000 0000"
						type="tel"
						@change="sanitizeNumber">
				</div>

				<div>
					<button :disabled="loading || phoneNumber.length < 10" @click="saveNumber">
						{{ t('libresign', 'Save your number.') }}
					</button>
				</div>
			</div>
		</NcContent>
	</Modal>
</template>

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
