<template>
	<NcContent app-name="libresign" class="with-sidebar--full">
		<form @submit="e => e.preventDefault()">
			<header>
				<h2>{{ t('libresign', 'Password Creation') }}</h2>
			</header>
			<div class="container">
				<p>{{ t('libresign', 'For security reasons, you must create a password to sign the documents. Enter your new password in the field below.') }}</p>
				<div class="input-group">
					<label for="password">{{ t('libresign', 'Enter a password') }}</label>
					<NcPasswordField :disabled="hasLoading"
						:label="t('libresign', 'Enter a password')"
						:placeholder="t('libresign', 'Enter a password')"
						:value.sync="password" />
				</div>
				<NcButton :disabled="hasLoading" @click="send">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
					</template>
					{{ t('libresign', 'Confirm') }}
				</NcButton>
			</div>
		</form>
	</NcContent>
</template>

<script>
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
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
		NcContent,
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
			modal: false,
			hasLoading: false,
			password: '',
			hasPfx: false,
		}
	},
	methods: {
		async send() {
			this.hasLoading = true
			try {
				await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature'), {
					signPassword: this.password,
				})
				showSuccess(t('libresign', 'New password to sign documents has been created'))
				this.signMethodsStore.hasSignatureFile(true)
				this.clear()
				this.$emit('close', true)
				this.$emit('password:created', true)
			} catch (err) {
				this.signMethodsStore.hasSignatureFile(false)
				if (err.response.data.message) {
					showError(err.response.data.message)
				} else {
					showError(t('libresign', 'Error creating new password, please contact the administrator'))
				}
				this.$emit('password:created', false)
			}
			this.hasLoading = false
		},
		clear() {
			this.password = ''
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
