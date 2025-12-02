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
							<FilterIcon :size="20" />
						</template>
						<NcActionInput v-model="filters.serialNumber"
							:label="t('libresign', 'Serial Number')"
							@update:value="onFilterChange">
							<template #icon>
								<Magnify :size="20" />
							</template>
						</NcActionInput>

						<NcActionInput v-model="filters.owner"
							:label="t('libresign', 'Owner')"
							@update:value="onFilterChange">
							<template #icon>
								<AccountIcon :size="20" />
							</template>
						</NcActionInput>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'issued'"
							@update:modelValue="setStatusFilter('issued', $event)">
							<template #icon>
								<CheckCircleIcon :size="20" />
							</template>
							{{ t('libresign', 'Issued') }}
						</NcActionButton>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'revoked'"
							@update:modelValue="setStatusFilter('revoked', $event)">
							<template #icon>
								<CancelIcon :size="20" />
							</template>
							{{ t('libresign', 'Revoked') }}
						</NcActionButton>

						<NcActionButton type="radio"
							:model-value="filters.status?.value === 'expired'"
							@update:modelValue="setStatusFilter('expired', $event)">
							<template #icon>
								<ClockAlertIcon :size="20" />
							</template>
							{{ t('libresign', 'Expired') }}
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

			<div v-if="loading && entries.length === 0" class="crl-management__loading">
				<NcLoadingIcon :size="64" />
			</div>

			<div v-else-if="entries.length === 0" class="crl-management__empty">
				<NcEmptyContent :name="t('libresign', 'No CRL entries found')"
					:description="t('libresign', 'There are no certificate revocation list entries to display.')">
					<template #icon>
						<ShieldLockIcon :size="64" />
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
									<ShieldLockIcon :size="16" />
									{{ t('libresign', 'Root CA') }}
								</span>
								<span v-else-if="entry.certificate_type === 'intermediate'" class="certificate-type certificate-type--intermediate">
									<ShieldLockIcon :size="16" />
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
									type="error"
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
					<NcButton type="error" @click="proceedToRevokeDialog">
						{{ t('libresign', 'Yes, revoke CA') }}
					</NcButton>
				</div>
			</div>
		</NcDialog>

		<!-- Revoke Certificate Dialog -->
		<NcDialog v-if="revokeDialog.open"
			:name="t('libresign', 'Revoke Certificate')"
			:can-close="!revokeDialog.loading"
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
					<NcButton type="error"
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

<script>
import Magnify from 'vue-material-design-icons/Magnify.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import ClockAlertIcon from 'vue-material-design-icons/ClockAlert.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ShieldLockIcon from 'vue-material-design-icons/ShieldLock.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { useUserConfigStore } from '../../store/userconfig.js'

import NcActions from '@nextcloud/vue/components/NcActions'
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
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'CrlManagement',
	components: {
		Magnify,
		FilterIcon,
		AccountIcon,
		CheckCircleIcon,
		CancelIcon,
		ClockAlertIcon,
		CloseIcon,
		ShieldLockIcon,
		NcActions,
		NcActionButton,
		NcActionInput,
		NcActionSeparator,
		NcAppContent,
		NcAvatar,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
	},
	data() {
		const userConfigStore = useUserConfigStore()

		return {
			userConfigStore,
			entries: [],
			loading: false,
			loadingMore: false,
			page: 1,
			length: 50,
			total: 0,
			hasMore: true,
			filters: {
				serialNumber: userConfigStore.crl_filters.serialNumber || '',
				status: userConfigStore.crl_filters.status || null,
				owner: userConfigStore.crl_filters.owner || '',
			},
			sortBy: userConfigStore.crl_sort.sortBy || 'revoked_at',
			sortOrder: userConfigStore.crl_sort.sortOrder || 'DESC',
			caWarningDialog: {
				open: false,
				entry: null,
				typeLabel: '',
			},
			revokeDialog: {
				open: false,
				entry: null,
				reasonCode: { value: 0, label: '' },
				reasonText: '',
				loading: false,
			},
			statusOptions: [
				{ value: 'issued', label: this.t('libresign', 'Issued') },
				{ value: 'revoked', label: this.t('libresign', 'Revoked') },
				{ value: 'expired', label: this.t('libresign', 'Expired') },
			],
			reasonCodes: {
				0: this.t('libresign', 'Unspecified'),
				1: this.t('libresign', 'Key Compromise'),
				2: this.t('libresign', 'CA Compromise'),
				3: this.t('libresign', 'Affiliation Changed'),
				4: this.t('libresign', 'Superseded'),
				5: this.t('libresign', 'Cessation of Operation'),
				6: this.t('libresign', 'Certificate Hold'),
				8: this.t('libresign', 'Remove from CRL'),
				9: this.t('libresign', 'Privilege Withdrawn'),
				10: this.t('libresign', 'AA Compromise'),
			},
			reasonCodeOptions: [
				{ value: 0, label: this.t('libresign', 'Unspecified') },
				{ value: 1, label: this.t('libresign', 'Key Compromise') },
				{ value: 2, label: this.t('libresign', 'CA Compromise') },
				{ value: 3, label: this.t('libresign', 'Affiliation Changed') },
				{ value: 4, label: this.t('libresign', 'Superseded') },
				{ value: 5, label: this.t('libresign', 'Cessation of Operation') },
				{ value: 6, label: this.t('libresign', 'Certificate Hold') },
				{ value: 8, label: this.t('libresign', 'Remove from CRL') },
				{ value: 9, label: this.t('libresign', 'Privilege Withdrawn') },
				{ value: 10, label: this.t('libresign', 'AA Compromise') },
			],
		}
	},
	computed: {
		hasActiveFilters() {
			return !!(this.filters.serialNumber || this.filters.status || this.filters.owner)
		},
		activeFilterCount() {
			let count = 0
			if (this.filters.serialNumber) count++
			if (this.filters.status) count++
			if (this.filters.owner) count++
			return count
		},
	},
	mounted() {
		this.loadEntries()
	},
	methods: {
		async loadEntries(append = false) {
			if (!append) {
				this.loading = true
				this.page = 1
				this.entries = []
			} else {
				this.loadingMore = true
			}

			try {
				const params = {
					page: this.page,
					length: this.length,
				}

				if (this.filters.serialNumber) {
					params.serialNumber = this.filters.serialNumber
				}
				if (this.filters.status?.value) {
					params.status = this.filters.status.value
				}
				if (this.filters.owner) {
					params.owner = this.filters.owner
				}

				if (this.sortBy) {
					params.sortBy = this.sortBy
					params.sortOrder = this.sortOrder
				}

			const response = await axios.get(
				generateOcsUrl('/apps/libresign/api/{apiVersion}/crl/list', { apiVersion: 'v1' }),
				{ params }
			)

			const data = response.data.ocs.data

			if (append) {
				this.entries.push(...data.data)
			} else {
				this.entries = data.data
			}

			this.total = data.total
			this.hasMore = this.entries.length < this.total

			} catch (error) {
				console.error('Failed to load CRL entries:', error)
				console.error('Error response:', error.response)
				showError(this.t('libresign', 'Failed to load CRL entries'))
			} finally {
				this.loading = false
				this.loadingMore = false
			}
		},
		onFilterChange() {
			clearTimeout(this.filterTimeout)
			this.filterTimeout = setTimeout(() => {
				this.saveFilters()
				this.loadEntries()
			}, 500)
		},
		async saveFilters() {
			try {
				const filters = {
					serialNumber: this.filters.serialNumber,
					status: this.filters.status,
					owner: this.filters.owner,
				}
				await this.userConfigStore.update('crl_filters', filters)
			} catch (error) {
				console.error('Failed to save filters:', error)
			}
		},
		async saveSort() {
			try {
				const sort = {
					sortBy: this.sortBy,
					sortOrder: this.sortOrder,
				}
				await this.userConfigStore.update('crl_sort', sort)
			} catch (error) {
				console.error('Failed to save sort:', error)
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
				this.loadEntries(true)
			}
		},
		formatDate(dateString) {
			if (!dateString) {
				return '-'
			}
			const date = new Date(dateString)
			return date.toLocaleString()
		},
		getReasonText(reasonCode) {
			if (reasonCode === null || reasonCode === undefined) {
				return '-'
			}
			return this.reasonCodes[reasonCode] || this.t('libresign', 'Unknown')
		},
		getCertificateTypeLabel(type) {
			const labels = {
				root: this.t('libresign', 'Root Certificate (CA)'),
				intermediate: this.t('libresign', 'Intermediate Certificate (CA)'),
				leaf: this.t('libresign', 'User Certificate'),
			}
			return labels[type] || type
		},
		clearFilters() {
			this.filters.serialNumber = ''
			this.filters.status = null
			this.filters.owner = ''
			this.saveFilters()
			this.loadEntries()
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
			this.loadEntries()
		},
		openRevokeDialog(entry) {
			if (entry.certificate_type === 'root' || entry.certificate_type === 'intermediate') {
				this.caWarningDialog.open = true
				this.caWarningDialog.entry = entry
				this.caWarningDialog.typeLabel = entry.certificate_type === 'root' ? 'ROOT' : 'INTERMEDIATE'
				return
			}

			this.revokeDialog.open = true
			this.revokeDialog.entry = entry
			this.revokeDialog.reasonCode = this.reasonCodeOptions[0]
			this.revokeDialog.reasonText = ''
		},
		closeCaWarningDialog() {
			this.caWarningDialog.open = false
			this.caWarningDialog.entry = null
			this.caWarningDialog.typeLabel = ''
		},
		proceedToRevokeDialog() {
			const entry = this.caWarningDialog.entry
			this.closeCaWarningDialog()

			this.revokeDialog.open = true
			this.revokeDialog.entry = entry
			this.revokeDialog.reasonCode = this.reasonCodeOptions[0]
			this.revokeDialog.reasonText = ''
		},
		closeRevokeDialog() {
			this.revokeDialog.open = false
			this.revokeDialog.entry = null
			this.revokeDialog.reasonCode = null
			this.revokeDialog.reasonText = ''
			this.revokeDialog.loading = false
		},
		async confirmRevoke() {
			this.revokeDialog.loading = true

			try {
				const response = await axios.post(
					generateOcsUrl('/apps/libresign/api/{apiVersion}/crl/revoke', { apiVersion: 'v1' }),
					{
						serialNumber: this.revokeDialog.entry.serial_number,
						reasonCode: this.revokeDialog.reasonCode?.value ?? 0,
						reasonText: this.revokeDialog.reasonText || null,
					}
				)
				if (response.data.ocs.data.success) {
					showSuccess(this.t('libresign', 'Certificate revoked successfully'))
					this.closeRevokeDialog()
					this.loadEntries()
				} else {
					showError(response.data.ocs.data.message || this.t('libresign', 'Failed to revoke certificate'))
				}
			} catch (error) {
				console.error('Failed to revoke certificate:', error)
				const message = error.response?.data?.ocs?.data?.message || this.t('libresign', 'An error occurred while revoking the certificate')
				showError(message)
			} finally {
				this.revokeDialog.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.crl-management {
	padding: 20px;
	height: 100%;
	display: flex;
	flex-direction: column;

	&__toolbar {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 8px;
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
	display: inline-block;
	padding: 4px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
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
	gap: 4px;
	padding: 4px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;

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

