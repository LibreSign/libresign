<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="file-status-list">
		<div class="list-header">
			<h2>{{ t('libresign', 'Files to sign') }}</h2>
			<p class="list-meta">{{ files.length }} {{ n('libresign', 'file', 'files', files.length) }}</p>
		</div>

		<div v-if="files.length === 0" class="empty-state">
			<NcEmptyContent :name="t('libresign', 'No files to sign')" />
		</div>

		<div v-else class="files-container">
			<div v-for="file in files" :key="file.uuid" class="file-item">
				<div class="file-info">
					<div class="file-icon">
						<NcIconSvgWrapper :path="mdiFilePdfBox" />
					</div>
					<div class="file-details">
						<p class="file-name">{{ file.name }}</p>
						<p class="file-size">{{ formatFileSize(file.size) }}</p>
					</div>
				</div>

				<div class="file-status">
					<div :class="['status-badge', `status-${getStatusClass(file.status)}`]">
						<NcIconSvgWrapper
							:path="getStatusIcon(file.status)"
							:size="20"
						/>
						<span>{{ getStatusLabel(file.status) }}</span>
					</div>
					<p v-if="file.signedAt" class="signed-date">
						{{ t('libresign', 'Signed') }} {{ formatDate(file.signedAt) }}
					</p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { mdiFilePdfBox } from '@mdi/js'
import { formatFileSize } from '@nextcloud/files'
import { n, t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { FILE_STATUS } from '../../constants.js'
import { getStatusLabel, getStatusIcon } from '../../utils/fileStatus.js'

export default {
	name: 'FileStatusList',
	components: {
		NcEmptyContent,
		NcIconSvgWrapper,
	},
	props: {
		fileIds: {
			type: Array,
			default: () => [],
	},
		updateInterval: {
			type: Number,
			default: 2000, // 2 seconds
		},
	},
	emits: ['file-signed', 'files-updated'],
	setup() {
		return {
			t,
			n,
			formatFileSize,
			getStatusLabel,
			mdiFilePdfBox,}
	},
	data() {
		return {
			mdiFilePdfBox,
			files: [],
			isLoading: false,
			updatePollingInterval: null,
			previouslySignedIds: [],
		}
	},
	mounted() {
		this.loadFiles()
		this.startUpdatePolling()
	},
	beforeUnmount() {
		this.stopUpdatePolling()
	},
	methods: {
		async loadFiles() {
			if (this.isLoading || this.fileIds.length === 0) {
				return
			}

			this.isLoading = true
			try {
				const { data } = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/file/list'),
					{ timeout: 10000 }
				)

				const fileList = data.ocs?.data?.data ?? []
				const fileMap = new Map(fileList.map(f => [f.id, f]))

				this.files = this.fileIds
					.map(id => fileMap.get(id))
					.filter(Boolean)

				this.$emit('files-updated', this.files)

				const signedFile = this.files.find(f => f.status === FILE_STATUS.SIGNED)
				if (signedFile && !this.previouslySignedIds?.includes(signedFile.id)) {
					this.$emit('file-signed', signedFile)
				}

				this.previouslySignedIds = this.files
					.filter(f => f.status === FILE_STATUS.SIGNED)
					.map(f => f.id)
			} catch (error) {
				console.error('[libresign][FileStatusList] Error loading files:', error)
			} finally {
				this.isLoading = false
			}
		},
		startUpdatePolling() {
			this.updatePollingInterval = setInterval(() => {
				this.loadFiles()
			}, this.updateInterval)
		},
		stopUpdatePolling() {
			if (this.updatePollingInterval) {
				clearInterval(this.updatePollingInterval)
				this.updatePollingInterval = null
			}
		},
		getStatusClass(status) {
			const statusMap = {
				[FILE_STATUS.NOT_LIBRESIGN_FILE]: 'not-libresign',
				[FILE_STATUS.DRAFT]: 'draft',
				[FILE_STATUS.ABLE_TO_SIGN]: 'ready',
				[FILE_STATUS.PARTIAL_SIGNED]: 'partial',
				[FILE_STATUS.SIGNED]: 'signed',
				[FILE_STATUS.DELETED]: 'deleted',
				[FILE_STATUS.SIGNING_IN_PROGRESS]: 'signing',
			}
			return statusMap[status] || 'unknown'
		},
		formatDate(date) {
			if (!date) return ''
			return Moment(date).calendar()
		},
	},
}
</script>

<style lang="scss" scoped>
.file-status-list {
	width: 100%;
	padding: 1rem;

	.list-header {
		margin-bottom: 1.5rem;

		h2 {
			margin: 0 0 0.5rem 0;
			font-size: 1.25rem;
		}

		.list-meta {
			margin: 0;
			font-size: 0.9rem;
			color: var(--color-text-secondary);
		}
	}

	.empty-state {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 200px;
	}

	.files-container {
		display: flex;
		flex-direction: column;
		gap: 1rem;

		.file-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 1rem;
			border: 1px solid var(--color-border);
			border-radius: 8px;
			background-color: var(--color-background-secondary);
			transition: all 0.3s ease;

			&:hover {
				border-color: var(--color-primary);
				background-color: var(--color-background-hover);
			}

			.file-info {
				display: flex;
				align-items: center;
				gap: 1rem;
				flex: 1;
				min-width: 0;

				.file-icon {
					display: flex;
					align-items: center;
					justify-content: center;
					min-width: 40px;
					height: 40px;
					background-color: var(--color-primary);
					border-radius: 50%;
					color: white;

					:deep(svg) {
						width: 20px;
						height: 20px;
					}
				}

				.file-details {
					flex: 1;
					min-width: 0;

					.file-name {
						margin: 0 0 0.25rem 0;
						font-weight: 500;
						white-space: nowrap;
						overflow: hidden;
						text-overflow: ellipsis;
					}

					.file-size {
						margin: 0;
						font-size: 0.85rem;
						color: var(--color-text-secondary);
					}
				}
			}

			.file-status {
				display: flex;
				flex-direction: column;
				align-items: flex-end;
				gap: 0.5rem;

				.status-badge {
					display: flex;
					align-items: center;
					gap: 0.5rem;
					padding: 0.5rem 1rem;
					border-radius: 20px;
					font-size: 0.9rem;
					font-weight: 500;
					white-space: nowrap;

					:deep(svg) {
						width: 20px;
						height: 20px;
					}

					&.status-signed {
						background-color: #e8f5e9;
						color: #2e7d32;
					}

					&.status-signing {
						background-color: #fff3e0;
						color: #e65100;
						animation: pulse 1.5s ease-in-out infinite;
					}

					&.status-ready,
					&.status-partial {
						background-color: #e3f2fd;
						color: #1565c0;
					}

					&.status-draft {
						background-color: #f5f5f5;
						color: #666;
					}

					&.status-deleted {
						background-color: #ffebee;
						color: #c62828;
					}
				}

				.signed-date {
					margin: 0;
					font-size: 0.8rem;
					color: var(--color-text-secondary);
				}
			}
		}
	}
}

@keyframes pulse {
	0%, 100% {
		opacity: 1;
	}
	50% {
		opacity: 0.7;
	}
}
</style>
