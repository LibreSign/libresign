<!-- eslint-disable vue/no-v-html -->

<template>
	<div class="container">
		<div id="image">
			<NcAvatar id="avatar"
				:is-guest="true"
				:show-user-status="false"
				:display-name="user.displayName"
				:user="user.email" />
		</div>
		<div id="list-content" class="with-sidebar--full">
			<p class="title">
				{{ user.displayName ? user.displayName : user.email }}
			</p>
			<span class="description" v-html="markedDescription" />
		</div>
		<div v-if="hasOptions" id="options">
			<NcActions>
				<NcActionButton icon="icon-delete" @click="removeUser(user)" />
			</NcActions>
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar'
import NcActions from '@nextcloud/vue/dist/Components/NcActions'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

export default {
	name: 'ListItem',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
	},
	props: {
		user: {
			type: Object,
			require: true,
			default: null,
		},
		description: {
			type: String,
			require: false,
			default: null,
		},
		hasOptions: {
			type: Boolean,
			default: true,
			require: false,
		},
	},
	computed: {
		markedDescription() {
			return DOMPurify.sanitize(marked(this.description))
		},
	},
	methods: {
		removeUser(user) {
			this.$emit('remove-user', user)
		},
	},
}
</script>
<style lang="scss" scoped>
@import './styles';
</style>
