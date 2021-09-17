<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<Content app-name="libresign" class="jumbotron">
		<div id="container">
			<div class="bg">
				<form>
					<Avatar id="avatar"
						:is-guest="true"
						:disable-menu="true"
						:user="email.length ? email : ''"
						:size="sizeAvatar" />

					<div v-show="controllerView === 0" class="form-account">
						<h2>{{ t('libresign', 'You need to create an account with the same email you received the invitation') }}</h2>

						<div class="group">
							<input
								v-model="email"
								v-tooltip.top="{
									content: t('libresign', 'The email entered is not the same as the email in the invitation'),
									show: tooltip.email,
									trigger: 'false'
								}"
								type="email"
								:required="validator.name"
								:placeholder="t('libresign', 'Email')"
								@focus="tooltip.nameFocus = true; tooltip.name = false"
								@blur="tooltip.nameFocus = false; validationName()">
						</div>

						<div v-show="!passwordSign" class="group">
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

						<div v-show="!passwordSign" class="group">
							<input
								v-model="passConfirm"
								v-tooltip.bottom="{
									content: t('libresign', 'Passwords do not match'),
									show: tooltip.passConfirm,
									trigger: 'false'
								}"
								type="password"
								:required="validator.passConfirm"
								:placeholder="t('libresign', 'Confirm password')"
								@focus="tooltip.passConfirmFocus = true; tooltip.passConfirm = false"
								@blur="tooltip.passConfirmFocus = false; validationPasswords()">
						</div>
					</div>

					<div v-show="controllerView === 1" class="form-password">
						<h2>{{ t('libresign', 'Set a password to sign the document') }}</h2>
						<div
							class="group">
							<input
								v-model="pfx"
								type="password"
								:required="validator.pfx"
								:placeholder="t('libresign', 'Password for sign document.')">
						</div>
					</div>

					<div v-show="controllerView === 2" class="form-signatures-initials">
						<div class="group">
							<h2 v-show="!viewDraw">
								{{ t('libresign', 'Do you want to create your signature and initials now?') }}
							</h2>
							<Modal v-show="viewDraw">
								<Draw @close="cancelCreateDraw" @save="saveSignatureAndInitials" />
							</Modal>
						</div>
						<div v-show="!viewDraw" class="actions-buttons">
							<button :class="hasLoading ? 'btn-load primary loading' : 'btn'" @click.prevent="handleDraw(true)">
								{{ t('libresign', 'Yes') }}
							</button>
							<button :class="hasLoading ? 'btn-load primary loading' : 'btn'" @click.prevent="handleDraw(false)">
								{{ t('libresign', 'No') }}
							</button>
						</div>
					</div>

					<div v-show="controllerView !== 2" class="buttons">
						<button
							v-if="!passwordSign"
							:class="hasLoading ? 'btn-load primary loading':'btn'"
							:disabled="!validator.back"
							type="submit"
							@click.prevent="createUser">
							{{ t('libresign', 'Next') }}
						</button>

						<button v-if="passwordSign"
							ref="btn"
							:class="hasLoading ? 'btn-load primary loading':'btn'"
							:disabled="!validator.btn"
							@click.prevent="createPfx">
							{{ t('libresign', 'Create password to sign document') }}
						</button>
					</div>
				</form>
			</div>
		</div>
	</Content>
</template>

<script>
import md5 from 'crypto-js/md5'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters } from 'vuex'
import Draw from '../Components/Draw'

export default {
	name: 'CreateUser',
	components: {
		Content,
		Avatar,
		Draw,
		Modal,
	},

	props: {
		messageToast: {
			type: String,
			default: 'Create a user',
		},
	},

	data() {
		return {
			btnRegisterName: t('libresign', 'Create an account'),
			hasLoading: false,
			email: '',
			pass: '',
			passConfirm: '',
			pfx: '',
			sizeAvatar: 100,
			passwordSign: false,
			validator: {
				name: false,
				pass: false,
				passConfirm: false,
				pfx: false,
				btn: false,
				back: false,
			},
			tooltip: {
				name: false,
				email: false,
				nameFocus: false,
				pass: false,
				passFocus: false,
				passConfirm: false,
				passConfirmFocus: false,

			},
			initial: null,
			controllerView: 0,
			viewDraw: false,
		}
	},
	computed: {
		...mapGetters({
			errorCreateUser: 'user/getError',
		}),
		isEqualEmail() {
			return this.initial.settings.accountHash === md5(this.email).toString()
		},
		alignButtons() {
			if (this.hasLoading) {
				return 'btn-load primary loading'
			}
			if (!this.passwordSign) {
				return 'btn-align'
			}
			return 'btn'
		},
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
			this.validatorback()
		},
		pfx() {
			this.validationPfx()
			this.validationBtn()
		},
	},

	created() {
		this.changeSizeAvatar()
		showError(t('libresign', this.messageToast))
		this.initial = JSON.parse(loadState('libresign', 'config'))
	},

	methods: {
		...mapActions({
			createUSER: 'user/CREATE',
			createPFXAction: 'user/CREATE_PFX',
		}),

		handleDraw(status) {
			this.viewDraw = status
		},

		handleViews(view) {
			this.controllerView = view
		},

		saveSignatureAndInitials(param) {
			console.info(param)
		},

		cancelCreateDraw() {
			this.$router.push({ name: 'SignPDF' })
		},

		async createUser() {
			this.hasLoading = true
			await this.createUSER({
				email: this.email,
				password: this.pass,
				signPassword: this.pfx,
				uuid: this.$route.params.uuid,
			})

			if (this.errorCreateUser) {
				this.passwordSign = false
				this.hasLoading = false
			} else {
				this.passwordSign = true
				this.hasLoading = false
			}
		},
		async createPfx() {
			this.hasLoading = true
			await this.createPFXAction({ signPassword: this.pfx })

			if (this.errorCreateUser) {
				this.hasLoading = false
			} else {
				this.$router.push({ name: 'SignPDF' })
			}
		},
		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},
		validationName() {
			this.isEqualEmail === false ? this.tooltip.email = true : this.tooltip.email = false

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
		validatorback() {
			if (this.validator.name === false && this.validator.passConfirm === false) {
				if (this.email.length > 2 && this.passConfirm.length > 2) {
					if (this.isEqualEmail) {
						this.validator.back = true
					}
				} else {
					this.validator.back = false
				}
			} else {
				this.validator.back = false
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
@import '../assets/styles/CreateUser.scss';
</style>
