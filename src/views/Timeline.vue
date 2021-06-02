<template>
	<div class="container-timeline">
		<ul>
			<File
				v-for="file in files"
				:key="file.id"
				class="file-details"
				:status="file.status"
				:name="file.name"
				@sidebar="setSidebar"
				@click.prevent="setSidebar" />
		</ul>
		<Sidebar v-show="sidebar" @closeSidebar="closeSidebar" />
	</div>
</template>

<script>
import File from '../Components/File'
import Sidebar from '../Components/File/Sidebar.vue'

export default {
	name: 'Timeline',
	components: {
		File,
		Sidebar,
	},
	data() {
		return {
			sidebar: false,
		}
	},
	computed: {
		files() {
			const items = [{
				id: 1,
				name: 'teste',
				status: 'done',
			}, {
				id: 2,
				name: 'teste2',
				status: 'pending',
			}, {
				id: 3,
				name: 'teste3',
				status: 'canceled',
			}]
			return items
		},
	},
	methods: {
		setSidebar() {
			this.sidebar = !this.sidebar
		},
		closeSidebar() {
			this.sidebar = false
		},
	},
}
</script>
<style lang="scss" scoped>
.container-timeline{
	margin: 30px;
	display: flex;
	width: 100%;
	flex-direction: row;

	ul{
		display: flex;
		width: 100%;
	}

	.file-details:hover {
		background: darken(#fff, 10%);
		border-radius: 10px;
	}
}
</style>
