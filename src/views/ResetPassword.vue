/* eslint-disable no-new */
<template>
	<NcContent app-name="libresign" class="with-sidebar--full">
		<form @submit="(e) => e.preventDefault()">
			<header>
				<h1>{{ t('libresign', 'Password reset') }}</h1>
				<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
			</header>
			<div class="container">
				<div class="input-group">
					<label for="new-password">{{ t('libresign', 'New password') }}</label>
					<NcPasswordField :value.sync="password" type="password" />
				</div>
				<div class="input-group">
					<label for="repeat-password">{{ t('libresign', 'Repeat password') }}</label>
					<NcPasswordField :value.sync="rPassword" :has-error="!hasEqualPassword" type="password" />
				</div>
				<button :disabled="!hableButton"
					:class="hasLoading ? 'btn-load loading primary btn-confirm' : 'primary btn-confirm'"
					@click="checkPasswordForConfirm">
					{{ t('libresign', 'Confirm') }}
				</button>
			</div>
		</form>
	</NcContent>
</template>

<script>
import { confirmPassword } from '@nextcloud/password-confirmation'
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'ResetPassword',
	components: {
		NcContent,
		NcPasswordField,
	},
	data() {
		return {
			password: '',
			rPassword: '',
			hasLoading: false,
		}
	},
	computed: {
		hableButton() {
			return !!(this.hasEqualPassword && this.password)
		},
		hasEqualPassword(val) {
			return this.password === this.rPassword
		},
	},
	methods: {
		checkPasswordForConfirm(param) {
			confirmPassword().then(() => {
				this.send()
			})
		},
		async send() {
			this.hasLoading = true
			try {
				await axios.post(generateUrl('/apps/libresign/api/0.1/account/signature'), {
					signPassword: this.password,
				})
				showSuccess(t('libresign', 'New password to sign documents has been created'))
				this.hasLoading = false
				this.$emit('close', true)
			} catch (err) {
				showError(t('libresign', 'Error creating new password, please contact the administrator'))
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
