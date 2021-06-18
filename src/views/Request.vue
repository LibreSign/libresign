<template>
	<Content app-name="libresign">
		<div class="conntainer-request">
			<header>
				<h1>{{ t('libresign', 'Choose the file to request signatures.') }}</h1>
			</header>
			<div class="content-request">
				<div>
					<button class="icon-folder" @click="getFile()">
						{{ t('libresign', 'Share fsrom files') }}
					</button>
					<button @click="log">
						log
					</button>
				</div>
			</div>
		</div>
	</Content>
</template>
<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { getFilePickerBuilder } from '@nextcloud/dialogs'

const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
	.setMultiSelect(false)
	.setMimeTypeFilter('application/pdf')
	.setModal(false)
	.setType(1)
	.allowDirectories(false)
	.build()

export default {
	name: 'Request',
	components: {
		Content,
	},
	data() {
		return {
			loading: false,
			file: {},
		}
	},
	methods: {
		handleUploadFile(event) {
			const files = event.target.files ?? []
			for (const file of files) {
				this.onLocalAttachmentSelected(file, 'file')
			}
			event.target.value = ''
		},
		log() {
			console.info(this.file)
		},
		getFile() {
			picker.pick()
				.then(path => {
					OC.dialogs.filelist.forEach(file => {
						if (file.name === path.split('/')[1]) {
							this.file = file
						}
					})
				})

		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	content: ''
}
button{
	background-position-x: 8%;
	padding-left: 35px;
}
</style>
