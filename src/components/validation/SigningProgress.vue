<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signing-progress">
		<h1>{{ t('libresign', 'Signing document...') }}</h1>
		<NcNoteCard type="info">
			<div class="note-header">
				<NcLoadingIcon :size="20" />
				<span>{{ t('libresign', 'Your document is being signed. This may take a few moments.') }}</span>
			</div>
			<div v-if="progress" class="progress-body">
				<div class="summary">
					<div class="metric">
						<div class="metric-label">{{ t('libresign', 'Signed') }}</div>
						<div class="metric-value text-success">{{ progress.signed }} / {{ progress.total }}</div>
					</div>
					<div class="metric">
						<div class="metric-label">{{ t('libresign', 'In progress') }}</div>
						<div class="metric-value text-warning">{{ progress.inProgress ?? 0 }}</div>
					</div>
					<div class="metric">
						<div class="metric-label">{{ t('libresign', 'Pending') }}</div>
						<div class="metric-value text-muted">{{ progress.pending }}</div>
					</div>
				</div>

				<div v-if="progress.files?.length" class="file-list">
					<div class="file-row header">
						<span>{{ t('libresign', 'File') }}</span>
						<span>{{ t('libresign', 'Status') }}</span>
					</div>
					<div v-for="file in progress.files" :key="file.id" class="file-row">
						<span class="file-name">{{ file.name }}</span>
						<span :class="['status-pill', `status-${getStatusMeta(file.status).class}`]">
							<NcIconSvgWrapper :path="getStatusMeta(file.status).icon" />
							{{ getStatusMeta(file.status).label }}
						</span>
					</div>
				</div>
			</div>
		</NcNoteCard>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { buildStatusMap } from '../../utils/fileStatus.js'

export default {
	name: 'SigningProgress',
	components: {
		NcLoadingIcon,
		NcNoteCard,
		NcIconSvgWrapper,
	},
	props: {
		uuid: {
			type: String,
			required: true,
		},
	},
	emits: ['completed', 'error', 'status-changed'],
	setup() {
		return { t }
	},
	data() {
		return {
			isPolling: false,
			pollingInterval: null,
			progress: null,
			currentStatusCode: null,
			statusMap: buildStatusMap(),
		}
	},
	watch: {
		uuid(newUuid, oldUuid) {
			if (newUuid && newUuid !== oldUuid) {
				this.startPolling()
			}
		},
	},
	mounted() {
		if (this.uuid) {
			this.startPolling()
		}
	},
	beforeDestroy() {
		this.stopPolling()
	},
	methods: {
		startPolling() {
			if (this.isPolling || !this.uuid) {
				return
			}
			this.isPolling = true
			this.pollFileProgress()
			this.fetchProgressFromValidation()
		},
		stopPolling() {
			this.isPolling = false
			if (this.pollingInterval) {
				clearTimeout(this.pollingInterval)
				this.pollingInterval = null
			}
		},
		async pollFileProgress() {
			if (!this.isPolling) {
				return
			}

		try {
			const { data } = await axios.get(
				generateOcsUrl(`/apps/libresign/api/v1/file/progress/${this.uuid}`),
				{
					params: { timeout: 0 },
					timeout: 35000,
				}
			)

			const responseData = data.ocs?.data || data
			const status = responseData?.status
			this.currentStatusCode = responseData?.statusCode ?? null

			this.$emit('status-changed', status)

			if (!this.progress) {
				await this.fetchProgressFromValidation()
			}

			if (status === 'SIGNED') {
				this.stopPolling()
				this.$emit('completed')
			} else if (status === 'ERROR') {
				this.stopPolling()
				this.$emit('error', t('libresign', 'Signing failed. Please try again.'))
			} else if (status === 'SIGNING_IN_PROGRESS') {
				this.pollingInterval = setTimeout(() => this.pollFileProgress(), 1000)
			} else {
				this.pollingInterval = setTimeout(() => this.pollFileProgress(), 2000)
			}
		} catch (error) {
			if (error.code === 'ECONNABORTED') {
				this.pollingInterval = setTimeout(() => this.pollFileProgress(), 100)
			} else {
				this.stopPolling()
				this.$emit('error', t('libresign', 'Failed to check signing progress'))
			}
		}
	},
	async fetchProgressFromValidation() {
		try {
			const { data } = await axios.get(
				generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${this.uuid}`)
			)
			const doc = data.ocs?.data || data
			const derived = this.buildProgressFromValidation(doc)
			if (derived) {
				this.progress = derived
			}
		} catch (e) {
			// Fallback failed, continue polling
		}
	},
	buildProgressFromValidation(doc) {
		if (!doc) {
			return null
		}
		if (doc.nodeType === 'envelope' || (Array.isArray(doc.files) && doc.files.length > 0)) {
			const files = doc.files.map(f => ({ id: f.id, name: f.name, status: f.status }))
			const total = files.length
			const signed = files.filter(f => f.status === 3).length
			const inProgress = files.filter(f => f.status === 5).length
			return {
				total,
				signed,
				pending: total - signed - inProgress,
				inProgress,
				files,
			}
		}

		if (Array.isArray(doc.signers)) {
			const total = doc.signers.length
			const signed = doc.signers.filter(s => !!s.signed).length
			return {
				total,
				signed,
				pending: total - signed,
			}
		}
		return null
	},
	async fetchProgressSnapshot(fileId, currentStatus) {
		try {
			const { data } = await axios.get(
				generateOcsUrl(`/apps/libresign/api/v1/file/${fileId}/wait-status`),
				{ params: { currentStatus, timeout: 0 } }
			)
			const responseData = data.ocs?.data || data
			if (responseData?.progress) {
				this.progress = responseData.progress
			}
		} catch (e) {
			// Snapshot load failed, continue polling
		}
	},
	getStatusMeta(status) {
		const meta = this.statusMap[status]
		if (meta) {
			return meta
		}
		return { label: t('libresign', 'Unknown'), icon: mdiHelpCircle }
	},
	},
}
</script>

<style lang="scss" scoped>
.signing-progress {
	max-width: 800px;
	margin: 0 auto;
	padding: 20px;

	h1 {
		font-size: 24px;
		font-weight: bold;
		color: var(--color-main-text);
	}

	.note-header {
		display: flex;
		align-items: center;
		gap: 10px;
		color: var(--color-main-text);
	}

	.progress-body {
		margin-top: 16px;
		display: flex;
		flex-direction: column;
		gap: 12px;

		.summary {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
			gap: 12px;
		}

		.metric {
			background: var(--color-background-hover);
			padding: 10px;
			border-radius: 8px;
			border: 1px solid var(--color-border);
		}

		.metric-label {
			font-size: 12px;
			color: var(--color-text-lighter);
		}

		.metric-value {
			font-weight: 600;
		}

		.file-list {
			display: flex;
			flex-direction: column;
			gap: 8px;

			.file-row {
				display: grid;
				grid-template-columns: 2fr 1fr;
				gap: 8px;
				align-items: center;
				padding: 8px 10px;
				border-radius: 6px;
				background: var(--color-main-background);
				border: 1px solid var(--color-border);

				&.header {
					background: transparent;
					border: none;
					color: var(--color-text-lighter);
					font-weight: 600;
				}
			}

			.file-name {
				word-break: break-word;
			}

			.status-pill {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 4px 10px;
				border-radius: 999px;
				font-size: 12px;
				font-weight: 600;
				justify-content: flex-start;

				&.status-draft { background: #f0f0f0; color: #555; }
				&.status-ready { background: #e3f2fd; color: #1976d2; }
				&.status-partial { background: #e3f2fd; color: #1976d2; }
				&.status-signed { background: #e8f5e9; color: #2e7d32; }
				&.status-deleted { background: #ffebee; color: #c62828; }
				&.status-signing { background: #fff3e0; color: #f57c00; }
				&.status-unknown { background: #eceff1; color: #455a64; }
			}
		}
	}
}
</style>
