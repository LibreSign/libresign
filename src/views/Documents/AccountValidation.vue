<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
						{{ doc.statusText }}
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
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import ProgressBar from '../../Components/ProgressBar.vue'

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
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/files/approval/list'))
				.then(({ data }) => {
					this.documentList = data.ocs.data.data
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
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
