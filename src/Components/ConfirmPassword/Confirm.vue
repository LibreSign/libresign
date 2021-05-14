<template>
	<Modal @close="closeModal">
		<div class="container">
			<h1>{{ t('libresign', 'Authentication required') }}</h1>
			<h2>{{ t('libresign', 'This action requires you to confirm your password') }}</h2>
			<form @submit="e=> e.preventDefault()">
				<span v-if="!!errorMessage">{{ errorMessage }}</span>
				<label>{{ t('libresign','Password') }}</label>
				<Input v-model="password" class="input-password" type="password" />
				<button class="primary" @click="confirmPassword">
					{{ t('libresign', 'Confirm') }}
				</button>
			</form>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Input from '../Input/Input.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
export default {
	name: 'Confirm',
	components: {
		Modal,
		Input,
	},
	data() {
		return {
			password: '',
			errorMessage: '',
		}
	},

	methods: {
		async confirmPassword() {
			try {
				await axios.post(generateUrl('/login/confirm'), {
					password: this.password,
				})
				this.send()
			} catch (err) {
				this.errorMessage = t('libresign', 'Incorrect password!')
			}
		},
		closeModal() {
			this.$emit('close', true)
		},
		send() {
			this.$emit('submit', true)
		},
	},
}
</script>
<style lang="scss" scoped>
	.container{
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 50vw ;
		padding: 30px;
		text-align: center;

		h1{
			font-size: 25px;
			font-weight: bold;
		}
		h2{
			font-size: 18px;
			font-weight: normal;
		}

		form{
			display: flex;
			flex-direction: column;
			width: 100%;
			max-width: 380px;
			justify-content: center;
			align-items: center;
			margin-top: 36px;

			span{
				color: #b40c0c;
				font-style: italic;
			}

			button{
				width: 80%;
			}
			.input-password{
				margin-bottom: 10px;
			}
		}
	}
</style>
