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
			variant="tertiary"
			:aria-label="t('libresign', 'Cancel upload')"
			@click="$emit('cancel')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCancel" :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

import { mdiCancel } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineOptions({
	name: 'UploadProgress',
})

defineEmits<{
	(e: 'cancel'): void
}>()

const props = withDefaults(defineProps<{
	isUploading: boolean
	uploadProgress?: number
	uploadedBytes?: number
	totalBytes?: number
	uploadStartTime?: number | null
}>(), {
	uploadProgress: 0,
	uploadedBytes: 0,
	totalBytes: 0,
	uploadStartTime: null,
})

const uploadEta = computed(() => {
	if (!props.isUploading || !props.uploadStartTime || props.uploadedBytes === 0) {
		return ''
	}

	const elapsed = Date.now() - props.uploadStartTime
	const rate = props.uploadedBytes / elapsed
	const remaining = props.totalBytes - props.uploadedBytes
	const eta = remaining / rate

	if (eta < 1000) {
		return t('libresign', 'a few seconds left')
	}

	if (eta < 60000) {
		const seconds = Math.ceil(eta / 1000)
		return t('libresign', '{seconds} seconds left', { seconds })
	}

	const minutes = Math.ceil(eta / 60000)
	return t('libresign', '{minutes} minutes left', { minutes })
})

defineExpose({
	uploadEta,
})
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
