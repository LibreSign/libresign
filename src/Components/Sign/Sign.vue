<template>
	<div class="container">
		<div class="avatar-local">
			<Avatar id="avatar" :user="userName" />
			<span>{{ userName }}</span>
		</div>

		<InputLS class="input"
			:type="'password'"
			:disabled="disabled"
			@submit="sign" />

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
import InputLS from '../InputLS'
import Icon from '../../assets/images/signed-icon.svg'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Sign',
	components: {
		Avatar,
		InputLS,
		EmptyContent,
	},
	props: {
		disabled: {
			type: Boolean,
			require: false,
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
	},
	methods: {
		sign(param) {
			this.$emit('sign:document', param)
		},
	},
}
</script>

<style lang="scss" scoped>
@import './styles';
</style>
