<template>
	<div class="container-sign">
		<div class="avatar-local">
			<Avatar id="avatar" :user="userName" />
			<span>{{ userName }}</span>
		</div>

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
				<p v-if="hasPfx">
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
				<img v-if="hasPfx" :src="icon">
				<div v-else class="icon icon-rename" />
			</template>
		</EmptyContent>
		<slot name="actions" />
		<Modal v-if="modal" size="large" @close="handleModal(false)">
			<ResetPassword v-if="hasPfx" @close="handleModal(false)" />
			<CreatePassword v-if="!hasPfx" @close="handleModal(false)" />
		</Modal>
	</div>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../views/ResetPassword.vue'
import CreatePassword from '../../views/CreatePassword.vue'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import InputAction from '../InputAction'
import Icon from '../../assets/images/signed-icon.svg'
import { getCurrentUser } from '@nextcloud/auth'
import { mapState } from 'vuex'

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
	},
	data() {
		return {
			icon: Icon,
			modal: false,
		}
	},
	computed: {
		userName() {
			return getCurrentUser().uid
		},
		messageForgot() {
			return this.hasPfx ? t('libresign', 'Forgot your password?') : t('libresign', 'Create password to sign document')
		},
		disabledButton() {
			if (this.hasPfx) {
				if (this.hasLoading) {
					return true
				}
				return false
			}
			return true
		},
		...mapState({
			hasPfx: state => state.settings.data.settings.hasSignatureFile,
		}),
	},
	methods: {
		clearInput() {
			this.$refs.input.clearInput()
		},
		sign(param) {
			this.$emit('sign:document', param)
		},
		handleModal(state) {
			this.modal = state
		},
	},
}
</script>

<style lang="scss">
@import './styles';
</style>
