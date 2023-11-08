/* eslint-disable no-new */
<template>
	<NcContent app-name="libresign" class="with-sidebar--full">
		<form @submit="(e) => e.preventDefault()">
			<header>
				<h2>{{ t('libresign', 'Password reset') }}</h2>
				<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
			</header>
			<div class="container">
				<div class="input-group">
					<label for="new-password">{{ t('libresign', 'Current password') }}</label>
					<NcPasswordField :value.sync="currentPassword" type="password" />
				</div>
				<div class="input-group">
					<label for="new-password">{{ t('libresign', 'New password') }}</label>
					<NcPasswordField :value.sync="newPassword" type="password" />
				</div>
				<div class="input-group">
					<label for="repeat-password">{{ t('libresign', 'Repeat password') }}</label>
					<NcPasswordField :value.sync="rPassword" :has-error="!validNewPassord" type="password" />
				</div>
				<button :disabled="!canSave"
					:class="hasLoading ? 'btn-load loading primary btn-confirm' : 'primary btn-confirm'"
					@click="send">
					{{ t('libresign', 'Confirm') }}
				</button>
			</div>
		</form>
	</NcContent>
</template>

<script>
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'ResetPassword',
	components: {
		NcContent,
		NcPasswordField,
	},
	data() {
		return {
			newPassword: '',
			currentPassword: '',
			rPassword: '',
			hasLoading: false,
		}
	},
	computed: {
		canSave() {
			return this.currentPassword && this.validNewPassord
		},
		validNewPassord() {
			return this.newPassword.length > 3 && this.newPassword === this.rPassword
		},
	},
	methods: {
		async send() {
			this.hasLoading = true
			try {
				const response = await axios.patch(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), {
					current: this.currentPassword,
					new: this.newPassword,
				})
				showSuccess(response.data.message)
				this.hasLoading = false
				this.$emit('close', true)
			} catch (err) {
				if (err.response.data.message) {
					showError(err.response.data.message)
				} else {
					showError(t('libresign', 'Error creating new password, please contact the administrator'))
				}
				this.hasLoading = false
			}
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
		margin: 50px;
		justify-content: center;
		align-items: center;
		text-align: center;
		padding: 1rem;
		header{
			margin-bottom: 2.5rem;
		}
		h1{
			font-size: 45px;
			margin-bottom: 1rem;
		}
		p{
			font-size: 15px;
		}
	}

	.container {
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		width: 100%;
		max-width: 420px;

		text-align: start;
	}

	.btn-confirm{
		width: 100%;
		max-width: 280px;
	}

	.btn-load{
		background-color: transparent !important;
		font-size: 0;
		pointer-events: none;
		cursor: not-allowed;
		margin-top: 10px;
		border: none;

	}

	.input-group{
		display: flex;
		flex-direction: column;
		margin: 10px;
		width: 100%;
		label:first-child{
			opacity: 0.7;
		}
		input{
			width: 100%;
			max-width: 370px;
		}
	}
</style>
