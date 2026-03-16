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
						<NcIconSvgWrapper :path="mdiFilter" :size="20" />
					</template>
					<NcActionInput v-model="filters.owner"
						:label="t('libresign', 'Owner')"
						@update:modelValue="onFilterChange">
						<template #icon>
						<NcIconSvgWrapper :path="mdiAccount" :size="20" />
						</template>
					</NcActionInput>

					<NcActionButton type="radio"
						:model-value="filters.status?.value === 'signed'"
						@update:modelValue="setStatusFilter('signed', $event)">
						<template #icon>
						<NcIconSvgWrapper :path="mdiCheckCircle" :size="20" />
						</template>
						{{ t('libresign', 'Signed') }}
					</NcActionButton>

					<NcActionButton type="radio"
						:model-value="filters.status?.value === 'pending'"
						@update:modelValue="setStatusFilter('pending', $event)">
						<template #icon>
						<NcIconSvgWrapper :path="mdiClockAlert" :size="20" />
						</template>
						{{ t('libresign', 'Pending') }}
					</NcActionButton>

					<NcActionSeparator v-if="hasActiveFilters" />

					<NcActionButton v-if="hasActiveFilters"
						@click="clearFilters">
						<template #icon>
						<NcIconSvgWrapper :path="mdiClose" :size="20" />
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
				<NcIconSvgWrapper :path="mdiFileDocument" :size="64" />
			</template>
		</NcEmptyContent>

		<div v-else
			ref="scrollContainer"
			class="container-account-docs-to-validate"
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
				<tr v-for="(doc, index) in filteredDocuments" :key="`doc-${index}-${doc.file.file.nodeId}-${doc.file_type.type}`">
					<td class="id-docs-table__cell--spacer id-docs-table__cell--frozen-left id-docs-table__cell--frozen-spacer">
						<NcAvatar :user="doc.account?.userId ?? doc.account?.displayName"
							:display-name="doc.account?.displayName || doc.account?.userId"
							:size="32"
							:disable-menu="true" />
					</td>
					<td class="id-docs-table__cell--frozen-left id-docs-table__cell--frozen-owner">
						{{ doc.account?.displayName || doc.account?.userId || '-' }}
					</td>
					<td>
						{{ doc.file_type.name }}
					</td>
					<td>
						{{ doc.file.statusText }}
					</td>
					<td>
						<template v-if="doc.file?.signers?.length > 0 && doc.file.signers[0].sign_date">
							<NcAvatar v-if="doc.file.signers[0].uid"
								:user="doc.file.signers[0].uid"
								:display-name="doc.file.signers[0].displayName"
								:size="32"
								:disable-menu="true" />
							{{ doc.file.signers[0].displayName }}
						</template>
						<template v-else>
							-
						</template>
					</td>
					<td class="id-docs-table__cell--frozen-right">
						<NcActions :force-name="true" :inline="4">
							<template v-if="doc.file?.status === FILE_STATUS.SIGNED">
								<NcActionButton @click="openValidationURL(doc)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiEye" :size="20" />
									</template>
									<!-- TRANSLATORS: "Validate" here is a technical process: checking the cryptographic integrity of the signatures, the certificate chain and revocation status. It does NOT mean approving or authorizing something. Choose a word in your language that conveys "to check" or "to verify", not "to approve" or "to authorize". -->
									{{ t('libresign', 'Validate') }}
								</NcActionButton>
							</template>
							<template v-else>
								<NcActionButton @click="openFile(doc)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiFileDocumentOutline" :size="20" />
									</template>
									{{ t('libresign', 'Open file') }}
								</NcActionButton>
							</template>
							<NcActionButton v-if="doc.file?.status === FILE_STATUS.ABLE_TO_SIGN"
								:aria-label="t('libresign', 'Sign')"
								@click="openApprove(doc)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPencil" :size="20" />
								</template>
								{{ t('libresign', 'Sign') }}
							</NcActionButton>
							<NcActionButton @click="deleteDocument(doc)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiDelete" :size="20" />
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
	mdiAccount,
	mdiCheckCircle,
	mdiClockAlert,
	mdiClose,
	mdiDelete,
	mdiEye,
	mdiFileDocument,
	mdiFileDocumentOutline,
	mdiFilter,
	mdiPencil,
} from '@mdi/js'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import { FILE_STATUS } from '../../constants.js'
import { openDocument } from '../../utils/viewer.js'
import { useUserConfigStore } from '../../store/userconfig.js'
import type { operations, components } from '../../types/openapi/openapi'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

type SortField = 'owner' | 'file_type' | 'status'
type SortOrder = 'ASC' | 'DESC' | null

type StatusOption = {
	value: 'signed' | 'pending'
	label: string
}

type PersistedStatusFilter = StatusOption['value'] | null
type IdDocEntry = components['schemas']['File']
type IdDocsListQuery = NonNullable<operations['id_docs-list-to-approval']['parameters']['query']>
type IdDocsListSuccess = operations['id_docs-list-to-approval']['responses'][200]['content']['application/json']
type IdDocsListError = operations['id_docs-list-to-approval']['responses'][404]['content']['application/json']
type IdDocsDeleteError = operations['id_docs-delete']['responses'][401]['content']['application/json']
type UserConfigFilters = {
	owner?: string
	status?: PersistedStatusFilter
}
type UserConfigSort = {
	sortBy?: SortField | null
	sortOrder?: SortOrder
}
type UserConfigStore = {
	id_docs_filters?: UserConfigFilters
	id_docs_sort?: UserConfigSort
	update: (key: 'id_docs_filters' | 'id_docs_sort', value: UserConfigFilters | UserConfigSort) => Promise<unknown> | unknown
}

const SORT_FIELDS: readonly SortField[] = ['owner', 'file_type', 'status']
const STATUS_OPTIONS: StatusOption[] = [
	{ value: 'signed', label: t('libresign', 'Signed') },
	{ value: 'pending', label: t('libresign', 'Pending') },
]

defineOptions({
	name: 'IdDocsValidation',
})

const router = useRouter()
const userConfigStore = useUserConfigStore() as UserConfigStore
const scrollContainer = ref<HTMLElement | null>(null)
const documentList = ref<IdDocEntry[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const page = ref(1)
const length = ref(50)
const total = ref(0)
const hasMore = ref(true)
const storedSort = userConfigStore.id_docs_sort
const initialSortBy = isSortField(storedSort?.sortBy)
	? storedSort.sortBy
	: null
const initialSortOrder = isSortOrder(storedSort?.sortOrder)
	? storedSort.sortOrder
	: null
const sortBy = ref<SortField | null>(initialSortBy)
const sortOrder = ref<SortOrder>(initialSortOrder)
const filters = reactive({
	owner: userConfigStore.id_docs_filters?.owner || '',
	status: getStatusOption(userConfigStore.id_docs_filters?.status) as StatusOption | null,
})

const hasActiveFilters = computed(() => !!(filters.owner || filters.status))
const activeFilterCount = computed(() => {
	let count = 0
	if (filters.owner) count++
	if (filters.status) count++
	return count
})
const filteredDocuments = computed(() => {
	let docs = [...documentList.value]

	if (filters.owner) {
		const ownerLower = filters.owner.toLowerCase()
		docs = docs.filter(doc => {
			const displayName = doc.account.displayName || doc.account.userId
			return displayName.toLowerCase().includes(ownerLower)
		})
	}

	if (filters.status?.value === 'signed') {
		docs = docs.filter(doc => doc.file.status === FILE_STATUS.SIGNED)
	} else if (filters.status?.value === 'pending') {
		docs = docs.filter(doc => doc.file.status !== FILE_STATUS.SIGNED)
	}

	return docs
})

let filterTimeout: ReturnType<typeof setTimeout> | undefined

function isSortField(value: unknown): value is SortField {
	return typeof value === 'string' && SORT_FIELDS.includes(value as SortField)
}

function isSortOrder(value: unknown): value is Exclude<SortOrder, null> {
	return value === 'ASC' || value === 'DESC'
}

function getStatusOption(status: UserConfigFilters['status']): StatusOption | null {
	if (!status) {
		return null
	}

	return STATUS_OPTIONS.find(option => option.value === status) || null
}

function getErrorMessage(error: unknown): string | null {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return null
	}

	const response = error.response
	if (typeof response !== 'object' || response === null || !('data' in response)) {
		return null
	}

	const data = response.data as IdDocsListError | IdDocsDeleteError | undefined
	if (!data?.ocs?.data || typeof data.ocs.data !== 'object') {
		return null
	}

	if ('message' in data.ocs.data && typeof data.ocs.data.message === 'string') {
		return data.ocs.data.message
	}

	if ('messages' in data.ocs.data && Array.isArray(data.ocs.data.messages)) {
		return data.ocs.data.messages[0] ?? null
	}

	return null
}

async function loadDocuments(append = false) {
	if (!append) {
		loading.value = true
		page.value = 1
		documentList.value = []
	} else {
		loadingMore.value = true
	}

	try {
		const params: IdDocsListQuery = {
			page: page.value,
			length: length.value,
		}

		if (sortBy.value) {
			params.sortBy = sortBy.value
			params.sortOrder = sortOrder.value
		}

		const response = await axios.get(
			generateOcsUrl('/apps/libresign/api/v1/id-docs/approval/list'),
			{ params },
		)

		const data = (response.data as IdDocsListSuccess).ocs.data
		const documents = data.data ?? []

		if (append) {
			documentList.value.push(...documents)
		} else {
			documentList.value = documents
		}

		total.value = data.pagination.total
		hasMore.value = documentList.value.length < total.value
	} catch (error: unknown) {
		showError(getErrorMessage(error) || t('libresign', 'Failed to load documents'))
	} finally {
		loading.value = false
		loadingMore.value = false
	}
}

function onScroll(event: Event) {
	if (loadingMore.value || !hasMore.value) {
		return
	}

	const container = event.target as HTMLElement
	const scrollPosition = container.scrollTop + container.clientHeight
	const scrollHeight = container.scrollHeight

	if (scrollPosition >= scrollHeight * 0.8) {
		page.value++
		void loadDocuments(true)
	}
}

function openApprove(doc: IdDocEntry) {
	router.push({
		name: 'IdDocsApprove',
		params: { uuid: doc.file.uuid },
		query: { idDocApproval: 'true' },
	})
}

async function deleteDocument(doc: IdDocEntry) {
	try {
		await axios.delete(generateOcsUrl('/apps/libresign/api/v1/id-docs/{nodeId}', { nodeId: doc.file.file.nodeId }))
		await loadDocuments()
	} catch (error: unknown) {
		showError(getErrorMessage(error) || t('libresign', 'Failed to delete document'))
	}
}

function openFile(doc: IdDocEntry) {
	const fileUrl = doc.file.file.url

	if (!fileUrl) {
		showError(t('libresign', 'File not found'))
		return
	}

	openDocument({
		fileUrl,
		filename: doc.file.name,
		nodeId: doc.file.file.nodeId,
	})
}

function openValidationURL(doc: IdDocEntry) {
	router.push({
		name: 'ValidationFile',
		params: { uuid: doc.file.uuid },
	})
}

function onFilterChange() {
	clearTimeout(filterTimeout)
	filterTimeout = setTimeout(() => {
		saveFilters()
	}, 500)
}

async function saveFilters() {
	try {
		await userConfigStore.update('id_docs_filters', {
			owner: filters.owner,
			status: filters.status?.value ?? null,
		})
	} catch (error) {
		console.error('Failed to save filters:', error)
	}
}

function clearFilters() {
	filters.owner = ''
	filters.status = null
	saveFilters()
}

function setStatusFilter(status: StatusOption['value'], value: boolean) {
	if (value) {
		filters.status = STATUS_OPTIONS.find(option => option.value === status) || null
	} else {
		filters.status = null
	}
	onFilterChange()
}

function sortColumn(column: SortField) {
	if (sortBy.value === column) {
		if (sortOrder.value === 'DESC') {
			sortOrder.value = 'ASC'
		} else if (sortOrder.value === 'ASC') {
			sortBy.value = null
			sortOrder.value = null
		}
	} else {
		sortBy.value = column
		sortOrder.value = 'DESC'
	}
	saveSort()
	loadDocuments()
}

async function saveSort() {
	try {
		await userConfigStore.update('id_docs_sort', {
			sortBy: sortBy.value,
			sortOrder: sortOrder.value,
		})
	} catch (error) {
		console.error('Failed to save sort:', error)
	}
}

onMounted(() => {
	loadDocuments()
})

onBeforeUnmount(() => {
	clearTimeout(filterTimeout)
})

defineExpose({
	t,
	FILE_STATUS,
	userConfigStore,
	scrollContainer,
	documentList,
	loading,
	loadingMore,
	page,
	length,
	total,
	hasMore,
	sortBy,
	sortOrder,
	filters,
	statusOptions: STATUS_OPTIONS,
	hasActiveFilters,
	activeFilterCount,
	filteredDocuments,
	loadDocuments,
	onScroll,
	openApprove,
	deleteDocument,
	openFile,
	openValidationURL,
	onFilterChange,
	saveFilters,
	clearFilters,
	setStatusFilter,
	sortColumn,
	saveSort,
	mdiAccount,
	mdiCheckCircle,
	mdiClockAlert,
	mdiClose,
	mdiDelete,
	mdiEye,
	mdiFileDocument,
	mdiFileDocumentOutline,
	mdiFilter,
	mdiPencil,
})
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
	width: 100%;
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
