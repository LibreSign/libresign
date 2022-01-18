<template>
	<div class="document-item-container">
		<div class="item-name">
			{{ name }}
		</div>
		<div class="item-status">
			{{ status }}
		</div>
		<div class="item-icons">
			<template v-if="canSendDocument">
				<button class="icon icon-upload" @click="openModal('upload')" />
				<button class="icon icon-folder" @click="getFile" />
			</template>
			<template v-else>
				<button class="icon icon-delete" @click="deleteFile" />
			</template>
		</div>
		<Modal v-if="modal" @close="modal = false">
			<div class="modal-file">
				<h1>{{ t('libresign', 'Select file type') }}</h1>
				<div class="modal-content">
					<div class="select-container">
						<select v-model="file_type" @change="changeFileType">
							<option
								value=""
								disabled
								selected
								hidden>
								{{ t('libresign', 'File type') }}
							</option>
							<option v-for="type_item in file_types" :key="type_item.type">
								{{ type_item.name }}
							</option>
						</select>
						<button v-if="file_type && origin === 'upload'" class="icon icon-upload" @click="uploadFile" />
						<button v-else-if="file_type && origin === 'file'" class="icon icon-folder" @click="getFile" />
					</div>
					<div v-if="file.name" class="file-info">
						{{ file.name }}
					</div>
				</div>
			</div>
			<div class="actions-modal">
				<button class="primary" @click="saveFile">
					{{ t('libresign', 'Save') }}
				</button>
				<button @click="cancel">
					{{ t('libresign', 'Cancel') }}
				</button>
			</div>
		</Modal>
	</div>
</template>

<script>
import { mapActions } from 'vuex'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import Modal from '@nextcloud/vue/dist/Components/Modal'
export default {
	name: 'Document',
	components: {
		Modal,
	},
	props: {
		item: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
			file: {},
			file_type: '',
			file_types: [
				{
					name: 'JPEG', type: 'jpeg',
				},
				{
					name: 'PNG', type: 'png',
				},
				{
					name: 'PDF', type: 'pdf',
				},
			],
			modal: false,
			origin: '',
		}
	},
	computed: {
		// ...mapGetters({ file_types: 'documents/fileTypes' }),
		name() {
			return this.item.name
		},
		status() {
			switch (this.item.status) {
			case 'approval':
				return this.t('libresign', 'Waiting approval')
			case 'approved':
				return this.t('libresign', 'Approved')
			case 'reproved':
				return this.t('libresign', 'Reproved')
			default:
				return this.t('libresign', 'Not found')
			}
		},
		canSendDocument() {
			return !['approved', 'approval'].includes(this.item.status)
		},
	},
	watch: {},
	methods: {
		...mapActions({ saveDocument: 'documents/save', deleteDocument: 'documents/remove', downloadFile: 'documents/download' }),
		// ...mapMutations({  }),
		toBase64: (file) => new Promise((resolve, reject) => {
			const reader = new FileReader()
			reader.readAsDataURL(file)
			reader.onload = () => resolve(reader.result)
			reader.onerror = (error) => reject(error)
		}),
		cancel() {
			this.modal = false
			this.file = {}
			this.file_type = ''
			this.origin = ''
		},
		changeFileType() {
			this.file = {}
			this.uploadFile()
		},
		async deleteFile() {
			const result = await this.deleteDocument({ id: this.item.id })
			if (result.success) this.$emit('delete', { item: this.item })
		},
		getFile() {
			const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
				// .setMimeTypeFilter(`.${this.file_type}`)
				.setModal(true)
				.setType(1)
				.allowDirectories(false)
				.build()

			return picker.pick()
				.then(path => {
					OC.dialogs.filelist.forEach(async file => {
						const indice = path.split('/').indexOf(file.name)
						if (path.startsWith('/')) {
							if (file.name === path.split('/')[indice]) {
								const form = {
									name: file.name,
									// type: file.mimetype,
									type: this.item.name,
									file: {
										...file,
									},
								}
								this.saveDocument({ form }).then(result => {
									if (result.success) this.$emit('save', { item: this.item })
								})
							}
						}
					})
				})
		},
		async saveFile() {
			if (this.file.name && this.file_type) {
				const form = {
					file: {
						url: await this.toBase64(this.file),
					},
					name: this.file.name,
					type: this.item.name,
					// type: this.file_type,
				}
				const result = await this.saveDocument({ form })
				if (result.success) this.$emit('save', { item: this.item })
			}
		},
		openModal(origin) {
			this.modal = true
			this.origin = origin
		},
		uploadFile() {
			const input = document.createElement('input')
			input.type = 'file'
			input.accept = `.${this.file_type}`

			input.onchange = e => {
				// getting a hold of the file reference
				const file = e.target.files[0]
				this.file = file

				input.remove()
			}
			input.click()
		},
	},
}
</script>

<style lang="scss" scoped>
.document-item-container {
	display: grid;
	grid-template-columns: 1fr auto 1fr ;
	gap: 30px;
	padding: 5px;

	// .item-status {
	// grid-column-start: 2;
	// grid-column-end: 4;
	// }

	.item-icons {
		display: flex;
		justify-content: flex-end;
		// grid-column-start: 4;

		.icon {
			margin: 0px 3px ;
			cursor: pointer;
			padding: 4px 12px;
			min-height: initial;
		}
	}
}

.select-container {
	display: flex;

	select {
		flex-grow: 1;
	}
}

.file-info {
	color: #bdbdbd;
	font-style: italic;
	font-size: 12px;
}

.modal-file {
	width: 380px;
	padding: 12px 18px 4px;
}

.modal-content {
	padding: 12px 0px
}

.actions-modal {
	display: flex;
	justify-content: flex-end;
	padding: 4px 18px;
}

</style>
