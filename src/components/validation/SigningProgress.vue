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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiAlertCircleOutline,
	mdiHelpCircle,
} from '@mdi/js'
import { buildStatusMap } from '../../utils/fileStatus.js'
import type { components } from '../../types/openapi/openapi'
import type { ValidationFileRecord } from '../../types/index'

type ProgressFile = components['schemas']['ProgressFile']
type ProgressState = components['schemas']['ProgressPayload']

type ValidationDocument = Partial<Pick<ValidationFileRecord, 'nodeType' | 'files' | 'signers'>>

type StatusMeta = {
	label: string
	icon?: string
	class?: string
}

type PollResponse = {
	status?: components['schemas']['ProgressResponse']['status']
	statusCode?: components['schemas']['ProgressResponse']['statusCode'] | null
	progress?: ProgressState
	error?: components['schemas']['ProgressResponse']['error']
	file?: components['schemas']['ProgressResponse']['file'] | unknown
}

defineOptions({
	name: 'SigningProgress',
})

const props = defineProps<{
	signRequestUuid: string
}>()

const emit = defineEmits<{
	(e: 'completed', file?: unknown): void
	(e: 'error', message: string): void
	(e: 'status-changed', status: string | undefined): void
	(e: 'file-errors', fileErrors: ProgressFile[]): void
}>()

const isPolling = ref(false)
const pollingInterval = ref<ReturnType<typeof setTimeout> | null>(null)
const progress = ref<ProgressState | null>(null)
const generalErrorMessage = ref<string | null>(null)
const statusMap = ref<Record<string, StatusMeta>>(buildStatusMap() as unknown as Record<string, StatusMeta>)
const pollTimeoutSeconds = ref(30)

function getHeaderTitle() {
	const { allProcessed, errorCount } = getProgressState()
	if (!isPolling.value && allProcessed && errorCount > 0) {
		return t('libresign', 'Signing finished with errors')
	}
	return t('libresign', 'Signing document...')
}

function getHeaderSubtitle() {
	const { allProcessed, errorCount } = getProgressState()
	if (!isPolling.value && allProcessed && errorCount > 0) {
		return t('libresign', 'Some files could not be signed. Please review the errors below.')
	}
	return t('libresign', 'Your document is being signed. This may take a few moments.')
}

function stopPolling() {
	isPolling.value = false
	if (pollingInterval.value) {
		clearTimeout(pollingInterval.value)
		pollingInterval.value = null
	}
}

async function fetchProgressFromValidation() {
	try {
		const { errorCount, hasFileErrors } = getProgressState()
		if (hasFileErrors || errorCount > 0) {
			return
		}
		const { data } = await axios.get(
			generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${props.signRequestUuid}`)
		)
		const doc = data.ocs?.data || data
		const derived = buildProgressFromValidation(doc)
		if (derived && !hasFileErrors && errorCount === 0) {
			progress.value = derived
		}
	} catch (error) {
	}
}

async function pollFileProgress() {
	if (!isPolling.value) {
		return
	}

	try {
		const { data } = await axios.get(
			generateOcsUrl(`/apps/libresign/api/v1/file/progress/${props.signRequestUuid}`),
			{
				params: { timeout: pollTimeoutSeconds.value },
				timeout: (pollTimeoutSeconds.value + 5) * 1000,
			}
		)

		const responseData: PollResponse = data.ocs?.data || data
		const status = responseData?.status
		const statusCode = responseData?.statusCode ?? null

		emit('status-changed', status)

		if (responseData?.progress) {
			progress.value = responseData.progress
		} else if (!progress.value) {
			await fetchProgressFromValidation()
		}

		const {
			allProcessed,
			errorCount,
			fileErrors,
			hasFileErrors,
			hasPendingWork,
		} = getProgressState()

		if (hasFileErrors) {
			emit('file-errors', fileErrors)
		}

		if (responseData?.error && !hasFileErrors) {
			stopPolling()
			generalErrorMessage.value = responseData.error.message || t('libresign', 'Signing failed. Please try again.')
			emit('error', generalErrorMessage.value)
			return
		}

		if (responseData?.file) {
			stopPolling()
			emit('completed', responseData.file)
			return
		}

		if (status === 'ABLE_TO_SIGN' || statusCode === 1) {
			stopPolling()
			emit('error', t('libresign', 'Document ready to sign'))
			return
		}

		if (allProcessed && errorCount > 0) {
			stopPolling()
			const firstError = fileErrors.find(file => file?.error?.message)?.error?.message
			emit('error', firstError || t('libresign', 'Signing failed. Please try again.'))
			return
		}

		const totalCount = progress.value?.total ?? 0
		const isSingleFile = totalCount === 1
		if (status === 'SIGNED' && (!hasPendingWork || isSingleFile)) {
			stopPolling()
			emit('completed')
			return
		}

		if (status === 'ERROR' && !hasPendingWork && !hasFileErrors) {
			stopPolling()
			generalErrorMessage.value = t('libresign', 'Signing failed. Please try again.')
			emit('error', generalErrorMessage.value)
			return
		}

		pollingInterval.value = setTimeout(() => pollFileProgress(), 0)
	} catch (error: unknown) {
		if ((error as { code?: string })?.code === 'ECONNABORTED') {
			pollingInterval.value = setTimeout(() => pollFileProgress(), 0)
		} else {
			stopPolling()
			emit('error', t('libresign', 'Failed to check signing progress'))
		}
	}
}

function startPolling() {
	if (isPolling.value || !props.signRequestUuid) {
		return
	}
	isPolling.value = true
	void pollFileProgress()
	if (!progress.value) {
		void fetchProgressFromValidation()
	}
}

function buildProgressFromValidation(doc: ValidationDocument | null | undefined): ProgressState | null {
	if (!doc) {
		return null
	}
	if (doc.nodeType === 'envelope') {
		const files = (doc.files ?? [])
			.filter((file): file is typeof file & { id: number; name: string; status: number } => {
				return typeof file.id === 'number'
					&& typeof file.name === 'string'
					&& typeof file.status === 'number'
			})
			.map(file => ({
				id: file.id,
				name: file.name,
				status: file.status,
				statusText: getStatusMeta(file.status).label,
			}))
		const total = files.length
		const signed = files.filter(file => file.status === 3).length
		const inProgress = files.filter(file => file.status === 5).length
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
		const signed = doc.signers.filter(signer => !!signer.signed).length
		return {
			total,
			signed,
			inProgress: 0,
			pending: total - signed,
		}
	}
	return null
}

function getStatusMeta(status?: number) {
	const meta = statusMap.value[String(status)]
	if (meta) {
		return meta
	}
	return { label: t('libresign', 'Unknown'), icon: mdiHelpCircle }
}

function getFileStatusMeta(file?: ProgressFile) {
	if (file?.error) {
		return {
			label: t('libresign', 'Error'),
			icon: mdiAlertCircleOutline,
			class: 'error',
		}
	}
	return getStatusMeta(file?.status)
}

function getProgressState() {
	const currentProgress = progress.value
	const progressReady = !!currentProgress && (currentProgress.total ?? 0) > 0
	const total = currentProgress?.total ?? 0
	const signed = currentProgress?.signed ?? 0
	const inProgress = currentProgress?.inProgress ?? 0
	const fileErrors = Array.isArray(currentProgress?.files)
		? currentProgress.files.filter(file => file?.error)
		: []
	const errorCount = currentProgress?.errors ?? fileErrors.length
	const processedCount = signed + errorCount
	const pending = currentProgress?.pending ?? Math.max(total - processedCount - inProgress, 0)
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
}

watch(() => props.signRequestUuid, (newUuid, oldUuid) => {
	if (newUuid && newUuid !== oldUuid) {
		startPolling()
	}
})

onMounted(() => {
	if (props.signRequestUuid) {
		startPolling()
	}
})

onBeforeUnmount(() => {
	stopPolling()
})

defineExpose({
	isPolling,
	pollingInterval,
	progress,
	generalErrorMessage,
	statusMap,
	pollTimeoutSeconds,
	getHeaderTitle,
	getHeaderSubtitle,
	startPolling,
	stopPolling,
	pollFileProgress,
	fetchProgressFromValidation,
	buildProgressFromValidation,
	getStatusMeta,
	getFileStatusMeta,
	getProgressState,
})
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
