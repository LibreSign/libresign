<template>
	<div class="container-timeline">
		<ul>
			<File
				v-for="file in files"
				:key="file.id"
				class="file-details"
				:status="file.status"
				:file="file"
				@sidebar="setSidebar"
				@click="setSidebar(file)" />
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
			}, {
				id: 4,
				name: 'teste4',
				status: 'canceled',
			}, {
				id: 5,
				name: 'teste5',
				status: 'canceled',
			}, {
				id: 6,
				name: 'teste6',
				status: 'canceled',
			}, {
				id: 8,
				name: 'teste8',
				status: 'canceled',
			}, {
				id: 7,
				name: 'teste7',
				status: 'canceled',
			}, {
				id: 9,
				name: 'teste9',
				status: 'canceled',
			}, {
				id: 10,
				name: 'teste10',
				status: 'canceled',
			}, {
				id: 11,
				name: 'teste11',
				status: 'canceled',
			}, {
				id: 12,
				name: 'teste12',
				status: 'canceled',
			}, {
				id: 13,
				name: 'teste13',
				status: 'canceled',
			}, {
				id: 14,
				name: 'teste14',
				status: 'canceled',
			}]
			return items
		},
	},

	methods: {
		openSidebar() {
			this.sidebar = true
		},
		setSidebar(objectFile) {
			this.openSidebar()
			this.$store.commit('setCurrentFile', objectFile)
		},
		closeSidebar() {
			this.sidebar = false
		},
	},
}
</script>
<style lang="scss" scoped>
.container-timeline{
	display: flex;
	width: 100%;
	flex-direction: row;

	ul{
		display: flex;
		width: 100%;
		flex-wrap: wrap;
	}

	.file-details:hover {
		background: darken(#fff, 10%);
		border-radius: 10px;
	}
}
</style>
