<template>
	<NcModal size="normal"
		:can-close="false"
		@close="close">
		<div class="modal__content">
			<h2 class="modal__header">
				{{ t('libresign', 'Sign with your email.') }}
			</h2>

			<div class="code-request">
				<div v-if="signMethodsStore.blurredEmail().length > 0" class="email">
					{{ signMethodsStore.blurredEmail() }}
				</div>
				<div v-if="signMethodsStore.settings.emailToken.hasConfirmCode">
					{{ t('libresign', 'Enter the code you received') }}
					<NcTextField maxlength="6"
						:value.sync="token"
						:disabled="loading"
						:label="t('libresign', 'Enter your code')"
						:placeholder="t('libresign', 'Enter your code')"
						name="code"
						type="text" />
				</div>
				<NcTextField v-else
					:disabled="loading"
					:label="t('libresign', 'Email')"
					:placeholder="t('libresign', 'Email')"
					:helper-text="errorMessage"
					:error="errorMessage.length > 0"
					:value.sync="sendTo"
					@input="onChangeEmail" />

				<div class="modal__button-row">
					<NcButton v-if="signMethodsStore.settings.emailToken.hasConfirmCode" :disabled="loading && !canRequestCode" @click="requestNewCode">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('libresign', 'Request new code') }}
					</NcButton>
					<NcButton v-if="!signMethodsStore.settings.emailToken.hasConfirmCode"
						:disabled="loading || !canRequestCode"
						type="primary"
						@click="requestCode">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('libresign', 'Request code.') }}
					</NcButton>

					<NcButton v-if="signMethodsStore.settings.emailToken.hasConfirmCode" :disabled="!canSendCode" @click="sendCode">
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
import { loadState } from '@nextcloud/initial-state'
import md5 from 'blueimp-md5'
import { onError } from '../../../helpers/errors.js'
import { validateEmail } from '../../../utils/validators.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'

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
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data: () => ({
		token: '',
		loading: false,
		tokenLength: loadState('libresign', 'token_length', 6),
		errorMessage: '',
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
			return this.signMethodsStore.settings.emailToken.hasConfirmCode && !this.loading && this.token.length === this.tokenLength
		},
	},
	methods: {
		onChangeEmail() {
			if (validateEmail(this.sendTo)) {
				if (md5(this.sendTo) !== this.signMethodsStore.settings.emailToken.hashOfEmail) {
					this.errorMessage = t('libresign', 'Invalid email')
					return
				}
				this.errorMessage = ''
			}
		},
		requestNewCode() {
			this.signMethodsStore.hasEmailConfirmCode(false)
			this.token = ''
		},
		async requestCode() {
			this.loading = true
			this.signMethodsStore.hasEmailConfirmCode(false)

			await this.$nextTick()

			try {
				if (this.fileId.length > 0) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', { fileId: this.fileId }),
						{
							identify: this.sendTo,
							identifyMethod: 'email',
							signMethod: 'emailToken',
						},
					)
					showSuccess(data.message)
				} else {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', { uuid: this.uuid }),
						{
							identify: this.sendTo,
							identifyMethod: 'email',
							signMethod: 'emailToken',
						},
					)
					showSuccess(data.message)
				}
				this.signMethodsStore.hasEmailConfirmCode(true)
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
		sendCode() {
			this.$emit('change', this.token)
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
