<template>
	<div class="container-timeline">
		<ul>
			<File
				v-for="file in files"
				:key="file.uuid"
				class="file-details"
				:status="file.status"
				:file="file"
				@sidebar="setSidebar" />
		</ul>
		<Sidebar v-if="sidebar"
			ref="sidebar"
			@sign:document="signDocument"
			@closeSidebar="closeSidebar" />
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
			data: [
				{
					uuid: '3fa85f6s4-5717-4562-b3fc-x2c963f66afa6',
					name: 'filename',
					callback: 'http://app.test.coop/callback_webhook',
					status: 'done',
					status_date: '2021-06-11T22:18:59.872Z',
					request_date: '2021-06-11T22:18:59.872Z',
					requested_by: {
						display_name: 'John Doe',
						uid: 'johndoe',
					},
					file: {
						type: 'pdf',
						url: 'http://cloud.test.coop/apps/libresign/pdf/46d30465-ae11-484b-aad5-327249a1e8ef',
						nodeId: 2312,
					},
					signers: [
						{
							email: 'user@test.coop',
							display_name: 'John Dddoe',
							me: true,
							uid: 'johndoe',
							description: "As the company's CEO, you must sign this contract",
							sign_date: '',
							request_sign_date: '2021-06-11T22:18:59.873Z',
						},
						{
							email: 'user@test.coop',
							display_name: 'John Doe',
							me: false,
							uid: 'johndoe2',
							description: "As the company's CEO, you must sign this contract",
							sign_date: '2021-06-11T22:18:59.872Z',
							request_sign_date: '2021-06-11T22:18:59.873Z',
						},
					],
				},
			],
		}
	},
	computed: {
		files() {
			const files = this.data.map(file => {
				return {
					uuid: file.uuid,
					name: file.name,
					status: file.status,
					status_date: file.status_date,
					signers: file.signers,
				}
			})
			return files
		},
	},

	methods: {
		openSidebar() {
			this.sidebar = true
		},
		setSidebar(objectFile) {
			this.closeSidebar()

			this.$store.commit('setCurrentFile', objectFile)
			this.openSidebar()
		},
		closeSidebar() {
			this.sidebar = false
		},
		signDocument(param) {
			console.info('Sign Function')
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
