<template>
	<div class="container-sign">
		<div class="avatar-local">
			<Avatar id="avatar" :user="userName" />
			<span>{{ userName }}</span>
		</div>

		<template v-if="!signWithSMS">
			<InputAction
				ref="input"
				class="input"
				:type="'password'"
				:disabled="disabledButton"
				:loading="hasLoading"
				@submit="sign" />
			<a class="forgot-sign" @click="handleModal(true)">
				{{ messageForgot }}
			</a>
			<EmptyContent class="emp-content">
				<template #desc>
					<p v-if="havePfx">
						{{ t('libresign', 'Enter your password to sign this document') }}
					</p>
					<p v-else>
						{{
							t('libresign',
								'You need to create a password to sign this document. Click "Create password to sign document" and create a password.')
						}}
					</p>
				</template>
				<template #icon>
					<img v-if="havePfx" :src="icon">
					<div v-else class="icon icon-rename" />
				</template>
			</EmptyContent>
			<slot name="actions" />
			<Modal v-if="modal" size="large" @close="handleModal(false)">
				<ResetPassword v-if="havePfx" @close="handleModal(false)" />
				<CreatePassword v-if="!havePfx" @changePfx="changePfx" @close="handleModal(false)" />
			</Modal>
		</template>
		<template v-else>
			<template v-if="$store.state.settings.data.settings.phone">
				<template v-if="!tokenSent">
					<div style="font-size: 0.9em;">
						We'll send an SMS token to *****543.
					</div>
					<div style="display: flex;">
						<div>
							<button
								style="margin-right: 10px;"
								class="button-vue btn btn-green"
								:disabled="sendingToken"
								@click="sendToken()">
								<template v-if="!sendingToken">
									Send SMS Token
								</template>
								<template v-else>
									Sending token...
								</template>
							</button>
						</div>
						<div>
							<button class="button-vue btn btn-blue" @click="signWithSMS = false">
								Sign with password
							</button>
						</div>
					</div>
				</template>
				<template v-else>
					<div>
						<div>Token sent! Type it below:</div>
						<div class="display: flex; align-items: center;">
							<div>
								<input v-model="smsToken" class="" type="text">
							</div>
							<div>
								<button
									class="button-vue btn btn-green"
									:disabled="!smsToken">
									Sign
								</button>
							</div>
						</div>
					</div>
				</template>
			</template>
			<template v-else>
				<div>Phone number:</div>
				<div class="flex">
					<div>
						<input v-model="phone" type="text">
					</div>
					<div>
						<button class="btn btn-green" @click="savePhone">
							Save
						</button>
					</div>
				</div>
			</template>
		</template>
	</div>
</template>

<script>

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../views/ResetPassword.vue'
import CreatePassword from '../../views/CreatePassword.vue'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import InputAction from '../InputAction'
import Icon from '../../assets/images/signed-icon.svg'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Sign',
	components: {
		Avatar,
		InputAction,
		EmptyContent,
		Modal,
		ResetPassword,
		CreatePassword,
	},
	props: {
		disabled: {
			type: Boolean,
			require: false,
		},
		hasLoading: {
			type: Boolean,
			required: false,
			default: false,
		},
		pfx: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	data() {
		return {
			icon: Icon,
			modal: false,

			signWithSMS: true,
			phoneNumberIsRequired: null,

			sendingToken: false,
			tokenSent: false,

			phone: null,
			smsToken: null,
		}
	},
	computed: {
		user() {
			return getCurrentUser()
		},
		userName() {
			const currentUser = getCurrentUser()
			if (currentUser === null) {
				return ''
			} else {
				return currentUser.uid
			}
		},
		havePfx() {
			return this.pfx ? this.pfx : false
		},
		messageForgot() {
			return this.havePfx ? t('libresign', 'Forgot your password?') : t('libresign', 'Create password to sign document')
		},
		disabledButton() {
			if (this.havePfx) {
				if (this.hasLoading) {
					return true
				}
				return false
			}
			return true
		},
	},
	mounted() {
	},
	methods: {
		clearInput() {
			this.$refs.input.clearInput()
		},
		sign(param) {
			this.clearInput()
			this.$emit('sign:document', param)
		},
		changePfx(value) {
			this.pfx = value
			this.$emit('change-pfx', true)
		},
		handleModal(state) {
			this.modal = state
		},

		sendToken() {
			this.sendingToken = true

			setTimeout(() => {
				this.sendingToken = false
				this.tokenSent = true
			}, 1200)
		},
		async savePhone() {
			if (this.phone) {
				try {
					// const response = await axios.post(generateUrl(`/apps/libresign/api/0.1/sign/file_id/${this.fileInfo.id}/${user.signatureId}`))
					const response = await axios.post(generateUrl('/apps/libresign/api/0.1/'))

					if (this.signers.length <= 0) {
						this.option('signatures')
					}

				   await this.getInfo()
				   this.sendToken()

				   showSuccess(response.data.message)
				} catch (err) {
					showError(err)
				}
			}
		},
	},
}
</script>

<style lang="scss">
@import './styles';

.flex {
	display: flex;
}

.btn{
	border: 1px solid #ddd;
	padding: 8px 12px;
	border-radius: 12px;
	cursor: pointer;
	background-color: #fff;
	color: #333;
	font-weight: bold;

	&[disabled=disabled] {
		cursor: default;
		background-color: #ddd !important;
		color: #777 !important;
	}

	&.btn-green{
		border-color: #393;
		background-color: #393;
		color: white;

		&[disabled=disabled] {
			border-color: #6A6;
			background-color: #6A6 !important;
			color: #ddd !important;
		}
	}

	&.btn-blue{
		border-color: #339;
		background-color: #339;
		color: white;

		&[disabled=disabled] {
			border-color: #66A;
			background-color: #66A !important;
			color: #ddd !important;
		}
	}
}
</style>
