<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste__group ste__dims">
		<div v-if="renderMode !== 'graphic'" class="ste__field">
			<div class="ste__label-row">
				<label :for="`ste-tfs-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Text font') }}</label>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="$emit('resetTemplateFontSize')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="ste__input-unit">
				<input :id="`ste-tfs-${id}`" :value="templateFontSize" type="number" :min="0.1" :max="30" :step="0.1" class="ste__num-input" @input="emitNumber($event, 'templateFontSize')">
				<span class="ste__unit">pt</span>
			</div>
		</div>

		<div v-if="renderMode === 'text'" class="ste__field">
			<div class="ste__label-row">
				<label :for="`ste-sfs-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Sig font') }}</label>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="$emit('resetSignatureFontSize')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="ste__input-unit">
				<input :id="`ste-sfs-${id}`" :value="signatureFontSize" type="number" :min="0.1" :max="30" :step="0.1" class="ste__num-input" @input="emitNumber($event, 'signatureFontSize')">
				<span class="ste__unit">pt</span>
			</div>
		</div>

		<div class="ste__field">
			<div class="ste__label-row">
				<label :for="`ste-w-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Width') }}</label>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="$emit('resetWidth')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="ste__input-unit">
				<input :id="`ste-w-${id}`" :value="signatureWidth" type="number" :min="1" :max="800" class="ste__num-input" @input="emitNumber($event, 'signatureWidth')">
				<span class="ste__unit">px</span>
			</div>
		</div>

		<div class="ste__field">
			<div class="ste__label-row">
				<label :for="`ste-h-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Height') }}</label>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="$emit('resetHeight')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="ste__input-unit">
				<input :id="`ste-h-${id}`" :value="signatureHeight" type="number" :min="1" :max="800" class="ste__num-input" @input="emitNumber($event, 'signatureHeight')">
				<span class="ste__unit">px</span>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { mdiUndoVariant } from '@mdi/js'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

type DisplayMode = 'default' | 'graphic' | 'text' | 'description_only'

defineProps<{
	id: string
	renderMode: DisplayMode
	templateFontSize: number
	signatureFontSize: number
	signatureWidth: number
	signatureHeight: number
}>()

const emit = defineEmits<{
	(event: 'update:templateFontSize', value: number): void
	(event: 'update:signatureFontSize', value: number): void
	(event: 'update:signatureWidth', value: number): void
	(event: 'update:signatureHeight', value: number): void
	(event: 'resetTemplateFontSize'): void
	(event: 'resetSignatureFontSize'): void
	(event: 'resetWidth'): void
	(event: 'resetHeight'): void
}>()

function emitNumber(event: Event, field: 'templateFontSize' | 'signatureFontSize' | 'signatureWidth' | 'signatureHeight'): void {
	const target = event.target
	const value = target instanceof HTMLInputElement ? Number(target.value) : NaN
	if (!Number.isFinite(value)) {
		return
	}
	if (field === 'templateFontSize') {
		emit('update:templateFontSize', value)
		return
	}
	if (field === 'signatureFontSize') {
		emit('update:signatureFontSize', value)
		return
	}
	if (field === 'signatureWidth') {
		emit('update:signatureWidth', value)
		return
	}
	emit('update:signatureHeight', value)
}
</script>

<style scoped>
.ste__group {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
}

.ste__dims {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 0.6rem;
	flex-direction: unset;
}

.ste__field {
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
}

.ste__label-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.35rem;
}

.ste__label {
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.ste__label--sm {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
}

.ste__input-unit {
	display: flex;
	align-items: center;
	gap: 0.3rem;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-main-background);
}

.ste__num-input {
	flex: 1;
	min-width: 0;
	width: 100%;
	padding: 0.45rem 0.5rem;
	border: none;
	background: transparent;
	font-size: 0.88rem;
	color: var(--color-main-text);
}

.ste__num-input:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

.ste__unit {
	padding: 0 0.45rem 0 0;
	font-size: 0.76rem;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

@media (max-width: 640px) {
	.ste__dims {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}
</style>
