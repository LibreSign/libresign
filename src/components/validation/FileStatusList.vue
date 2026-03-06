<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="file-status-list">
		<div v-if="files.length === 0" class="empty-state">
			<p>{{ t('libresign', 'No files to display') }}</p>
		</div>
		<div v-else class="files-container">
			<div v-for="file in files" :key="file.id" class="file-item">
				<div class="file-info">
					<div class="file-icon">
						<NcIconSvgWrapper :path="mdiFilePdfBox" />
					</div>
					<div class="file-details">
						<div class="file-name">{{ file.name }}</div>
						<div class="file-size">{{ formatFileSize(file.size) }}</div>
					</div>
				</div>
				<div class="file-status">
					<div :class="['status-badge', `status-${getStatusClass(file.status)}`]">
						<NcIconSvgWrapper :path="getStatusIcon(file.status)" />
						<span>{{ getStatusLabel(file.status) }}</span>
					</div>
					<div v-if="file.signed" class="signed-date">
						{{ formatDate(file.signed) }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { mdiFilePdfBox } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { formatFileSize } from '@nextcloud/files'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Moment from '@nextcloud/moment'
import { onBeforeUnmount, onMounted, ref, watch, toRefs } from 'vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { FILE_STATUS } from '../../constants.js'
import { getStatusLabel, getStatusIcon } from '../../utils/fileStatus.js'

defineOptions({
	name: 'FileStatusList',
})

type FileEntry = {
	id: number
	name: string
	status?: string | number
	size?: number
	signed?: string
}

const emit = defineEmits<{
	(event: 'file-signed', file: FileEntry): void
	(event: 'files-updated', files: FileEntry[]): void
}>()

const props = withDefaults(defineProps<{
	fileIds?: number[]
	updateInterval?: number
}>(), {
	fileIds: () => [],
	updateInterval: 2000,
})

const { fileIds, updateInterval } = toRefs(props)
const files = ref<FileEntry[]>([])
const updateTimer = ref<ReturnType<typeof setInterval> | null>(null)

async function loadFiles() {
	try {
		const fileRequests = fileIds.value.map((fileId) => axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/${fileId}`)))
		const responses = await Promise.all(fileRequests)
		files.value = responses.map((response) => response.data.ocs.data)
		emit('files-updated', files.value)
	} catch (error) {
		console.error('[libresign][front] Failed to load files', error)
	}
}

function startUpdatePolling() {
	if (updateTimer.value) {
		return
	}
	updateTimer.value = setInterval(() => {
		loadFiles()
	}, updateInterval.value)
}

function stopUpdatePolling() {
	if (updateTimer.value) {
		clearInterval(updateTimer.value)
		updateTimer.value = null
	}
}

function getStatusClass(status: string | number) {
	const statusMap: Record<string | number, string> = {
		[FILE_STATUS.NOT_LIBRESIGN_FILE]: 'not-libresign',
		[FILE_STATUS.DRAFT]: 'draft',
		[FILE_STATUS.ABLE_TO_SIGN]: 'ready',
		[FILE_STATUS.PARTIAL_SIGNED]: 'partial',
		[FILE_STATUS.SIGNED]: 'signed',
		[FILE_STATUS.DELETED]: 'deleted',
		[FILE_STATUS.SIGNING_IN_PROGRESS]: 'signing',
	}
	return statusMap[status] || 'unknown'
}

function formatDate(dateString?: string) {
	if (!dateString) {
		return ''
	}
	return Moment(dateString).format('LL LTS')
}

watch(fileIds, (newIds) => {
	if (newIds.length > 0) {
		loadFiles()
		startUpdatePolling()
	} else {
		stopUpdatePolling()
	}
})

onMounted(() => {
	if (fileIds.value.length > 0) {
		loadFiles()
		startUpdatePolling()
	}
})

onBeforeUnmount(() => {
	stopUpdatePolling()
})

defineExpose({
	files,
	updateTimer,
	loadFiles,
	startUpdatePolling,
	stopUpdatePolling,
	getStatusClass,
	getStatusLabel,
	getStatusIcon,
	formatDate,
})
</script>

<style lang="scss" scoped>
.file-status-list {
	width: 100%;

	.empty-state {
		text-align: center;
		padding: 20px;
		color: var(--color-text-lighter);

		p {
			margin: 0;
		}
	}

	.files-container {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.file-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px;
		background-color: var(--color-background-hover);
		border-radius: 6px;
		border: 1px solid var(--color-border);

		@media screen and (max-width: 700px) {
			flex-direction: column;
			align-items: flex-start;
		}
	}

	.file-info {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;

		.file-icon {
			width: 32px;
			height: 32px;
			display: flex;
			align-items: center;
			justify-content: center;
			background-color: var(--color-primary-light);
			border-radius: 4px;
			color: var(--color-primary);

			:deep(svg) {
				width: 20px;
				height: 20px;
			}
		}

		.file-details {
			display: flex;
			flex-direction: column;
			gap: 4px;

			.file-name {
				font-weight: 500;
				color: var(--color-main-text);
				word-break: break-word;
			}

			.file-size {
				font-size: 12px;
				color: var(--color-text-lighter);
			}
		}
	}

	.file-status {
		display: flex;
		align-items: center;
		gap: 16px;

		@media screen and (max-width: 700px) {
			width: 100%;
			justify-content: space-between;
			margin-top: 8px;
		}

		.status-badge {
			display: flex;
			align-items: center;
			gap: 6px;
			padding: 4px 12px;
			border-radius: 16px;
			font-size: 12px;
			font-weight: 500;
			white-space: nowrap;

			:deep(svg) {
				width: 16px;
				height: 16px;
			}

			&.status-draft {
				background-color: #f0f0f0;
				color: #666;
			}

			&.status-ready {
				background-color: #e3f2fd;
				color: #1976d2;
			}

			&.status-partial {
				background-color: #e3f2fd;
				color: #1976d2;
			}

			&.status-signed {
				background-color: #e8f5e9;
				color: #388e3c;
			}

			&.status-deleted {
				background-color: #ffebee;
				color: #c62828;
			}

			&.status-signing {
				background-color: #fff3e0;
				color: #f57c00;
				animation: pulse 1.5s ease-in-out infinite;
			}
		}

		.signed-date {
			font-size: 12px;
			color: var(--color-text-lighter);
			white-space: nowrap;
		}
	}
}

@keyframes pulse {
	0%, 100% {
		opacity: 1;
	}
	50% {
		opacity: 0.6;
	}
}
</style>
