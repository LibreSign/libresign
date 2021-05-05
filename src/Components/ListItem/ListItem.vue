<template>
	<div class="container">
		<div id="image">
			<Avatar id="avatar" :user="user.email" />
		</div>
		<div id="content">
			<p class="title">
				{{ user.name ? user.name : user.email }}
			</p>
			<span class="description"> {{ description }}</span>
		</div>
		<div id="options">
			<Actions>
				<ActionButton icon="icon-delete" @click="removeUser(user)">
					{{ t('libresign', 'Delete user.') }}
				</ActionButton>
			</Actions>
		</div>
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'ListItem',
	components: {
		Avatar,
		Actions,
		ActionButton,
	},
	props: {
		user: {
			type: String,
			require: true,
			default: null,
		},
		description: {
			type: String,
			require: false,
			default: null,
		},
	},
	methods: {
		removeUser(user) {
			this.$emit('remove-user', user)
			// eslint-disable-next-line no-console
			console.log(user)
		},
	},
}
</script>
<style lang="scss" scoped>
	.container{
		width: 93%;
		height: 100%;
		display: flex;
		justify-content: space-between;
		align-items: center;
		flex-direction: row;

		#content{
			display: flex;
			flex-direction: column;
			max-width: 240px;
			align-items: flex-start;
			max-height: 70px;
			padding-top: 0;
			padding-left: 10px;
			overflow: hidden;
			text-overflow: ellipsis;
			text-align: start;
			color: #000;

			.title{
				font-weight: bold;
			}
			.description{
				height: 10vh;
				color: lighten(#000000, 10%)
			}
		}

	}
</style>
