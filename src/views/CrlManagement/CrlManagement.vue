<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="crl-management">
			<div class="crl-management__toolbar">
				<div class="filter-wrapper" :class="{ 'filter-wrapper--active': hasActiveFilters }">
					<NcActions :aria-label="hasActiveFilters ? t('libresign', 'Filters ({count})', { count: activeFilterCount }) : t('libresign', 'Filters')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFilter" :size="20" />
						</template>
						<NcActionInput v-model="filters.serialNumber"
							:label="t('libresign', 'Serial Number')"
							@update:modelValue="onFilterChange">
							<template #icon>
								<NcIconSvgWrapper :path="mdiMagnify" :size="20" />
							</template>
						</NcActionInput>

						<NcActionInput v-model="filters.owner"
							:label="t('libresign', 'Owner')"
							@update:modelValue="onFilterChange">
							<template #icon>
								<NcIconSvgWrapper :path="mdiAccount" :size="20" />
							</template>
						</NcActionInput>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'issued'"
							@update:modelValue="setStatusFilter('issued', $event)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiCheckCircle" :size="20" />
							</template>
							{{ t('libresign', 'Issued') }}
						</NcActionButton>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'revoked'"
							@update:modelValue="setStatusFilter('revoked', $event)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiCancel" :size="20" />
							</template>
							{{ t('libresign', 'Revoked') }}
						</NcActionButton>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'expired'"
							@update:modelValue="setStatusFilter('expired', $event)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiClockAlert" :size="20" />
							</template>
							{{ t('libresign', 'Expired') }}
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

			<div v-if="loading && entries.length === 0" class="crl-management__loading">
				<NcLoadingIcon :size="64" />
			</div>

			<div v-else-if="entries.length === 0" class="crl-management__empty">
				<NcEmptyContent :name="t('libresign', 'No CRL entries found')"
					:description="t('libresign', 'There are no certificate revocation list entries to display.')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiShieldLock" :size="64" />
					</template>
				</NcEmptyContent>
			</div>

			<div v-else
				ref="scrollContainer"
				class="crl-management__list"
				@scroll="onScroll">
				<table class="crl-table">
					<thead>
						<tr>
							<th class="crl-table__cell--spacer crl-table__cell--frozen-left crl-table__cell--frozen-spacer" />
							<th class="sortable crl-table__cell--frozen-left crl-table__cell--frozen-owner" @click="sortColumn('owner')">
								{{ t('libresign', 'Owner') }}
								<span v-if="sortBy === 'owner'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('serial_number')">
								{{ t('libresign', 'Serial Number') }}
								<span v-if="sortBy === 'serial_number'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th>{{ t('libresign', 'Type') }}</th>
							<th class="sortable" @click="sortColumn('status')">
								{{ t('libresign', 'Status') }}
								<span v-if="sortBy === 'status'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('engine')">
								{{ t('libresign', 'Engine') }}
								<span v-if="sortBy === 'engine'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('issued_at')">
								{{ t('libresign', 'Issued At') }}
								<span v-if="sortBy === 'issued_at'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('valid_to')">
								{{ t('libresign', 'Valid To') }}
								<span v-if="sortBy === 'valid_to'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('revoked_at')">
								{{ t('libresign', 'Revoked At') }}
								<span v-if="sortBy === 'revoked_at'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
							<th class="sortable" @click="sortColumn('reason_code')">
								{{ t('libresign', 'Reason') }}
								<span v-if="sortBy === 'reason_code'" class="sort-indicator">
									{{ sortOrder === 'ASC' ? '▲' : '▼' }}
								</span>
							</th>
						<th>{{ t('libresign', 'Comment') }}</th>
						<th class="crl-table__cell--frozen-right">{{ t('libresign', 'Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(entry, index) in entries" :key="`${entry.serial_number}-${entry.issued_at}-${index}`" class="crl-table__row">
							<td class="crl-table__cell--spacer crl-table__cell--frozen-left crl-table__cell--frozen-spacer">
								<NcAvatar v-if="entry.certificate_type === 'leaf'"
									:user="entry.owner"
									:size="32"
									:display-name="entry.owner"
									:disable-menu="true" />
							</td>
							<td class="crl-table__cell--frozen-left crl-table__cell--frozen-owner">{{ entry.owner }}</td>
							<td class="crl-table__cell--monospace">{{ entry.serial_number }}</td>
							<td>
								<span v-if="entry.certificate_type === 'root'" class="certificate-type certificate-type--root">
									<span class="certificate-type__icon">
										<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path :d="mdiShieldLock" />
										</svg>
									</span>
									{{ t('libresign', 'Root CA') }}
								</span>
								<span v-else-if="entry.certificate_type === 'intermediate'" class="certificate-type certificate-type--intermediate">
									<span class="certificate-type__icon">
										<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path :d="mdiShieldLock" />
										</svg>
									</span>
									{{ t('libresign', 'Intermediate CA') }}
								</span>
								<span v-else class="certificate-type certificate-type--user">
									{{ t('libresign', 'User') }}
								</span>
							</td>
							<td>
								<span :class="'status-badge status-badge--' + entry.status">
									{{ entry.status }}
								</span>
							</td>
							<td>{{ entry.engine }}</td>
							<td>{{ formatDate(entry.issued_at) }}</td>
							<td>{{ formatDate(entry.valid_to) }}</td>
							<td>{{ formatDate(entry.revoked_at) }}</td>
							<td>{{ getReasonText(entry.reason_code) }}</td>
							<td class="crl-table__cell--comment">
								<span v-if="entry.comment" :title="entry.comment">{{ entry.comment }}</span>
								<span v-else class="crl-table__cell--empty">-</span>
							</td>
							<td class="crl-table__cell--frozen-right">
								<NcButton v-if="entry.status === 'issued'"
									variant="error"
									@click="openRevokeDialog(entry)">
									{{ t('libresign', 'Revoke') }}
								</NcButton>
							</td>
						</tr>
					</tbody>
				</table>

				<div v-if="loadingMore" class="crl-management__loading-more">
					<NcLoadingIcon :size="32" />
				</div>

				<div v-if="!hasMore && entries.length > 0" class="crl-management__end">
					{{ t('libresign', 'No more entries to load') }}
				</div>
			</div>
		</div>

		<NcDialog v-if="caWarningDialog.open"
			:name="t('libresign', 'Warning: Certificate Authority')"
			@update:open="closeCaWarningDialog">
			<div class="ca-warning-dialog">
				<NcNoteCard type="error">
					{{ t('libresign', 'You are about to revoke a {type} CERTIFICATE AUTHORITY. This is a critical operation that may invalidate certificates issued by this CA.', { type: caWarningDialog.typeLabel }) }}
				</NcNoteCard>

				<div class="ca-warning-dialog__info">
					<p><strong>{{ t('libresign', 'Serial Number:') }}</strong> {{ caWarningDialog.entry?.serial_number }}</p>
					<p><strong>{{ t('libresign', 'Owner:') }}</strong> {{ caWarningDialog.entry?.owner }}</p>
					<p><strong>{{ t('libresign', 'Type:') }}</strong> {{ getCertificateTypeLabel(caWarningDialog.entry?.certificate_type) }}</p>
				</div>

				<p class="ca-warning-dialog__question">
					{{ t('libresign', 'Are you absolutely sure you want to proceed?') }}
				</p>

				<div class="ca-warning-dialog__actions">
					<NcButton @click="closeCaWarningDialog">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton variant="error" @click="proceedToRevokeDialog">
						{{ t('libresign', 'Yes, revoke CA') }}
					</NcButton>
				</div>
			</div>
		</NcDialog>

		<NcDialog v-if="revokeDialog.open"
			:name="t('libresign', 'Revoke Certificate')"
			:no-close="revokeDialog.loading"
			@update:open="closeRevokeDialog">
			<div class="revoke-dialog">
				<NcNoteCard type="warning">
					{{ t('libresign', 'This action cannot be undone. The certificate will be permanently revoked.') }}
				</NcNoteCard>

				<NcNoteCard v-if="revokeDialog.entry?.certificate_type === 'root' || revokeDialog.entry?.certificate_type === 'intermediate'" type="error">
					{{ t('libresign', 'WARNING: This is a CERTIFICATE AUTHORITY! Revoking it will affect the certificate chain and may invalidate certificates issued by this CA.') }}
				</NcNoteCard>

				<div class="revoke-dialog__info">
					<p><strong>{{ t('libresign', 'Serial Number:') }}</strong> {{ revokeDialog.entry?.serial_number }}</p>
					<p><strong>{{ t('libresign', 'Owner:') }}</strong> {{ revokeDialog.entry?.owner }}</p>
					<p v-if="revokeDialog.entry?.certificate_type !== 'leaf'"><strong>{{ t('libresign', 'Type:') }}</strong> {{ getCertificateTypeLabel(revokeDialog.entry?.certificate_type) }}</p>
				</div>

				<div class="revoke-dialog__form">
					<NcSelect v-model="revokeDialog.reasonCode"
						:input-label="t('libresign', 'Revocation Reason')"
						:options="reasonCodeOptions"
						:disabled="revokeDialog.loading"
						label="label"
						track-by="value" />

					<NcTextArea v-model="revokeDialog.reasonText"
						:label="t('libresign', 'Reason Description (optional)')"
						:disabled="revokeDialog.loading"
						:maxlength="255"
						:rows="3" />
				</div>
				<div class="revoke-dialog__actions">
					<NcButton :disabled="revokeDialog.loading"
						@click="closeRevokeDialog">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton variant="error"
						:disabled="revokeDialog.loading"
						@click="confirmRevoke">
						<template #icon>
							<NcLoadingIcon v-if="revokeDialog.loading" :size="20" />
						</template>
						{{ t('libresign', 'Revoke Certificate') }}
					</NcButton>
				</div>
			</div>
		</NcDialog>
	</NcAppContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, reactive, ref } from 'vue'
import {
	mdiAccount,
	mdiCheckCircle,
	mdiCancel,
	mdiClockAlert,
	mdiClose,
	mdiFilter,
	mdiMagnify,
	mdiShieldLock,
} from '@mdi/js'


import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { useUserConfigStore } from '../../store/userconfig.js'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

type SelectOption = {
	value: string
	label: string
}

type ReasonOption = {
	value: number
	label: string
}

type CrlEntry = Record<string, any>

defineOptions({
	name: 'CrlManagement',
})

const userConfigStore = useUserConfigStore()
const scrollContainer = ref<HTMLElement | null>(null)
const entries = ref<CrlEntry[]>([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const length = ref(50)
const total = ref(0)
const hasMore = ref(true)
const statusOptions: SelectOption[] = [
	{ value: 'issued', label: t('libresign', 'Issued') },
	{ value: 'revoked', label: t('libresign', 'Revoked') },
	{ value: 'expired', label: t('libresign', 'Expired') },
]
const initialStatus = userConfigStore.crl_filters?.status
const initialStatusOption = typeof initialStatus === 'string'
	? statusOptions.find(option => option.value === initialStatus) || null
	: null
const filters = reactive({
	serialNumber: userConfigStore.crl_filters?.serialNumber || '',
	status: initialStatusOption as SelectOption | null,
	owner: userConfigStore.crl_filters?.owner || '',
})
const sortBy = ref<string | null>(userConfigStore.crl_sort?.sortBy || 'revoked_at')
const sortOrder = ref<'ASC' | 'DESC' | null>(userConfigStore.crl_sort?.sortOrder || 'DESC')
const caWarningDialog = reactive({
	open: false,
	entry: null as CrlEntry | null,
	typeLabel: '',
})
const revokeDialog = reactive({
	open: false,
	entry: null as CrlEntry | null,
	reasonCode: { value: 0, label: '' } as ReasonOption | null,
	reasonText: '',
	loading: false,
})
const reasonCodes: Record<number, string> = {
	0: t('libresign', 'Unspecified'),
	1: t('libresign', 'Key Compromise'),
	2: t('libresign', 'CA Compromise'),
	3: t('libresign', 'Affiliation Changed'),
	4: t('libresign', 'Superseded'),
	5: t('libresign', 'Cessation of Operation'),
	6: t('libresign', 'Certificate Hold'),
	8: t('libresign', 'Remove from CRL'),
	9: t('libresign', 'Privilege Withdrawn'),
	10: t('libresign', 'AA Compromise'),
}
const reasonCodeOptions: ReasonOption[] = [
	{ value: 0, label: t('libresign', 'Unspecified') },
	{ value: 1, label: t('libresign', 'Key Compromise') },
	{ value: 2, label: t('libresign', 'CA Compromise') },
	{ value: 3, label: t('libresign', 'Affiliation Changed') },
	{ value: 4, label: t('libresign', 'Superseded') },
	{ value: 5, label: t('libresign', 'Cessation of Operation') },
	{ value: 6, label: t('libresign', 'Certificate Hold') },
	{ value: 8, label: t('libresign', 'Remove from CRL') },
	{ value: 9, label: t('libresign', 'Privilege Withdrawn') },
	{ value: 10, label: t('libresign', 'AA Compromise') },
]

const hasActiveFilters = computed(() => !!(filters.serialNumber || filters.status || filters.owner))
const activeFilterCount = computed(() => {
	let count = 0
	if (filters.serialNumber) count++
	if (filters.status) count++
	if (filters.owner) count++
	return count
})

let filterTimeout: ReturnType<typeof setTimeout> | undefined

async function loadEntries(append = false) {
	if (!append) {
		loading.value = true
		page.value = 1
		entries.value = []
	} else {
		loadingMore.value = true
	}

	try {
		const params: Record<string, any> = {
			page: page.value,
			length: length.value,
		}

		if (filters.serialNumber) {
			params.serialNumber = filters.serialNumber
		}
		if (filters.status?.value) {
			params.status = filters.status.value
		}
		if (filters.owner) {
			params.owner = filters.owner
		}
		if (sortBy.value) {
			params.sortBy = sortBy.value
			params.sortOrder = sortOrder.value
		}

		const response = await axios.get(
			generateOcsUrl('/apps/libresign/api/{apiVersion}/crl/list', { apiVersion: 'v1' }),
			{ params },
		)

		const data = response.data.ocs.data

		if (append) {
			entries.value.push(...data.data)
		} else {
			entries.value = data.data
		}

		total.value = data.total
		hasMore.value = entries.value.length < total.value
	} catch (error: any) {
		console.error('Failed to load CRL entries:', error)
		console.error('Error response:', error.response)
		showError(t('libresign', 'Failed to load CRL entries'))
	} finally {
		loading.value = false
		loadingMore.value = false
	}
}

function onFilterChange() {
	clearTimeout(filterTimeout)
	filterTimeout = setTimeout(() => {
		saveFilters()
		loadEntries()
	}, 500)
}

async function saveFilters() {
	try {
		await userConfigStore.update('crl_filters', {
			serialNumber: filters.serialNumber,
			status: filters.status?.value || null,
			owner: filters.owner,
		})
	} catch (error) {
		console.error('Failed to save filters:', error)
	}
}

async function saveSort() {
	try {
		await userConfigStore.update('crl_sort', {
			sortBy: sortBy.value,
			sortOrder: sortOrder.value,
		})
	} catch (error) {
		console.error('Failed to save sort:', error)
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
		loadEntries(true)
	}
}

function formatDate(dateString: string | null | undefined) {
	if (!dateString) {
		return '-'
	}
	return new Date(dateString).toLocaleString()
}

function getReasonText(reasonCode: number | null | undefined) {
	if (reasonCode === null || reasonCode === undefined) {
		return '-'
	}
	return reasonCodes[reasonCode] || t('libresign', 'Unknown')
}

function getCertificateTypeLabel(type: string) {
	const labels: Record<string, string> = {
		root: t('libresign', 'Root Certificate (CA)'),
		intermediate: t('libresign', 'Intermediate Certificate (CA)'),
		leaf: t('libresign', 'User Certificate'),
	}
	return labels[type] || type
}

function clearFilters() {
	filters.serialNumber = ''
	filters.status = null
	filters.owner = ''
	saveFilters()
	loadEntries()
}

function setStatusFilter(status: string, value: boolean) {
	if (value) {
		filters.status = statusOptions.find(option => option.value === status) || null
	} else {
		filters.status = null
	}
	onFilterChange()
}

function sortColumn(column: string) {
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
	loadEntries()
}

function openRevokeDialog(entry: CrlEntry) {
	if (entry.certificate_type === 'root' || entry.certificate_type === 'intermediate') {
		caWarningDialog.open = true
		caWarningDialog.entry = entry
		caWarningDialog.typeLabel = entry.certificate_type === 'root' ? 'ROOT' : 'INTERMEDIATE'
		return
	}

	revokeDialog.open = true
	revokeDialog.entry = entry
	revokeDialog.reasonCode = reasonCodeOptions[0]
	revokeDialog.reasonText = ''
}

function closeCaWarningDialog() {
	caWarningDialog.open = false
	caWarningDialog.entry = null
	caWarningDialog.typeLabel = ''
}

function proceedToRevokeDialog() {
	const entry = caWarningDialog.entry
	closeCaWarningDialog()
	revokeDialog.open = true
	revokeDialog.entry = entry
	revokeDialog.reasonCode = reasonCodeOptions[0]
	revokeDialog.reasonText = ''
}

function closeRevokeDialog() {
	revokeDialog.open = false
	revokeDialog.entry = null
	revokeDialog.reasonCode = null
	revokeDialog.reasonText = ''
	revokeDialog.loading = false
}

async function confirmRevoke() {
	revokeDialog.loading = true

	try {
		const response = await axios.post(
			generateOcsUrl('/apps/libresign/api/{apiVersion}/crl/revoke', { apiVersion: 'v1' }),
			{
				serialNumber: revokeDialog.entry?.serial_number,
				reasonCode: revokeDialog.reasonCode?.value ?? 0,
				reasonText: revokeDialog.reasonText || null,
			},
		)

		if (response.data.ocs.data.success) {
			showSuccess(t('libresign', 'Certificate revoked successfully'))
			closeRevokeDialog()
			loadEntries()
		} else {
			showError(response.data.ocs.data.message || t('libresign', 'Failed to revoke certificate'))
		}
	} catch (error: any) {
		console.error('Failed to revoke certificate:', error)
		const message = error.response?.data?.ocs?.data?.message || t('libresign', 'An error occurred while revoking the certificate')
		showError(message)
	} finally {
		revokeDialog.loading = false
	}
}

onMounted(() => {
	loadEntries()
})

defineExpose({
	t,
	mdiFilter,
	mdiMagnify,
	mdiAccount,
	mdiCheckCircle,
	mdiCancel,
	mdiClockAlert,
	mdiClose,
	mdiShieldLock,
	userConfigStore,
	scrollContainer,
	entries,
	loading,
	loadingMore,
	page,
	length,
	total,
	hasMore,
	filters,
	sortBy,
	sortOrder,
	caWarningDialog,
	revokeDialog,
	statusOptions,
	reasonCodes,
	reasonCodeOptions,
	hasActiveFilters,
	activeFilterCount,
	loadEntries,
	onFilterChange,
	saveFilters,
	saveSort,
	onScroll,
	formatDate,
	getReasonText,
	getCertificateTypeLabel,
	clearFilters,
	setStatusFilter,
	sortColumn,
	openRevokeDialog,
	closeCaWarningDialog,
	proceedToRevokeDialog,
	closeRevokeDialog,
	confirmRevoke,
})
</script>

<style lang="scss" scoped>
.crl-management {
	padding: 20px 0;
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

	&__loading,
	&__empty {
		display: flex;
		flex: 1;
		align-items: center;
		justify-content: center;
	}

	&__list {
		flex: 1;
		overflow-y: auto;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
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

.crl-table {
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

			&.crl-table__cell--frozen-spacer,
			&.crl-table__cell--frozen-owner,
			&.crl-table__cell--frozen-right {
				z-index: $frozen-z-head;
			}
		}
	}

	tbody {
		tr {
			border-bottom: 1px solid var(--color-border);

			&:hover {
				background-color: var(--color-background-hover);

				.crl-table__cell--frozen-left,
				.crl-table__cell--frozen-right {
					background-color: var(--color-background-hover);
				}
			}
		}

		td {
			padding: $cell-padding;
			font-size: 14px;
		}
	}

	&__cell--monospace {
		font-family: monospace;
		font-size: 13px;
	}

	&__cell--comment {
		max-width: 250px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__cell--empty {
		color: var(--color-text-maxcontrast);
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

.status-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	height: 26px;
	padding: 0 10px;
	border-radius: 13px;
	box-sizing: border-box;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;
	text-transform: uppercase;

	&--issued {
		background-color: #d4edda;
		color: #155724;
	}

	&--revoked {
		background-color: #f8d7da;
		color: #721c24;
	}

	&--expired {
		background-color: #fff3cd;
		color: #856404;
	}
}

.certificate-type {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 4px;
	height: 26px;
	padding: 0 10px;
	border-radius: 13px;
	box-sizing: border-box;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;

	&__icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 14px;
		height: 14px;
		flex: 0 0 14px;
		line-height: 0;

		svg {
			display: block;
			width: 14px;
			height: 14px;
			fill: currentColor;
		}
	}

	&--root {
		background-color: #e3f2fd;
		color: #1565c0;
	}

	&--intermediate {
		background-color: #fff3e0;
		color: #e65100;
	}

	&--user {
		background-color: #f3e5f5;
		color: #6a1b9a;
	}
}

.owner-cell {
	display: flex;
	align-items: center;
	gap: 8px;
}

.ca-warning-dialog {
	padding: 20px;

	&__info {
		margin: 20px 0;
		padding: 12px;
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius);

		p {
			margin: 8px 0;
		}
	}

	&__question {
		margin: 20px 0;
		padding: 12px;
		font-weight: bold;
		text-align: center;
		background-color: var(--color-error-hover);
		border-left: 4px solid var(--color-error);
		border-radius: var(--border-radius);
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
		margin-top: 20px;
	}
}

.revoke-dialog {
	padding: 20px;

	&__info {
		margin: 20px 0;
		padding: 12px;
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius);

		p {
			margin: 8px 0;
		}
	}

	&__form {
		margin: 20px 0;

		> * {
			margin-bottom: 16px;
		}
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
		margin-top: 30px;
	}
}
</style>

