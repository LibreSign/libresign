<template>
	<div class="workflow-header-card">
		<!-- ========================================= -->
		<!-- FILE IDENTITY -->
		<!-- ========================================= -->
		<div class="workflow-file-identity">
			<div class="workflow-requester" v-if="file?.requested_by?.displayName && !state?.canSave">
				Requested by {{ file?.requested_by?.displayName }}
			</div>
			<div class="workflow-file-title-row">
				<div class="file-icon-wrapper">
					<NcIconSvgWrapper
						class="workflow-file-icon"
						:path="fileIcon"
						:size="22" />
				</div>

				<div class="workflow-file-heading">
					<span class="workflow-file-label">
						{{ state?.isEnvelope ? 'Envelope' : 'Document' }}
					</span>

					<div class="workflow-file-title-row">
						<h2 class="workflow-file-name">
							{{ file?.name }}
						</h2>
					</div>
				</div>
			</div>

			<div
				class="workflow-status-pill"
				:class="`workflow-status-pill--${state.statusVariant}`">
				<span class="workflow-status-dot" />

				<span>
					{{ state.statusLabel }}
				</span>
			</div>
			<span class="workflow-status-subtitle">
				{{ state.statusSubtitle }}
			</span>
		</div>

		<!-- ========================================= -->
		<!-- META -->
		<!-- ========================================= -->
		<div class="workflow-meta-grid">
			<div class="workflow-meta-item">
				<span class="workflow-meta-label">
					{{ t('libresign', 'Pages') }}
				</span>

				<strong class="workflow-meta-value">
					{{ pages }}
				</strong>
			</div>

			<div class="workflow-meta-item">
				<span class="workflow-meta-label">
					{{ t('libresign', 'Created') }}
				</span>

				<strong class="workflow-meta-value">
					{{ formattedDate }}
				</strong>
			</div>

			<div class="workflow-meta-item">
				<span class="workflow-meta-label">
					{{ t('libresign', 'Signers') }}
				</span>

				<strong class="workflow-meta-value">
					{{ state.signersCount }}
				</strong>
			</div>
		</div>

		<!-- ========================================= -->
		<!-- FILE ACTIONS -->
		<!-- ========================================= -->
		<div class="workflow-file-actions">
			<NcButton
				v-if="state.isEnvelope"
				class="workflow-action-button"
				variant="tertiary"
				size="small"
				wide
				@click="$emit('manage-files')">
				<template #icon>
					<NcIconSvgWrapper
						:path="mdiFolder"
						:size="20" />
				</template>

				{{ t('libresign', 'Manage files ({count})', {
					count: file?.filesCount || 0,
				}) }}
			</NcButton>

			<NcButton
				v-else
				class="workflow-action-button"
				variant="tertiary"
				size="small"
				wide
				@click="$emit('open-file')">
				<template #icon>
					<NcIconSvgWrapper
						:path="mdiFile"
						:size="20" />
				</template>

				{{ t('libresign', 'View file') }}
			</NcButton>

			<NcButton
				v-if="canValidate"
				class="workflow-action-button"
				variant="tertiary"
				size="small"
				wide
				@click="$emit('validate-file')">
				<template #icon>
					<NcIconSvgWrapper
						:path="mdiCheckDecagramOutline"
						:size="20" />
				</template>

				{{ t('libresign', 'Validate') }}
			</NcButton>
		</div>

		<div class="workflow-divider" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiCheckDecagramOutline,
	mdiFileDocumentOutline as mdiFile,
	mdiFolder,
} from '@mdi/js'

import type {
	PublicFileState,
} from '../../../store/files'

import type {
	WorkflowState,
} from '../../../composables/useWorkflowState'

defineOptions({
	name: 'WorkflowHeaderCard',
})

const props = defineProps<{
	file: PublicFileState | null
	state: WorkflowState
	canValidate: boolean
}>()

defineEmits<{
	(e: 'open-file'): void
	(e: 'manage-files'): void
	(e: 'validate-file'): void
}>()

const fileIcon = computed(() => {
	return props.state.isEnvelope
		? mdiFolder
		: mdiFile
})

const pages = computed(() => {
	/**
	 * Envelope:
	 * sum validated page counts
	 */
	if (props.state.isEnvelope) {
		return props.file?.files?.reduce((total, currentFile) => {
			const filePages =
				currentFile.metadata?.p
				?? currentFile.totalPages
				?? 0

			return total + filePages
		}, 0) ?? 0
	}

	/**
	 * Single file:
	 * validated metadata first
	 */
	return (
		props.file?.metadata?.p
		?? 0
	)
})

function hasCreatedAt(f: any): f is { created_at: string } {
  return 'created_at' in f
}

const formattedDate = computed(() => {
  const f = props.file
  if (!f || !hasCreatedAt(f)) return 'Draft'
  return new Date(f.created_at).toLocaleDateString()
})
</script>

<style scoped lang="scss">
.workflow-header-card {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 4px 0 0;
}

.workflow-header-card .workflow-file-actions .button-vue {
	border: 1px solid var(--color-border);
}

.workflow-file-identity {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.workflow-requester {
	font-size: 12px;
	font-weight: 600;

	letter-spacing: 0.01em;

	color: var(--color-text-maxcontrast);

	opacity: 0.58;
}

.workflow-file-title-row {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.workflow-file-icon {
	flex-shrink: 0;
	opacity: 0.8;
}

.workflow-file-heading {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.workflow-file-label {
	font-size: 12px;
	font-weight: 800;
	letter-spacing: 0.4px;
	text-transform: uppercase;

	color: var(--color-text-maxcontrast);
	opacity: 0.6;
}

.workflow-file-name {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
	line-height: 1.1;
	word-break: break-word;
}

.file-icon-wrapper {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 48px;
	height: 48px;

	border-radius: 14px;

	background:
		linear-gradient(
			180deg,
			rgba(34, 197, 94, 0.14),
			rgba(34, 197, 94, 0.09)
		);

	color: #15803d;

	border:
		1px solid rgba(34, 197, 94, 0.08);

	flex-shrink: 0;
}

.workflow-status-pill {
	display: inline-flex;
	align-items: center;
	gap: 8px;

	width: fit-content;

	padding: 6px 12px;
	border-radius: 999px;

	font-size: 12px;
	font-weight: 600;
	line-height: 1;
}

.workflow-status-pill--warning {
	background: #fff2cc;
	color: #a15c00;
}

.workflow-status-pill--success {
	background: #dff5e4;
	color: #1d7a3d;
}

.workflow-status-pill--info {
	background: #e7f1ff;
	color: #1d4ed8;
}

.workflow-status-pill--error {
	background: #fde8e8;
	color: #c81e1e;
}

.workflow-status-pill--neutral {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}

.workflow-status-dot {
	width: 8px;
	height: 8px;
	border-radius: 999px;
	background: currentColor;
}

.workflow-status-subtitle {
	margin-top: -4px;
    margin-bottom: -2px;
    padding-left: 8px;
    font-size: 13px;
    opacity: 0.58;
    font-weight: 500;
    line-height: 1.4;
    color: var(--color-text-maxcontrast);
}

.workflow-meta-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 16px;
}

.workflow-meta-item {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.workflow-meta-label {
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	opacity: 0.8;
}

.workflow-meta-value {
	font-size: 12px;
	font-weight: 700;
	line-height: 1;
}

.workflow-file-actions {
	display: flex;
	gap: 12px;
}

.workflow-action-button {
	flex: 1;
}

.workflow-divider {
	width: 100%;
	height: 1px;
	background: var(--color-border);
	opacity: 0.65;
}

@media (max-width: 512px) {
	.workflow-file-name {
		font-size: 18px;
	}

	.workflow-meta-label {
		font-size: 12px;
	}

	.workflow-meta-value {
		font-size: 12px;
	}

	.workflow-file-actions {
		flex-direction: row;
	}
}
</style>
