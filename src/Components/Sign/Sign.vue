<template>
	<div class="container">
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
		<a :href="linkForgot" target="_blank" class="forgot">
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
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import InputAction from '../InputAction'
import Icon from '../../assets/images/signed-icon.svg'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Sign',
	components: {
		Avatar,
		InputAction,
		EmptyContent,
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
		}
	},
	computed: {
		userName() {
			return getCurrentUser().uid
		},
		linkForgot() {
			return generateUrl('/apps/libresign/reset-password')
		},
	},
	methods: {
		clearInput() {
			this.$refs.input.clearInput()
		},
		sign(param) {
			this.$emit('sign:document', param)
		},
	},
}
</script>

<style lang="scss" scoped>
@import './styles';
</style>
