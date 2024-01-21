<template>
	<NcModal size="normal"
		:can-close="!loading"
		@close="close">
		<div class="modal__content">
			<h2 class="modal__header">
				{{ t('libresign', 'Sign with your email.') }}
			</h2>

			<div class="code-request">
				<div v-if="email" class="email">
					{{ email }}
				</div>
				<NcTextField v-else-if="!needConfirmCode"
					:disabled="loading"
					:label="t('libresign', 'Email')"
					:placeholder="t('libresign', 'Email')"
					:value.sync="sendTo" />
				<div v-else>
					<NcTextField :value.sync="token"
						:disabled="loading"
						name="code"
						type="text" />
				</div>

				<div class="modal__button-row">
					<NcButton v-if="!needConfirmCode" :disabled="loading || !canRequestCode" @click="requestCode">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('libresign', 'Request code.') }}
					</NcButton>

					<NcButton v-if="needConfirmCode" :disabled="loading || !canRequestCode" @click="sendCode">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('libresign', 'Send code.') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { showSuccess } from '@nextcloud/dialogs'
import { onError } from '../../../helpers/errors.js'
import { validateEmail } from '../../../utils/validators.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalEmailManager',
	components: {
		NcModal,
		NcTextField,
		NcLoadingIcon,
		NcButton,
	},
	props: {
		email: {
			type: String,
			required: true,
			default: '',
		},
		confirmCode: {
			type: Boolean,
			required: true,
			default: false,
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
		tokenRequested: false,
		loading: false,
		sendTo: '',
	}),
	computed: {
		canRequestCode() {
			if (validateEmail(this.sendTo)) {
				return true
			}
			return false
		},
		needConfirmCode() {
			return this.confirmCode || this.tokenRequested
		},
	},
	methods: {
		async requestCode() {
			this.loading = true
			this.tokenRequested = false

			await this.$nextTick()

			try {
				if (this.fileId.length > 0) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', { fileId: this.fileId }),
						{
							identify: this.sendTo,
							methodId: 'email',
						},
					)
					showSuccess(data.message)
				} else {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', { uuid: this.uuid }),
						{
							identify: this.sendTo,
							methodId: 'email',
						},
					)
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

.modal {
	&__content {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 20px;
		gap: 4px 0;
	}
	&__header {
		font-weight: bold;
		font-size: 20px;
		margin-bottom: 12px;
		line-height: 30px;
		color: var(--color-text-light);
	}
	&__button-row {
		display: flex;
		width: 100%;
		justify-content: space-between;
	}
}

</style>
