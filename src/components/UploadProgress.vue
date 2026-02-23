<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="isUploading" class="upload-picker-container">
		<div class="upload-picker__progress">
			<NcProgressBar :value="uploadProgress"
				:error="false"
				size="small"
				aria-label="Upload progress" />
			<p v-if="uploadEta">
				<span :title="uploadEta">{{ uploadEta }}</span>
			</p>
		</div>
		<NcButton class="upload-picker__cancel"
			type="tertiary"
			:aria-label="t('libresign', 'Cancel upload')"
			@click="$emit('cancel')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCancel" :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'

import { mdiCancel } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

export default {
	name: 'UploadProgress',
	components: {
		NcButton,
		NcProgressBar,
		NcIconSvgWrapper,
	},
	props: {
		isUploading: {
			type: Boolean,
			required: true,
	},
		uploadProgress: {
			type: Number,
			default: 0,
	},
		uploadedBytes: {
			type: Number,
			default: 0,
	},
		totalBytes: {
			type: Number,
			default: 0,
	},
		uploadStartTime: {
			type: Number,
			default: null,
	},
	},
	emits: ['cancel'],
	setup() {
		return { mdiCancel }
	},
	computed: {
		uploadEta() {
			if (!this.isUploading || !this.uploadStartTime || this.uploadedBytes === 0) {
				return ''
			}

			const elapsed = Date.now() - this.uploadStartTime
			const rate = this.uploadedBytes / elapsed // bytes por ms
			const remaining = this.totalBytes - this.uploadedBytes
			const eta = remaining / rate // ms restantes

			if (eta < 1000) {
				return t('libresign', 'a few seconds left')
			} else if (eta < 60000) {
				const seconds = Math.ceil(eta / 1000)
				return t('libresign', '{seconds} seconds left', { seconds })
			} else {
				const minutes = Math.ceil(eta / 60000)
				return t('libresign', '{minutes} minutes left', { minutes })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.upload-picker-container {
	display: inline-flex;
	align-items: center;
	gap: 0;
	height: var(--default-clickable-area);
}

.upload-picker__progress {
	width: 200px;
	max-width: 0;
	transition: max-width var(--animation-quick) ease-in-out;
	margin-top: 8px;

	:deep(.progress-bar) {
		height: 6px;
	}

	p {
		margin: 0;
		padding: 0;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;

		span {
			display: inline-block;
		}
	}
}

.upload-picker-container .upload-picker__progress {
	max-width: 200px;
	margin-inline: 8px 20px;
}
</style>
