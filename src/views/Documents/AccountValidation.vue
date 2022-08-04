<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { documentsService, parseDocument } from '../../domains/documents/index.js'
import { onError } from '../../helpers/errors.js'
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

		openApprove(doc) {
			const route = this.$router.resolve({ name: 'AccountFileApprove', params: { uuid: doc.uuid } })
			const url = new URL(window.location.toString())

			url.pathname = route.href

			window.open(url.toString())
		},
	},
}
</script>

<template>
	<Content class="container-account-docs-to-validate" app-name="libresign">
		<!-- <pre>{{ documentList }}</pre> -->
		<ProgressBar v-if="loading" infinity />

		<div v-else class="is-fullwidth">
			<table class="libre-table is-fullwidth">
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
				<tbody>
					<tr v-for="(doc, index) in documentList" :key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`">
						<td>
							{{ doc.file_type.name }}
						</td>
						<td>
							{{ doc.status_text }}
						</td>
						<td class="actions">
							<button @click="openApprove(doc)">
								{{ t('libresign', 'Validate') }}
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</Content>
</template>
