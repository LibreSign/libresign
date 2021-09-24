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
					<Avatar v-show="controllerView !==3"
						id="avatar"
						:is-guest="true"
						:disable-menu="true"
						:user="email.length ? email : ''"
						:size="sizeAvatar" />

					<div v-show="controllerView === 0" class="form-account">
						<h2>{{ t('libresign', 'You need to create an account with the same email you received the invitation') }}</h2>

						<div class="group">
							<input
								v-model.trim="$v.email.$model"
								type="email"
								:placeholder="t('libresign', 'Email')">
							<span v-show="showErrorEmail" class="error">
								{{ emailError }}
							</span>
						</div>

						<div v-show="!passwordSign" class="group">
							<input
								v-model.trim="$v.password.$model"
								type="password"
								:placeholder="t('libresign', 'Password')">
							<span v-show="$v.password.$error">
								{{ t('libresign', 'Your password must be greater than 4 digits') }}
							</span>
						</div>

						<div v-show="!passwordSign" class="group">
							<input
								v-model.trim="$v.passwordConfirm.$model"
								type="password"
								:placeholder="t('libresign', 'Confirm password')">
							<span v-show="!isEqualPassword">
								{{ t('libresign', 'Passwords does not match') }}
							</span>
						</div>

						<div class="buttons">
							<button :class="hasLoading ? 'btn-load primary loading' : 'btn'"
								:disabled="!hableCreateUserButton"
								type="submit"
								@click.prevent="createUser">
								{{ t('libresign', 'Next') }}
							</button>
						</div>
					</div>

					<div v-show="controllerView === 1" class="form-password">
						<h2>{{ t('libresign', 'Set a password to sign the document') }}</h2>
						<div
							class="group">
							<input
								v-model="$v.pfx.$model"
								type="password"
								:placeholder="t('libresign', 'Password for sign document.')">
							<span v-show="showPfxError">{{ t('libresign', 'Your password must be greater than 4 digits') }} </span>
						</div>
						<div class="buttons">
							<button
								ref="btn"
								:class="hasLoading ? 'btn-load primary loading':'btn'"
								:disabled="hableCreatePfx"
								@click.prevent="createPfx">
								{{ t('libresign', 'Create password to sign document') }}
							</button>
						</div>
					</div>

					<div v-show="controllerView === 2" v-if="enabledFeatures.includes('manage_signatures')" class="form-signatures-initials">
						<div class="group">
							<h2 v-show="!viewDraw">
								{{ t('libresign', 'Do you want to create your signature and initials now?') }}
							</h2>
							<Modal v-show="viewDraw" @close="createSuccess">
								<Draw @close="createSuccess" @save="saveSignatureAndInitials" />
							</Modal>
						</div>

						<div v-show="!viewDraw" class="actions-buttons">
							<button :class="hasLoading ? 'btn-load primary loading' : 'btn'" @click.prevent="handleDraw(true)">
								{{ t('libresign', 'Yes') }}
							</button>
							<button :class="hasLoading ? 'btn-load primary loading' : 'btn'" @click.prevent="createSuccess">
								{{ t('libresign', 'No') }}
							</button>
						</div>
					</div>
					<div v-show="controllerView === 3" class="form-sucess">
						<h2>{{ t('libresign', 'Congratulations, you have created your account. Please wait, we will redirect you to the requested signature file.') }}</h2>
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
import { showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters } from 'vuex'

import { required, email, minLength } from 'vuelidate/lib/validators'

import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Modal from '@nextcloud/vue/dist/Components/Modal'
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
			default: 'Create user',
		},
	},

	data() {
		return {
			hasLoading: false,
			email: '',
			password: '',
			passwordConfirm: '',
			pfx: '',
			sizeAvatar: 100,
			passwordSign: false,
			initial: null,
			controllerView: 0,
			viewDraw: false,
		}
	},

	validations: {
		email: { required, email },
		password: { required, minLength: minLength(4) },
		passwordConfirm: { required, minLength: minLength(4) },
		pfx: { required, minLength: minLength(4) },
	},

	computed: {
		...mapGetters({
			errorCreateUser: 'user/getError',
			enabledFeatures: 'fController/getEnabledFeatures',
		}),
		isValidCreateUser() {
			return this.$v.email.$invalid && !this.$v.password.$invalid
						&& (this.$v.password.$model === this.$v.passwordConfirm.$model
						)
		},
		emailError() {
			if (this.$v.email.$model) {
				if (this.$v.email.$error) {
					return t('libresign', 'This is not a valid email')
				} else if (this.isEqualEmail === false) {
					return t('libresign', 'The email entered is not the same as the email in the invitation')
				}
			}
			return ''
		},
		showErrorEmail() {
			if (this.$v.email.$dirty) {
				return this.emailError.length > 2
			}
			return 0
		},
		passwordError() {
			if (this.$v.password.$model && this.$v.passwordConfirm.$model) {
				return t('libresign', 'Your password must be greater than 4 digits')
			}
			return ''
		},
		showErrorPassword() {
			if (this.$v.password.$model) {
				return this.$v.password.$error
			}
			return 0
		},
		showPfxError() {
			if (this.$v.pfx.$model) {
				return this.$v.pfx.$error
			}
			return false
		},
		isEqualPassword() {
			return this.password === this.passwordConfirm
		},
		isEqualEmail() {
			return this.initial.settings.accountHash === md5(this.email).toString()
		},
		hableCreateUserButton() {
			if (this.$v.email.$model) {
				if (this.isEqualEmail) {
					if (this.isEqualPassword) {
						return true
					}
					return false
				}
			}
			return false
		},
		hableCreatePfx() {
			return this.$v.pfx.$model ? this.$v.pfx.$error : false
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
		isEqual(val1, val2) {
			return val1 === val2
		},

		handleDraw(status) {
			this.viewDraw = status
		},

		createSuccess() {
			this.controllerView = 3

			setTimeout(() => {
				this.$router.push({ name: 'SignPDF' })
			}, 3000)
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
				password: this.password,
				uuid: this.$route.params.uuid,
			})

			if (this.errorCreateUser) {
				this.passwordSign = false
				this.hasLoading = false
			} else {
				this.passwordSign = true
				this.hasLoading = false
				this.controllerView = 1
			}
		},

		async createPfx() {
			this.hasLoading = true
			await this.createPFXAction({ signPassword: this.pfx })

			if (this.errorCreateUser) {
				this.hasLoading = false
			} else {
				this.controllerView = 2
			}
		},
		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/CreateUser.scss';
</style>
