<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="id-docs-validation">
		<div class="id-docs-validation__toolbar">
			<div class="filter-wrapper" :class="{ 'filter-wrapper--active': hasActiveFilters }">
				<NcActions :aria-label="hasActiveFilters ? t('libresign', 'Filters ({count})', { count: activeFilterCount }) : t('libresign', 'Filters')">
					<template #icon>
						<FilterIcon :size="20" />
					</template>
					<NcActionInput v-model="filters.owner"
						:label="t('libresign', 'Owner')"
						@update:value="onFilterChange">
						<template #icon>
							<AccountIcon :size="20" />
						</template>
					</NcActionInput>

					<NcActionButton type="radio"
						:model-value="filters.status?.value === 'signed'"
						@update:modelValue="setStatusFilter('signed', $event)">
						<template #icon>
							<CheckCircleIcon :size="20" />
						</template>
						{{ t('libresign', 'Signed') }}
					</NcActionButton>

					<NcActionButton type="radio"
						:model-value="filters.status?.value === 'pending'"
						@update:modelValue="setStatusFilter('pending', $event)">
						<template #icon>
							<ClockAlertIcon :size="20" />
						</template>
						{{ t('libresign', 'Pending') }}
					</NcActionButton>

					<NcActionSeparator v-if="hasActiveFilters" />

					<NcActionButton v-if="hasActiveFilters"
						@click="clearFilters">
						<template #icon>
							<CloseIcon :size="20" />
						</template>
						{{ t('libresign', 'Clear filters') }}
					</NcActionButton>
				</NcActions>
				<span v-if="hasActiveFilters" class="filter-badge" aria-hidden="true">{{ activeFilterCount }}</span>
			</div>
		</div>

		<NcLoadingIcon v-if="loading" :size="44" />

		<NcEmptyContent v-else-if="filteredDocuments.length === 0"
			:name="t('libresign', 'No documents to validate')">
			<template #icon>
				<FileDocumentIcon :size="64" />
			</template>
		</NcEmptyContent>

		<div v-else
			ref="scrollContainer"
			class="is-fullwidth container-account-docs-to-validate with-sidebar--full"
			@scroll="onScroll">
		<table class="id-docs-table">
			<thead>
				<tr>
					<th class="id-docs-table__cell--spacer id-docs-table__cell--frozen-left id-docs-table__cell--frozen-spacer" />
					<th class="sortable id-docs-table__cell--frozen-left id-docs-table__cell--frozen-owner" @click="sortColumn('owner')">
						{{ t('libresign', 'Owner') }}
						<span v-if="sortBy === 'owner'" class="sort-indicator">
							{{ sortOrder === 'ASC' ? '▲' : '▼' }}
						</span>
					</th>
					<th class="sortable" @click="sortColumn('file_type')">
						{{ t('libresign', 'Type') }}
						<span v-if="sortBy === 'file_type'" class="sort-indicator">
							{{ sortOrder === 'ASC' ? '▲' : '▼' }}
						</span>
					</th>
					<th class="sortable" @click="sortColumn('status')">
						{{ t('libresign', 'Status') }}
						<span v-if="sortBy === 'status'" class="sort-indicator">
							{{ sortOrder === 'ASC' ? '▲' : '▼' }}
						</span>
					</th>
					<th>{{ t('libresign', 'Approved by') }}</th>
					<th class="id-docs-table__cell--frozen-right">{{ t('libresign', 'Actions') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(doc, index) in filteredDocuments" :key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`">
					<td class="id-docs-table__cell--spacer id-docs-table__cell--frozen-left id-docs-table__cell--frozen-spacer">
						<NcAvatar :user="doc.account?.uid"
							:display-name="doc.account?.display_name || doc.account?.uid"
							:size="32"
							:disable-menu="true" />
					</td>
					<td class="id-docs-table__cell--frozen-left id-docs-table__cell--frozen-owner">
						{{ doc.account?.display_name || doc.account?.uid || '-' }}
					</td>
					<td>
						{{ doc.file_type.name }}
					</td>
					<td>
						{{ doc.statusText }}
					</td>
					<td>
						<template v-if="doc.file?.signers?.length > 0 && doc.file.signers[0].sign_date">
							<NcAvatar :user="doc.account?.uid"
								:display-name="doc.file.signers[0].displayName"
								:disable-menu="true" />
							{{ doc.file.signers[0].displayName }}
						</template>
						<template v-else>
							-
						</template>
					</td>
					<td class="id-docs-table__cell--frozen-right">
						<NcActions :force-name="true" :inline="4">
							<NcActionButton @click="openFile(doc)">
								<template #icon>
									<FileDocumentOutlineIcon :size="20" />
								</template>
								{{ t('libresign', 'Open file') }}
							</NcActionButton>
							<NcActionButton @click="openValidationURL(doc)">
								<template #icon>
									<EyeIcon :size="20" />
								</template>
								{{ t('libresign', 'View') }}
							</NcActionButton>
							<NcActionButton v-if="doc.file?.status !== 3" @click="openApprove(doc)">
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

		<div v-if="loadingMore" class="id-docs-validation__loading-more">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-if="!hasMore && documentList.length > 0" class="id-docs-validation__end">
			{{ t('libresign', 'No more entries to load') }}
		</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import { useUserConfigStore } from '../../store/userconfig.js'

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import AccountIcon from 'vue-material-design-icons/Account.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import ClockAlertIcon from 'vue-material-design-icons/ClockAlert.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'IdDocsValidation',
	components: {
		AccountIcon,
		CheckCircleIcon,
		ClockAlertIcon,
		CloseIcon,
		DeleteIcon,
		FileDocumentOutlineIcon,
		EyeIcon,
		FileDocumentIcon,
		FilterIcon,
		NcActions,
		NcActionButton,
		NcActionInput,
		NcActionSeparator,
		NcAvatar,
		NcEmptyContent,
		NcLoadingIcon,
		PencilIcon,
	},
	data() {
		const userConfigStore = useUserConfigStore()

		return {
			userConfigStore,
			documentList: [],
			loading: true,
			loadingMore: false,
			page: 1,
			length: 50,
			total: 0,
			hasMore: true,
			sortBy: userConfigStore.id_docs_sort?.sortBy || null,
			sortOrder: userConfigStore.id_docs_sort?.sortOrder || null,
			filters: {
				owner: userConfigStore.id_docs_filters?.owner || '',
				status: userConfigStore.id_docs_filters?.status || null,
			},
			statusOptions: [
				{ value: 'signed', label: 'Signed' },
				{ value: 'pending', label: 'Pending' },
			],
		}
	},
	computed: {
		hasActiveFilters() {
			return !!(this.filters.owner || this.filters.status)
		},
		activeFilterCount() {
			let count = 0
			if (this.filters.owner) count++
			if (this.filters.status) count++
			return count
		},
		filteredDocuments() {
			let docs = [...this.documentList]

			if (this.filters.owner) {
				const ownerLower = this.filters.owner.toLowerCase()
				docs = docs.filter(doc => {
					const displayName = doc.account?.display_name || doc.account?.uid || ''
					return displayName.toLowerCase().includes(ownerLower)
				})
			}

			if (this.filters.status?.value === 'signed') {
				docs = docs.filter(doc => doc.file?.status === 3)
			} else if (this.filters.status?.value === 'pending') {
				docs = docs.filter(doc => doc.file?.status !== 3)
			}

			return docs
		},
	},
	mounted() {
		this.loadDocuments()
	},
	methods: {
		async loadDocuments(append = false) {
			if (!append) {
				this.loading = true
				this.page = 1
				this.documentList = []
			} else {
				this.loadingMore = true
			}

			try {
				const params = {
					page: this.page,
					length: this.length,
				}

				if (this.sortBy) {
					params.sortBy = this.sortBy
					params.sortOrder = this.sortOrder
				}

				const response = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/id-docs/approval/list'),
					{ params }
				)

				const data = response.data.ocs.data

				if (append) {
					this.documentList.push(...data.data)
				} else {
					this.documentList = data.data
				}

				this.total = data.total || data.data.length
				this.hasMore = this.documentList.length < this.total
			} catch (error) {
				showError(error.response?.data?.ocs?.data?.message || this.t('libresign', 'Failed to load documents'))
			} finally {
				this.loading = false
				this.loadingMore = false
			}
		},

		onScroll(event) {
			if (this.loadingMore || !this.hasMore) {
				return
			}

			const container = event.target
			const scrollPosition = container.scrollTop + container.clientHeight
			const scrollHeight = container.scrollHeight

			if (scrollPosition >= scrollHeight * 0.8) {
				this.page++
				this.loadDocuments(true)
			}
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

		openFile(doc) {
			const fileUrl = doc.file?.file?.url

			if (!fileUrl) {
				showError(t('libresign', 'File not found'))
				return
			}

			if (OCA?.Viewer !== undefined) {
				const fileInfo = {
					source: fileUrl,
					basename: doc.file.name,
					mime: 'application/pdf',
					fileid: doc.nodeId,
				}
				OCA.Viewer.open({
					fileInfo,
					list: [fileInfo],
				})
			} else {
				window.open(`${fileUrl}?_t=${Date.now()}`)
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

		onFilterChange() {
			clearTimeout(this.filterTimeout)
			this.filterTimeout = setTimeout(() => {
				this.saveFilters()
			}, 500)
		},

		async saveFilters() {
			try {
				const filters = {
					owner: this.filters.owner,
					status: this.filters.status,
				}
				console.log('Saving id_docs_filters:', filters)
				await this.userConfigStore.update('id_docs_filters', filters)
				console.log('Filters saved successfully')
			} catch (error) {
				console.error('Failed to save filters:', error)
			}
		},

		clearFilters() {
			this.filters.owner = ''
			this.filters.status = null
			this.saveFilters()
		},

		setStatusFilter(status, value) {
			if (value) {
				const option = this.statusOptions.find(opt => opt.value === status)
				this.filters.status = option
			} else {
				this.filters.status = null
			}
			this.onFilterChange()
		},

		sortColumn(column) {
			if (this.sortBy === column) {
				if (this.sortOrder === 'DESC') {
					this.sortOrder = 'ASC'
				} else if (this.sortOrder === 'ASC') {
					this.sortBy = null
					this.sortOrder = null
				}
			} else {
				this.sortBy = column
				this.sortOrder = 'DESC'
			}
			this.saveSort()
			this.loadDocuments()
		},

		async saveSort() {
			try {
				const sort = {
					sortBy: this.sortBy,
					sortOrder: this.sortOrder,
				}
				await this.userConfigStore.update('id_docs_sort', sort)
			} catch (error) {
				console.error('Failed to save sort:', error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.id-docs-validation {
	height: 100%;
	display: flex;
	flex-direction: column;

	&__toolbar {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 8px;
		margin-top: 12px;
		margin-right: 12px;
		margin-bottom: 12px;

		.filter-wrapper {
			position: relative;

			&--active :deep(button) {
				background-color: var(--color-primary-element-light) !important;

				&:hover {
					background-color: var(--color-primary-element-light-hover) !important;
				}
			}
		}

		.filter-badge {
			position: absolute;
			top: -8px;
			right: -8px;
			z-index: 100;
			min-width: 18px;
			height: 18px;
			padding: 0 5px;
			background-color: var(--color-primary-element);
			color: var(--color-primary-element-text);
			border-radius: 9px;
			font-size: 11px;
			font-weight: 600;
			line-height: 18px;
			box-shadow: 0 0 0 2px var(--color-main-background);
			pointer-events: none;
		}
	}

	&__loading-more {
		padding: 20px;
		text-align: center;
	}

	&__end {
		padding: 20px;
		text-align: center;
		color: var(--color-text-maxcontrast);
		font-size: 14px;
	}
}

.container-account-docs-to-validate {
	flex: 1;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.id-docs-table {
	$spacer-width: 44px;
	$cell-padding: 12px;
	$frozen-z-body: 2;
	$frozen-z-head: 20;
	$sticky-z-head: 10;

	width: 100%;
	border-collapse: collapse;

	@mixin frozen-separator($side) {
		content: '';
		position: absolute;
		top: 0;
		bottom: 0;
		width: 1px;
		background-color: var(--color-border);
		@if $side == 'right' {
			right: 0;
		} @else {
			left: 0;
		}
	}

	thead {
		th {
			position: sticky;
			top: 0;
			background-color: var(--color-main-background);
			z-index: $sticky-z-head;
			padding: $cell-padding;
			text-align: left;
			font-weight: 600;
			border-bottom: 2px solid var(--color-border);
			white-space: nowrap;

			&.id-docs-table__cell--frozen-spacer,
			&.id-docs-table__cell--frozen-owner,
			&.id-docs-table__cell--frozen-right {
				z-index: $frozen-z-head;
			}

			&.sortable {
				cursor: pointer;
				user-select: none;
				transition: background-color 0.2s;

				&:hover {
					background-color: var(--color-background-hover);
				}

				.sort-indicator {
					margin-left: 4px;
					font-size: 10px;
					color: var(--color-primary);
				}
			}
		}
	}

	tbody {
		tr {
			border-bottom: 1px solid var(--color-border);

			&:hover {
				background-color: var(--color-background-hover);

				.id-docs-table__cell--frozen-left,
				.id-docs-table__cell--frozen-right {
					background-color: var(--color-background-hover);
				}
			}
		}

		td {
			padding: $cell-padding;
			font-size: 14px;
		}
	}

	&__cell--spacer {
		width: $spacer-width;
		min-width: $spacer-width;
		max-width: $spacer-width;
		padding: 6px;
		text-align: center;
	}

	&__cell--frozen-left,
	&__cell--frozen-right {
		position: sticky;
		z-index: $frozen-z-body;
		background-color: var(--color-main-background);
	}

	&__cell--frozen-left::after {
		@include frozen-separator('right');
	}

	&__cell--frozen-right {
		right: 0;

		&::before {
			@include frozen-separator('left');
		}
	}

	&__cell--frozen-spacer {
		left: 0;
	}

	&__cell--frozen-owner {
		left: $spacer-width;
	}
}
</style>
