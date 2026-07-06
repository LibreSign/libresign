<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste__group ste__bg-row">
		<span class="ste__label">{{ backgroundLabel }}</span>
		<div class="ste__seg ste__seg--background" role="radiogroup" :aria-label="backgroundSourceLabel">
			<button
				v-for="opt in backgroundOptions"
				:key="opt.value"
				type="button"
				class="ste__seg-btn"
				:class="{ 'ste__seg-btn--active': backgroundType === opt.value }"
				:aria-pressed="backgroundType === opt.value"
				:title="opt.description"
				@click="selectBackground(opt.value)">
				{{ opt.label }}
			</button>
		</div>
		<NcButton
			variant="secondary"
			:aria-label="uploadBackgroundImageLabel"
			@click="triggerFilePicker">
			<template #icon>
				<NcIconSvgWrapper :path="mdiUpload" :size="16" />
			</template>
			{{ uploadLabel }}
		</NcButton>
		<NcButton
			v-if="backgroundType !== 'default'"
			variant="tertiary"
			:aria-label="resetBackgroundToDefaultLabel"
			@click="$emit('resetBackground')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
			</template>
		</NcButton>
		<NcButton
			v-if="backgroundType !== 'deleted'"
			variant="tertiary"
			:aria-label="removeBackgroundLabel"
			@click="$emit('removeBackground')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiDelete" :size="20" />
			</template>
		</NcButton>
		<NcLoadingIcon v-if="showLoading" :size="20" />
		<input ref="input" type="file" accept="image/png" class="ste__file-input" @change="onFileChange">
	</div>

	<NcNoteCard v-if="errorMessage" type="error" :show-alert="true">
		<p>{{ errorMessage }}</p>
	</NcNoteCard>
</template>

<script setup lang="ts">
import { mdiDelete, mdiUndoVariant, mdiUpload } from '@mdi/js'
import { ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

type BackgroundType = 'default' | 'custom' | 'deleted'

defineProps<{
	backgroundType: BackgroundType
	backgroundOptions: Array<{ value: BackgroundType; label: string; description: string }>
	showLoading: boolean
	errorMessage: string
}>()

const emit = defineEmits<{
	(event: 'selectBackground', value: BackgroundType): void
	(event: 'resetBackground'): void
	(event: 'removeBackground'): void
	(event: 'fileSelected', file: File): void
}>()

// TRANSLATORS Section label for choosing the background of the visible signature stamp.
const backgroundLabel = t('libresign', 'Background')
// TRANSLATORS Accessible label for the control that selects the signature stamp background source.
const backgroundSourceLabel = t('libresign', 'Background source')
// TRANSLATORS Accessible label for the button that uploads a custom background image for the signature stamp.
const uploadBackgroundImageLabel = t('libresign', 'Upload background image')
// TRANSLATORS Button label for uploading a custom signature stamp background image.
const uploadLabel = t('libresign', 'Upload')
// TRANSLATORS Accessible label for the icon-only button that restores the signature stamp background to the default source.
const resetBackgroundToDefaultLabel = t('libresign', 'Reset background to default')
// TRANSLATORS Accessible label for the icon-only button that removes the current signature stamp background.
const removeBackgroundLabel = t('libresign', 'Remove background')

const input = ref<HTMLInputElement | null>(null)

function triggerFilePicker(): void {
	if (!input.value) {
		return
	}
	input.value.value = ''
	input.value.click()
}

function selectBackground(value: BackgroundType): void {
	if (value === 'custom') {
		triggerFilePicker()
		return
	}
	emit('selectBackground', value)
}

function onFileChange(event: Event): void {
	const target = event.target
	const file = target instanceof HTMLInputElement ? target.files?.[0] : undefined
	if (!file) {
		return
	}
	emit('fileSelected', file)
}
</script>

<style scoped>
.ste__group {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
}

.ste__label {
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.ste__bg-row {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
}

.ste__seg {
	display: inline-flex;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-background-dark);
}

.ste__seg--background {
	display: inline-flex;
}

.ste__seg-btn {
	flex: 1;
	padding: 0.35rem 0.75rem;
	border: none;
	background: transparent;
	font-size: 0.84rem;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	transition: background 100ms, color 100ms;
}

.ste__seg-btn + .ste__seg-btn {
	border-left: 1px solid var(--color-border);
}

.ste__seg-btn--active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.ste__seg-btn:not(.ste__seg-btn--active):hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.ste__file-input {
	display: none;
}
</style>
