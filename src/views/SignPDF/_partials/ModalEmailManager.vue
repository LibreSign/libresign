<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { showSuccess } from '@nextcloud/dialogs'
import { castArray } from 'lodash-es'
import Content from '../../../Components/Modals/ModalContent.vue'
import { onError } from '../../../helpers/errors.js'
import { service as signService } from '../../../domains/sign/index.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalEmailManager',
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
		tokenRequested: false,
		loading: false,
	}),
	methods: {
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
		<Content class="modal-view">
			<template slot="header">
				<h2>{{ t('libresign', 'Sign with your email.') }}</h2>
			</template>

			<div class="code-request">
				<h3 class="email">
					{{ settings.email }}
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
		</Content>
	</Modal>
</template>

<style lang="scss" scoped>
h3.email {
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

.code-request {
	width: 100%;
	display: flex;
	flex-direction: column;
	input {
		font-family: monospace;
		font-size: 1.3em;
		width: 50%;
		max-width: 250px;
		height: auto !important;
		display: block;
		margin: 0 auto;
	}
}

</style>
