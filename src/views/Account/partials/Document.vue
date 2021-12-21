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
				<div class="icon icon-upload" @click="uploadFile" />
				<div class="icon icon-folder" @click="getFile" />
			</template>
			<template v-else>
				<div class="icon icon-delete" @click="deleteFile" />
			</template>
		</div>
	</div>
</template>

<script>
// import { mapMutations } from 'vuex'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
export default {
	name: 'Document',
	components: {},
	props: {
		item: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
			modal: false,
			canSendDocument: false
		}
	},
	computed: {
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
		// canSendDocument() {
		// return !['approved', 'approval'].includes(this.item.status)
		// },
	},
	watch: {
		item: {
			immediate: true,
			deep: true,
			handler(value) {
				this.canSendDocument = !['approved', 'approval'].includes(value.status)
			}
		}
	},
	methods: {
		// ...mapMutations({  }),
		deleteFile() {
			this.canSendDocument = true
			this.$emit('deleteFile', this.item)
		},
		getFile() {
			const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
				.setMimeTypeFilter('images/*')
				.setModal(true)
				.setType(1)
				.allowDirectories()
				.build()

			return picker.pick()
				.then(path => {
					OC.dialogs.filelist.forEach(async file => {
						const indice = path.split('/').indexOf(file.name)
						if (path.startsWith('/')) {
							if (file.name === path.split('/')[indice]) {
								// this.file = file
								// this.setSidebarStatus(true)
								// await this.validateFile(file.id)
								this.canSendDocument = false
								this.$emit('file', file)
							}
						}
					})
				})
		},
		uploadFile() {
			const input = document.createElement('input')
			input.type = 'file'

			input.onchange = e => {

				// getting a hold of the file reference
				const file = e.target.files[0]
				console.log('FILE', file)
				this.canSendDocument = false

				input.remove()

			}

			input.click()
		}
	}
}
</script>

<style lang="scss" scoped>
.document-item-container {
	display: grid;
	grid-template-columns: 1fr auto 1fr ;
	gap: 30px;

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
		}
	}
}
</style>
