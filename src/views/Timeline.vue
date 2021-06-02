<template>
	<div class="container-timeline">
		<ul>
			<File
				v-for="file in files"
				:key="file.id"
				:status="file.status"
				:name="file.name" />
		</ul>
	</div>
</template>

<script>
import File from '../Components/File'

export default {
	name: 'Timeline',
	components: {
		File,
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

	created() {
		console.info('files', this.files)
		console.info(this.isSign)
	},

}
</script>
<style lang="scss" scoped>
.container-timeline{
	margin: 30px;
	display: flex;
	flex-direction: row;

	ul{
		display: flex;
		width: 100%;
	}
}
</style>
