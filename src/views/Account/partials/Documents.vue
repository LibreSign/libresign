<script>
import { documentsService } from '../../../domains/documents'
import { getFilePickerBuilder, showWarning } from '@nextcloud/dialogs'
import { find } from 'lodash-es'
import { pathJoin } from '../../../helpers/path'

const findDocumentByType = (list, type) => { // TODO: fix contract
	return find(list, row => row.type === type) || {
		id: 0,
		name: 'missing',
		type,
	}
}

export default {
	name: 'Documents',
	components: {
		// Btn,
	},
	data() {
		return {
			documentList: [],
		}
	},
	computed: {
		documents() {
			return {
				default: findDocumentByType(this.documentList, 'default'),
			}
		},
		list() {
			return Object.values(this.documents)
		},
	},
	mounted() {
		// documentsService.loadAccountList()
	},
	methods: {
		async pickFile(fileType) {
			try {
				const fileFullName = await getFilePickerBuilder(t('libresign', 'Select a file'))
					.setMultiSelect(false)
					.allowDirectories(false)
					.setModal(true)
					.setType(1) // FilePickerType.Choose
					.setMimeTypeFilter(['application/pdf'])
					.build()
					.pick()

				const file = OC.dialogs.filelist.find(entry => {
					const fullName = pathJoin(entry.path, entry.name)
					return fullName === fileFullName
				})

				if (!file) {
					showWarning(t('libresign', 'Impossible to get file entry'))
					return
				}

				const res = await documentsService.addAcountFile({
					name: file.name,
					type: fileType,
					file: {
						fileId: file.id,
					},
				})

				console.log({ res })

			} catch (err) {
				console.error(err)
			}
		},
	},
}
</script>

<template>
	<div class="documents">
		<h1>{{ t('libresign', 'Your profile documents') }}</h1>

		<table class="libre-table is-fullwidth">
			<thead>
				<tr>
					<td>
						{{ t('libresign', 'type') }}
					</td>
					<td>
						{{ t('libresign', 'status') }}
					</td>
					<td>
						{{ t('libresign', 'actions') }}
					</td>
				</tr>
			</thead>
			<tbody>
				<tr v-for="doc in list" :key="`doc-${doc.type}`">
					<td>
						{{ doc.type }}
					</td>
					<td>
						{{ doc.name }}
					</td>
					<td class="actions">
						<button @click="pickFile(doc.type)">
							<div class="icon-file" />
						</button>
						<button @click="pickFile">
							<div class="icon-upload" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style lang="scss" scoped>
.documents {
	align-items: flex-start;
	width: 100%;

	table td {
		vertical-align: middle;
	}

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
		display: block;
	}

	td.actions button {
		padding: 3px 8px;
		margin-top: 0;
		margin-bottom: 0;
	}
}
</style>
