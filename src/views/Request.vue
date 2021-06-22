<template>
	<Content app-name="libresign">
		<div class="conntainer-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<p>{{ t('libresign', 'Choose the file to request signatures.') }}</p>
			</header>
			<div class="content-request">
				<div>
					<button class="icon-folder" @click="getFile()">
						{{ t('libresign', 'Share from files') }}
					</button>
					<button @click="userAdd">
						{{ t('libresign', 'Add Users') }}
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
		userAdd() {
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
.conntainer-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 100%;
	max-width: 100%;
	text-align: center;
	header {
		margin-bottom: 2.5rem;
		h1 {
			font-size: 45px;
			margin-bottom: 1rem;
		}

		p {
			font-size: 15px;
		}
	}
	.content-request{
		display: flex;
		flex-direction: column;
	}
}

button {
	background-position-x: 8%;
	padding-left: 35px;
}
</style>
