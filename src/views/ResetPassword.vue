/* eslint-disable no-new */
<template>
	<Content app-name="libresign">
		<form @submit="(e) => e.preventDefault()">
			<header>
				<h1>{{ t('libresign', 'Password reset') }}</h1>
				<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
			</header>
			<div class="container">
				<div class="input-group">
					<label for="new-password">{{ t('libresign', 'New password') }}</label>
					<Input v-model="password" type="password" />
				</div>
				<div class="input-group">
					<label for="repeat-password">{{ t('libresign', 'Repeat password') }}</label>
					<Input v-model="rPassword" :has-error="!hasEqualPassword" type="password" />
				</div>
				<button :disabled="!hableButton" class="primary btn-confirm" @click="checkPasswordForConfirm">
					{{ t('libresign', 'Confirm') }}
				</button>
			</div>
		</form>
		<ConfirmPassword v-if="modal" @submit="send" @close="changeModal" />
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import axios from '@nextcloud/axios'
import ConfirmPassword from '../Components/ConfirmPassword/Confirm'
import Input from '../Components/Input/Input'
import { generateUrl } from '@nextcloud/router'
export default {
	name: 'ResetPassword',
	components: {
		Content,
		Input,
		ConfirmPassword,
	},
	data() {
		return {
			password: '',
			rPassword: '',
			modal: false,
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
			if (!this.modal) {
				this.changeModal()
			}
		},
		changeModal() {
			this.modal = !this.modal
		},
		async send() {
			try {
				const response = await axios.post(generateUrl('/account/signature'), {
					password: this.password,
				})
				console.info(response)

			} catch (err) {
				console.error(err.response)
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

	.input-item{
		display: flex;
		flex-direction: row;
		border: 1px solid #cecece;
		border-radius: 10px;
		width: 100%;
		max-width: 380px;
		label{
			padding: 0 20px;
			border-radius: 10px 0 0 10px;
			background-color: #cecece;
		}
		input{
			border: none;
		}
		&:focus-within{
			border: thin solid #0082c9;
			box-shadow: inset 0 0 1em rgba(0, 130, 201, .5);
		}
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
