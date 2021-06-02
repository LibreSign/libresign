<template>
	<AppSidebar v-if="currentFile"
		title="teste"
		:active="tabId"
		@close="closeSidebar">
		<template #secondary-actions>
			<ActionButton>
				ok
			</ActionButton>
		</template>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
// import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { mapGetters, mapState } from 'vuex'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
		ActionButton,
		// AppSidebarTab,
	},
	props: {
		tabId: {
			type: String,
			required: false,
			default: null,
		},
	},
	computed: {
		...mapState({
			currentFile: state => state.currentFile,
			sidebar: state => state.sidebar,
		}),
		...mapGetters(['getCurrentFile', 'getSidebar']),
	},
	methods: {
		closeSidebar() {
			this.$emit('closeSidebar', true)
		},
	},
}
</script>
