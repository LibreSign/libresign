<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signing-progress">
		<h1>{{ getHeaderTitle() }}</h1>
		<NcNoteCard v-if="generalErrorMessage" type="error" class="general-error-card">
			{{ generalErrorMessage }}
		</NcNoteCard>
		<NcNoteCard type="info">
			<div class="note-header">
				<NcLoadingIcon v-if="isPolling" :size="20" />
				<span>{{ getHeaderSubtitle() }}</span>
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
					<div v-for="file in progress.files" :key="file.id" class="file-item">
						<div class="file-row">
							<span class="file-name">{{ file.name }}</span>
							<span :class="['status-pill', `status-${getFileStatusMeta(file).class}`]">
								<NcIconSvgWrapper :path="getFileStatusMeta(file).icon" />
								{{ getFileStatusMeta(file).label }}
							</span>
						</div>
						<NcNoteCard v-if="file.error && file.error.message" type="error" class="file-error-card">
							{{ file.error.message }}
						</NcNoteCard>
					</div>
				</div>
			</div>
		</NcNoteCard>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiAlertCircleOutline,
	mdiHelpCircle,
} from '@mdi/js'
import { buildStatusMap } from '../../utils/fileStatus.js'

export default {
	name: 'SigningProgress',
	components: {
		NcLoadingIcon,
		NcNoteCard,
		NcIconSvgWrapper,
	},
	props: {
		signRequestUuid: {
			type: String,
			required: true,
			description: 'Sign request UUID (not file UUID)',
	},
	},
	emits: ['completed', 'error', 'status-changed', 'file-errors'],
	setup() {
		return { t,
			mdiAlertCircleOutline,
			mdiHelpCircle,}
	},
	data() {
		return {
			isPolling: false,
			pollingInterval: null,
			progress: null,
			generalErrorMessage: null,
			statusMap: buildStatusMap(),
			pollTimeoutSeconds: 30,
		}
	},
	watch: {
		signRequestUuid(newUuid, oldUuid) {
			if (newUuid && newUuid !== oldUuid) {
				this.startPolling()
			}
		},
	},
	mounted() {
		if (this.signRequestUuid) {
			this.startPolling()
		}
	},
	beforeDestroy() {
		this.stopPolling()
	},
	methods: {
		getHeaderTitle() {
			const { allProcessed, errorCount } = this.getProgressState()
			if (!this.isPolling && allProcessed && errorCount > 0) {
				return t('libresign', 'Signing finished with errors')
			}
			return t('libresign', 'Signing document...')
		},
		getHeaderSubtitle() {
			const { allProcessed, errorCount } = this.getProgressState()
			if (!this.isPolling && allProcessed && errorCount > 0) {
				return t('libresign', 'Some files could not be signed. Please review the errors below.')
			}
			return t('libresign', 'Your document is being signed. This may take a few moments.')
		},
		startPolling() {
			if (this.isPolling || !this.signRequestUuid) {
				return
			}
			this.isPolling = true
			this.pollFileProgress()
			if (!this.progress) {
				this.fetchProgressFromValidation()
			}
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
					generateOcsUrl(`/apps/libresign/api/v1/file/progress/${this.signRequestUuid}`),
					{
						params: { timeout: this.pollTimeoutSeconds },
						timeout: (this.pollTimeoutSeconds + 5) * 1000,
					}
				)

				const responseData = data.ocs?.data || data
				const status = responseData?.status
				const statusCode = responseData?.statusCode ?? null

				this.$emit('status-changed', status)

				if (responseData?.progress) {
					this.progress = responseData.progress
				} else if (!this.progress) {
					await this.fetchProgressFromValidation()
				}

				const {
					allProcessed,
					errorCount,
					fileErrors,
					hasFileErrors,
					hasPendingWork,
				} = this.getProgressState()

				if (hasFileErrors) {
					this.$emit('file-errors', fileErrors)
				}

				if (responseData?.error && !hasFileErrors) {
					this.stopPolling()
					this.generalErrorMessage = responseData.error.message || t('libresign', 'Signing failed. Please try again.')
					this.$emit('error', this.generalErrorMessage)
					return
				}

				if (responseData?.file) {
					this.stopPolling()
					this.$emit('completed', responseData.file)
					return
				}

				if (status === 'ABLE_TO_SIGN' || statusCode === 1) {
					this.stopPolling()
					this.$emit('error', t('libresign', 'Document ready to sign'))
					return
				}

				if (allProcessed && errorCount > 0) {
					this.stopPolling()
					const firstError = fileErrors.find(file => file?.error?.message)?.error?.message
					this.$emit('error', firstError || t('libresign', 'Signing failed. Please try again.'))
					return
				}

				const totalCount = this.progress?.total ?? 0
				const isSingleFile = totalCount === 1
				if (status === 'SIGNED' && (!hasPendingWork || isSingleFile)) {
					this.stopPolling()
					this.$emit('completed')
					return
				}

				if (status === 'ERROR' && !hasPendingWork && !hasFileErrors) {
					this.stopPolling()
					this.generalErrorMessage = t('libresign', 'Signing failed. Please try again.')
					this.$emit('error', this.generalErrorMessage)
					return
				}

				this.pollingInterval = setTimeout(() => this.pollFileProgress(), 0)
			} catch (error) {
				if (error.code === 'ECONNABORTED') {
					this.pollingInterval = setTimeout(() => this.pollFileProgress(), 0)
				} else {
					this.stopPolling()
					this.$emit('error', t('libresign', 'Failed to check signing progress'))
				}
			}
		},
	async fetchProgressFromValidation() {
		try {
			const { errorCount, hasFileErrors } = this.getProgressState()
			if (hasFileErrors || errorCount > 0) {
				return
			}
			const { data } = await axios.get(
				generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${this.signRequestUuid}`)
			)
			const doc = data.ocs?.data || data
			const derived = this.buildProgressFromValidation(doc)
			if (derived && !hasFileErrors && errorCount === 0) {
				this.progress = derived
			}
		} catch (e) {
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
	getStatusMeta(status) {
		const meta = this.statusMap[status]
		if (meta) {
			return meta
		}
		return { label: t('libresign', 'Unknown'), icon: mdiHelpCircle }
	},
	getFileStatusMeta(file) {
		if (file?.error) {
			return {
				label: t('libresign', 'Error'),
				icon: mdiAlertCircleOutline,
				class: 'error',
			}
		}
		return this.getStatusMeta(file?.status)
	},
	getProgressState() {
		const progress = this.progress
		const progressReady = !!progress && (progress.total ?? 0) > 0
		const total = progress?.total ?? 0
		const signed = progress?.signed ?? 0
		const inProgress = progress?.inProgress ?? 0
		const fileErrors = Array.isArray(progress?.files)
			? progress.files.filter(file => file?.error)
			: []
		const errorCount = progress?.errors ?? fileErrors.length
		const processedCount = signed + errorCount
		const pending = progress?.pending ?? Math.max(total - processedCount - inProgress, 0)
		const allProcessed = progressReady && processedCount >= total
		const hasPendingWork = !progressReady || pending > 0 || inProgress > 0 || !allProcessed
		const hasFileErrors = fileErrors.length > 0

		return {
			allProcessed,
			errorCount,
			fileErrors,
			hasFileErrors,
			hasPendingWork,
		}
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
	.general-error-card {
		margin: 12px 0;
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

			.file-item {
				display: flex;
				flex-direction: column;
				gap: 4px;
			}

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
				&.status-error { background: #ffebee; color: #c62828; }
				&.status-signing { background: #fff3e0; color: #f57c00; }
				&.status-unknown { background: #eceff1; color: #455a64; }
			}

			.file-error-card {
				margin: 0 10px;
				font-size: 13px;
			}
		}
	}
}
</style>
