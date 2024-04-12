<template>
	<NcDialog v-if="signMethodsStore.modal.createPassword"
		:name="t('libresign', 'Password Creation')"
		@closing="signMethodsStore.closeModal('createPassword')">
		<p>{{ t('libresign', 'For security reasons, you must create a password to sign the documents. Enter your new password in the field below.') }}</p>
		<NcPasswordField :disabled="hasLoading"
			:label="t('libresign', 'Enter a password')"
			:placeholder="t('libresign', 'Enter a password')"
			:value.sync="password" />
		<template #actions>
			<NcButton :disabled="hasLoading" @click="send">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Confirm') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useSignMethodsStore } from '../store/signMethods.js'

export default {
	name: 'CreatePassword',
	components: {
		NcDialog,
		NcPasswordField,
		NcButton,
		NcLoadingIcon,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data() {
		return {
			hasLoading: false,
			password: '',
		}
	},
	methods: {
		async send() {
			this.hasLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature'), {
				signPassword: this.password,
			})
				.then(() => {
					showSuccess(t('libresign', 'New password to sign documents has been created'))
					this.signMethodsStore.setHasSignatureFile(true)
					this.password = ''
					this.$emit('password:created', true)
					this.signMethodsStore.closeModal('createPassword')
				})
				.catch(({ response }) => {
					this.signMethodsStore.setHasSignatureFile(false)
					if (response.data.message) {
						showError(response.data.message)
					} else {
						showError(t('libresign', 'Error creating new password, please contact the administrator'))
					}
					this.$emit('password:created', false)
				})
			this.hasLoading = false
		},
	},
}
</script>
<style lang="scss" scoped>
	form{
		display: flex;
		flex-direction: column;
		width: 100%;
		max-width: 100%;
		justify-content: center;
		align-items: center;
		text-align: center;
		header{
			font-weight: bold;
			font-size: 20px;
			margin-bottom: 12px;
			line-height: 30px;
			color: var(--color-text-light);
		}
	}

	.container {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 20px;
		gap: 4px 0;
	}

	.input-group{
		justify-content: space-between;
		display: flex;
		flex-direction: column;
		width: 100%;
	}
</style>
