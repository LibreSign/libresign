<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="draw-ink-color-editor">
		<div class="draw-ink-color-editor__copy">
			<strong>{{ title }}</strong>
			<p>{{ description }}</p>
		</div>
		<NcColorPicker
			:model-value="normalizedValue"
			@update:modelValue="onChange">
			<button
				class="draw-ink-color-editor__preview"
				:style="{ backgroundColor: normalizedValue }"
				:aria-label="t('libresign', 'Choose color')" />
		</NcColorPicker>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { DEFAULT_DRAW_INK_COLOR, resolveDrawInkColor } from './model'

defineOptions({
	name: 'DrawInkColorRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Title for the setting that chooses the initial ink color of the signature drawing pad.
const title = t('libresign', 'Default ink color for drawn signatures')
// TRANSLATORS Description for the setting that chooses the initial ink color of the signature drawing pad.
const description = t('libresign', 'Set the color the signature drawing pad starts with. Signers can still choose another color while drawing.')

const normalizedValue = computed<string>(() => resolveDrawInkColor(props.modelValue) ?? DEFAULT_DRAW_INK_COLOR)

function onChange(color: string | undefined) {
	emit('update:modelValue', resolveDrawInkColor(color ?? null) ?? DEFAULT_DRAW_INK_COLOR)
}
</script>

<style scoped lang="scss">
.draw-ink-color-editor {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 0.5rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__preview {
		width: 36px;
		height: 36px;
		border-radius: var(--border-radius-large);
		border: 2px solid var(--color-border);
		cursor: pointer;
		transition: border-color 0.2s;

		&:hover {
			border-color: var(--color-primary-element);
		}
	}
}
</style>
