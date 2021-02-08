<template>
	<Content app-name="libresign" class="jumbotron">
		<div id="container">
			<div class="bg">
				<form>
					<Avatar id="avatar" :user="username.length ? username : 'User'" :size="sizeAvatar" />
					<div class="group">
						<input
							ref="username"
							v-model="username"
							type="text"
							:required="validator.name"
							placeholder="Nome"
							@blur="validationName(); validationBtn(); validationBtn()">
						<div v-show="validator.name" class="submit-icon icon-error-white" />
					</div>
					<div class="group">
						<input ref="pass"
							type="password"
							:required="validator.pass"
							placeholder="Senha"
							@blur="validationPass(); validationPasswords(); validationBtn()">
						<div v-show="validator.pass" class="submit-icon icon-error-white" />
					</div>
					<div class="group">
						<input ref="passConfirm"
							v-tooltip.right="{
								content: 'Assegure-se de que os campos Senha sejam iguais',
								show: true,
								// trigger: 'hover focus'
							}"
							type="password"
							:required="validator.passConfirm"
							placeholder="Confirmar senha"
							@blur="validationPassConfirm(); validationPasswords(); validationBtn()">
						<div v-show="validator.passConfirm" class="submit-icon icon-error-white" />
					</div>

					<div
						v-tooltip.right="{
							content: 'Senha para confirmar assinatura no documento!',
							show: false,
							trigger: 'hover focus'
						}"
						class="group">
						<input ref="pfx"
							v-model="pfx"
							:required="validator.pfx"
							placeholder="Senha PFX">
						<div v-show="validator.pfx" class="submit-icon icon-error-white" />
					</div>
					<button :key="validator.btn" class="btn" :disabled="!validator.btn">
						Cadastrar
					</button>
				</form>
			</div>
		</div>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
export default {
	name: 'CreateUser',
	components: {
		Content,
		Avatar,
	},

	data() {
		return {
			username: '',
			pfx: '',
			sizeAvatar: 100,
			validator: {
				name: false,
				pass: false,
				passConfirm: false,
				pfx: false,
				btn: false,
			},
		}
	},
	watch: {
		pfx() {
			this.validationBtn()
		},
	},
	created() {
		this.changeSizeAvatar()
	},

	methods: {
		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},
		validationName() {
			if (this.$refs.username.value.length < 3) {
				this.validator.name = true
			} else {
				this.validator.name = false
			}
		},
		validationPass() {
			if (this.$refs.pass.value.length < 3) {
				this.validator.pass = true
			} else {
				this.validator.pass = false
			}
		},
		validationPassConfirm() {
			if (this.$refs.passConfirm.value.length < 3) {
				this.validator.passConfirm = true
			} else {
				this.validator.passConfirm = false
				this.validator.pass = false
			}
		},
		validationPfx() {
			if (this.$refs.pfx.value.length < 3) {
				this.validator.pfx = true
			} else {
				this.validator.pfx = false
			}
		},
		validationPasswords() {
			if (this.$refs.pass.value !== this.$refs.passConfirm.value) {
				this.validator.pass = true
				this.validator.passConfirm = true
			} else {
				this.validator.pass = false
				this.validator.passConfirm = false
			}
		},
		validationBtn() {
			const name = this.validator.name
			const passConfirm = this.validator.passConfirm
			const pfx = this.validator.pfx
			if (name === false && passConfirm === false && pfx === false) {
				this.validator.btn = false
				if (this.$refs.username.value.length > 3 && this.$refs.passConfirm.value.length > 3 && this.$refs.pfx.value.length > 3) {
					this.validator.btn = true
				}
			} else {
				this.validator.btn = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
#container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
}

#avatar{
	margin-bottom: 20px;
}

#password{
	margin-right: 3px;
}

form{
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 80%;
	margin: 10px 0px 10px 0px;
}

form > div{
	width: 100%;
}

input {
	min-width: 317px;
	width: 100%
}
@media screen and (max-width: 535px) {
	form {width: 90%}
	.jumbotron{
		background-image: linear-gradient(#fff,#fff);
		animation: 1s;
		background-position-x: 50%;
	}

}

#tooltip{
	position: relative;

	span{
		width: 160px;
		padding: 8px;
		border-radius: 4px;
		font-size: 14px;
		font-weight: 500;
		opacity: 0;
		transition: opacity 0.4s;
		visibility: visible;

		position: absolute;
		bottom: calc(100% + 12px);
		left: 50%;
	}
}

.group{
	display: flex;
}

.btn{
	margin-top: 10px;
}

.bg{
	display: flex;
	justify-content: center;
	background-image: linear-gradient(40deg, #0082c9 0%, #1cafff 100%);
	width: 400px;
	border-radius: 5px;
	box-shadow: rgba(0, 130, 201, 0.4) 5px 5px, rgba(0, 130, 201, 0.3) 10px 10px, rgba(0, 130, 201, 0.2) 15px 15px, rgba(0, 130, 201, 0.1) 20px 20px, rgba(0, 130, 201, 0.05) 25px 25px;
}

.jumbotron{
	background-image: url('../../img/bg.jpg');
	background-size: cover;
}

</style>
