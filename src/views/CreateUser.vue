<template>
	<Content app-name="libresign" class="jumbotron">
		<div id="container">
			<div class="bg">
				<form>
					<Avatar id="avatar" :user="email.length ? email : 'User'" :size="sizeAvatar" />
					<div class="group">
						<input
							v-model="email"
							v-tooltip.top="{
								content: t('libresign', 'Enter your email.'),
								show: tooltip.name,
								trigger: 'false',
							}"
							type="email"
							:required="validator.name"
							:placeholder="t('libresign', 'E-mail')"
							@focus="tooltip.nameFocus = true; tooltip.name = false"
							@blur="tooltip.nameFocus = false; validationName()">
					</div>
					<div class="group">
						<input
							v-model="pass"
							v-tooltip.bottom="{
								content: t('libresign', 'Password must be at least 3 characters.'),
								show: tooltip.pass,
								trigger: 'false'
							}"
							type="password"
							:required="validator.pass"
							:placeholder="t('libresign', 'Password')"
							@focus="tooltip.passFocus = true; tooltip.pass = false"
							@blur="tooltip.passFocus = false; validationPass()">
					</div>
					<div class="group">
						<input
							v-model="passConfirm"
							v-tooltip.bottom="{
								content: t('libresign', 'Password does not match'),
								show: tooltip.passConfirm,
								trigger: 'false'
							}"
							type="password"
							:required="validator.passConfirm"
							:placeholder="t('libresign', 'Confirm password')"
							@focus="tooltip.passConfirmFocus = true; tooltip.passConfirm = false"
							@blur="tooltip.passConfirmFocus = false; validationPasswords()">
					</div>

					<div
						v-tooltip.right="{
							content: t('libresign', 'Password to confirm signature on the document!'),
							show: false,
							trigger: 'hover focus'
						}"
						class="group">
						<input
							v-model="pfx"
							type="password"
							:required="validator.pfx"
							:placeholder="t('libresign', 'Password for sign document.')">
					</div>
					<button class="btn" :disabled="!validator.btn" @click.prevent="createUser">
						Cadastrar
					</button>
				</form>
			</div>
		</div>
	</Content>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CreateUser',
	components: {
		Content,
		Avatar,
	},

	props: {
		messageToast: {
			type: String,
			default: 'Create a user',
		},
	},

	data() {
		return {
			email: '',
			pass: '',
			passConfirm: '',
			pfx: '',
			sizeAvatar: 100,
			validator: {
				name: false,
				pass: false,
				passConfirm: false,
				pfx: false,
				btn: false,
			},
			tooltip: {
				name: false,
				nameFocus: false,
				pass: false,
				passFocus: false,
				passConfirm: false,
				passConfirmFocus: false,

			},
		}
	},
	watch: {
		email() {
			this.validationName()
			this.validationBtn()
		},
		pass() {
			this.validationPass()
			this.validationPasswords()
			this.validationBtn()
		},
		passConfirm() {
			this.validationPassConfirm()
			this.validationPasswords()
			this.validationBtn()
		},
		pfx() {
			this.validationPfx()
			this.validationBtn()
		},
	},
	created() {
		this.changeSizeAvatar()
		showError(t('libresign', this.messageToast))
	},

	methods: {
		async createUser() {
			try {
				await axios.post(generateUrl(`/apps/libresign/api/0.1/account/create/${this.$route.params.uuid}`), {
					email: this.email,
					password: this.pass,
					signPassword: this.pfx,
				})
				showSuccess(t('libresigng', 'User created!'))
				this.$route.push({ name: 'SignPDF' })
			} catch (err) {
				showError(`Error ${err.response.data.action}: ${err.response.data.message}`)
			}
		},

		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},

		validationName() {
			if (this.email.length < 3) {
				this.validator.name = true
				if (this.tooltip.nameFocus === false) {
					this.tooltip.name = true
				} else {
					this.tooltip.name = false
					this.tooltip.name = false
				}
			} else {
				this.validator.name = false
				this.tooltip.name = false
			}
		},
		validationPass() {
			if (this.pass.length < 3) {
				this.validator.pass = true
				if (this.tooltip.passFocus === false) {
					this.tooltip.pass = true
				} else {
					this.tooltip.pass = false
				}
			} else {
				this.validator.pass = false
				this.tooltip.pass = false
			}
			if (this.pass.length > 0 && this.passConfirm.length > 0 && this.pass !== this.passConfirm) {
				this.validator.pass = true
				this.validator.passConfirm = true
			} else {
				this.validator.pass = false
				this.validator.passConfirm = false
			}
		},
		validationPassConfirm() {
			if (this.passConfirm.length < 3) {
				this.validator.passConfirm = true
			} else {
				this.validator.passConfirm = false
				this.validator.pass = false
			}
		},
		validationPfx() {
			if (this.pfx.length < 3) {
				this.validator.pfx = true
			} else {
				this.validator.pfx = false
			}
		},
		validationPasswords() {
			if (this.pass !== this.passConfirm) {
				this.validator.pass = true
				this.validator.passConfirm = true
				if (this.tooltip.passConfirmFocus === false && this.tooltip.passFocus === false) {
					this.tooltip.passConfirm = true
				} else {
					this.tooltip.passConfirm = false
				}
			} else {
				this.validator.pass = false
				this.validator.passConfirm = false
			}
		},
		validationBtn() {
			if (this.validator.name === false && this.validator.passConfirm === false && this.validator.pfx === false) {
				if (this.email.length > 2 && this.passConfirm.length > 2 && this.pfx.length > 2) {
					this.validator.btn = true
				} else {
					this.validator.btn = false
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
	background-color: rgb(30, 120, 193);
	// color: rgb(255,255,255) !important;
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
	width: 100%;
	background-color: white;
	color: black;
	border-color: white;
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
	align-items: center;
	justify-content: center;
}

.btn{
	margin-top: 10px;
	box-shadow: rgba(0, 0, 0, 0.15) 1.95px 1.95px 2.6px;
}

.bg{
	display: flex;
	justify-content: center;
	background: linear-gradient(134.01deg, rgba(196, 196, 196, 0.4) 5.27%, rgba(196, 196, 196, 0.1) 90.05%);
	box-shadow: 0px 4px 24px -1px rgba(0, 0, 0, 0.1);
	backdrop-filter: blur(20px);
	width: 400px;
	border-radius: 5px;
	transition: all;
	transition-duration: 1s;
}

.jumbotron{
	background-image: url('../../img/bgCreateUser.jpg');
	background-size: cover;
	-moz-background-size: cover;
	transition: background-position-x;
	transition-duration: 2s;
	overflow: hidden;
	-moz-overflow: hidden;
	background-position: center;
}

@-moz-document url-prefix()
{
	.bg:after{
		content: '';
		filter: blur(20px);
	}
}

@media screen and (max-width: 1300px){
	.jumbotron{
		background-position-x: 60%;
	}
}
@media screen and (max-width: 1300px){
	.jumbotron{
		background-position-x: 70%;
	}
}
@media screen and (max-width: 768px) {
	.jumbotron{
		background-position-x: 30%;
	}
}

@media screen and (max-width: 535px) {
	.bg{
		transition: ease all .5s;
		width: 100%;
		height: 100%;
		backdrop-filter: blur(10px);
	}
	input {
		max-width: 90%
	}
	#content-vue{
		padding-top: 0px;
	}
	.jumbotron{
		background-position-x: 90%;
	}
}

@media screen and (max-width: 380px) {
	.bg{
		transition: ease all .5s;
		border-radius: 0;
		width: 100%;
		height: 100%;
		box-shadow: none;
	}
	input{
		max-width: 317px;
	}
}
</style>
