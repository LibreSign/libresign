<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcLoadingIcon v-if="loading" :size="44" />

	<NcEmptyContent v-else-if="documentList.length === 0"
		:name="t('libresign', 'No documents to validate')">
		<template #icon>
			<FileDocumentIcon :size="64" />
		</template>
	</NcEmptyContent>

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
						<NcActions :force-name="true" :inline="3">
							<NcActionButton @click="openValidationURL(doc)">
								<template #icon>
									<EyeIcon :size="20" />
								</template>
								{{ t('libresign', 'View') }}
							</NcActionButton>
							<NcActionButton v-if="doc.status !== 4" @click="openApprove(doc)">
								<template #icon>
									<PencilIcon :size="20" />
								</template>
								{{ t('libresign', 'Sign') }}
							</NcActionButton>
							<NcActionButton @click="deleteDocument(doc)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('libresign', 'Delete') }}
							</NcActionButton>
						</NcActions>
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
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'IdDocsValidation',
	components: {
		DeleteIcon,
		EyeIcon,
		FileDocumentIcon,
		NcActions,
		NcActionButton,
		NcEmptyContent,
		NcLoadingIcon,
		PencilIcon,
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
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/id-docs/approval/list'))
				.then(({ data }) => {
					this.documentList = data.ocs.data.data
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},

		openApprove(doc) {
			const uuid = doc.file?.uuid || doc.uuid
			if (!uuid) {
				showError(this.t('libresign', 'Document UUID not found'))
				return
			}
			this.$router.push({
				name: 'IdDocsApprove',
				params: { uuid },
			})
		},

		async deleteDocument(doc) {
			try {
				await axios.delete(generateOcsUrl('/apps/libresign/api/v1/id-docs/{nodeId}', { nodeId: doc.nodeId }))
				await this.loadDocuments()
			} catch (error) {
				showError(error.response?.data?.ocs?.data?.message || this.t('libresign', 'Failed to delete document'))
			}
		},

		openValidationURL(doc) {
			const uuid = doc.file?.uuid || doc.uuid
			if (!uuid) {
				showError(this.t('libresign', 'Document UUID not found'))
				return
			}
			this.$router.push({
				name: 'ValidationFile',
				params: { uuid },
			})
		},
	},
}
</script>
