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
			:disabled="disabled"
			:loading="hasLoading"
			@submit="sign" />
		<a class="forgot-sign" @click="handleModal(true)">
			{{ t('libresign', 'Forgot your password?') }}
		</a>
		<EmptyContent class="emp-content">
			<template #desc>
				<p>
					{{ t('libresign', 'Enter your password to sign this document') }}
				</p>
			</template>
			<template #icon>
				<img :src="icon">
			</template>
		</EmptyContent>
		<slot name="actions" />
		<Modal v-if="modal" size="large" @close="handleModal(false)">
			<ResetPassword @close="handleModal(false)" />
		</Modal>
	</div>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../views/ResetPassword.vue'
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
