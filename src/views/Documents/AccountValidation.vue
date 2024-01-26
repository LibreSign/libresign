<template>
	<ProgressBar v-if="loading" infinity />
	<div v-else class="is-fullwidth container-account-docs-to-validate with-sidebar--full">
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
</template>

<script>
import { onError } from '../../helpers/errors.js'
import ProgressBar from '../../Components/ProgressBar.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'AccountValidation',
	components: {
		ProgressBar,
	},
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
				const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/files/approval/list'))
				this.documentList = data.map(entry => {
					return {
						uuid: entry.uuid,
						nodeId: entry.file.nodeId,
						file_type: entry.file_type,
						name: entry.name,
						status: entry.status,
						status_text: entry.status_text,
					}
				})
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
