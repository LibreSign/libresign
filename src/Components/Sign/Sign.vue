<template>
	<div class="container">
		<div class="avatar-local">
			<Avatar id="avatar" :user="userName" />
			<span>{{ userName }}</span>
		</div>

		<InputLS class="input"
			:type="'password'"
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
			this.$emit('sign:pdf', param)
		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: column;

	.avatar-local{
		display: flex;
		flex-direction: row;
		align-self: flex-start;
		margin: 10px 0;

		span{
			margin: 0 10px;
		}
	}

	.input{
		margin-left: 40px;
	}
	.emp-content{
		margin-top: 10vh !important;

		p{
			opacity: .6;
		}

		img{
			width: 400px;
		}
	}
}
</style>
