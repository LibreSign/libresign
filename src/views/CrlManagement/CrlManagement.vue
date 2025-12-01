<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="crl-management">
			<div class="crl-management__filters">
				<NcTextField :value.sync="filters.serialNumber"
					:label="t('libresign', 'Serial Number')"
					:placeholder="t('libresign', 'Search by serial number...')"
					@update:value="onFilterChange">
					<template #trailing-button-icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>

				<NcSelect v-model="filters.status"
					:input-label="t('libresign', 'Status')"
					:options="statusOptions"
					:placeholder="t('libresign', 'Filter by status')"
					:clearable="true"
					@input="onFilterChange">
					<template #selected-option="option">
						{{ option.label }}
					</template>
				</NcSelect>

				<NcTextField :value.sync="filters.owner"
					:label="t('libresign', 'Owner')"
					:placeholder="t('libresign', 'Filter by owner...')"
					@update:value="onFilterChange" />
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
							<th class="crl-table__cell--spacer" />
							<th class="sortable" @click="sortColumn('owner')">
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
							<th>{{ t('libresign', 'Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(entry, index) in entries" :key="`${entry.serial_number}-${entry.issued_at}-${index}`" class="crl-table__row">
							<td class="crl-table__cell--spacer">
								<NcAvatar v-if="entry.certificate_type === 'leaf'"
									:user="entry.owner"
									:size="32"
									:display-name="entry.owner"
									:disable-menu="true" />
							</td>
							<td>{{ entry.owner }}</td>
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
							<td>
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

					<NcTextArea :value.sync="revokeDialog.reasonText"
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
import ShieldLockIcon from 'vue-material-design-icons/ShieldLock.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

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
		ShieldLockIcon,
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
		return {
			entries: [],
			loading: false,
			loadingMore: false,
			page: 1,
			length: 50,
			total: 0,
			hasMore: true,
			filters: {
				serialNumber: '',
				status: null,
				owner: '',
			},
			sortBy: 'revoked_at',
			sortOrder: 'DESC',
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
				this.loadEntries()
			}, 500)
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
			this.loadEntries()
		},
		openRevokeDialog(entry) {
			// If it's a CA certificate (root or intermediate), require extra confirmation
			if (entry.certificate_type === 'root' || entry.certificate_type === 'intermediate') {
				const typeLabel = entry.certificate_type === 'root' ? 'ROOT' : 'INTERMEDIATE'
				const confirmed = confirm(
					this.t('libresign', 'You are about to revoke a {type} CERTIFICATE AUTHORITY. This is a critical operation that may invalidate certificates issued by this CA. Are you absolutely sure you want to proceed?', { type: typeLabel })
				)
				if (!confirmed) {
					return
				}
			}

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

	&__header {
		margin-bottom: 20px;

		h2 {
			font-size: 24px;
			font-weight: 600;
		}
	}

	&__filters {
		display: flex;
		gap: 12px;
		margin-bottom: 20px;
		flex-wrap: wrap;

		> * {
			flex: 1;
			min-width: 200px;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		align-items: center;
		flex: 1;
	}

	&__empty {
		flex: 1;
		display: flex;
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
		display: flex;
		justify-content: center;
		padding: 20px;
	}

	&__end {
		text-align: center;
		padding: 20px;
		color: var(--color-text-maxcontrast);
		font-size: 14px;
	}
}

.crl-table {
	width: 100%;
	border-collapse: collapse;

	thead {
		position: sticky;
		top: 0;
		background-color: var(--color-main-background);
		z-index: 1;

		th {
			padding: 12px;
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
		}
	}

	tbody {
		tr {
			border-bottom: 1px solid var(--color-border);

			&:hover {
				background-color: var(--color-background-hover);
			}
		}

		td {
			padding: 12px;
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
		width: 44px;
		min-width: 44px;
		max-width: 44px;
		padding: 6px;
		text-align: center;
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

