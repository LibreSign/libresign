<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { documentsService, parseDocument } from '../../domains/documents'
import { onError } from '../../helpers/errors'
import ProgressBar from '../../Components/ProgressBar.vue'
import { map } from 'lodash-es'

export default {
	name: 'AccountValidation',
	components: { Content, ProgressBar },
	data: () => ({
		documentList: [],
		loading: true,
	}),
	mounted() {
		this.loadDocuments()
	},
	methods: {
		async loadDocuments() {
			this.loading = true

			try {
				const { data } = await documentsService.loadApprovalList()
				this.documentList = map(data, parseDocument)
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<template>
	<Content class="container-account" app-name="libresign">
		<!-- <pre>{{ documentList }}</pre> -->
		<ProgressBar v-if="loading" infinity />

		<table v-else class="libre-table is-fullwidth">
			<thead>
				<tr>
					<td>
						{{ t('libresign', 'Type') }}
					</td>
					<td>
						{{ t('libresign', 'Status') }}
					</td>
					<td>
						{{ t('libresign', 'Actions') }}
					</td>
				</tr>
			</thead>
			<tr v-for="(doc, index) in documentList" :key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`">
				<td>
					{{ doc.file_type.name }}
				</td>
				<td>
					{{ doc.status_text }}
				</td>
				<td class="actions">
					--
				</td>
			</tr>
		</table>
	</Content>
</template>
