<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste__group">
		<div class="ste__label-row">
			<label class="ste__label">{{ t('libresign', 'Render mode') }}</label>
		</div>
		<div class="ste__seg ste__seg--modes" role="radiogroup" :aria-label="t('libresign', 'Render mode')">
			<button
				v-for="opt in displayModeOptions"
				:key="opt.value"
				type="button"
				class="ste__seg-btn"
				:class="{ 'ste__seg-btn--active': renderMode === opt.value }"
				:aria-pressed="renderMode === opt.value"
				:title="opt.description"
				@click="$emit('update:renderMode', opt.value)">
				{{ opt.label }}
			</button>
		</div>
	</div>

	<div v-if="renderMode !== 'graphic'" class="ste__group">
		<div class="ste__label-row">
			<label :for="`ste-tpl-${id}`" class="ste__label">{{ t('libresign', 'Signature text template') }}</label>
			<NcButton
				variant="tertiary"
				:aria-label="t('libresign', 'Show available variables')"
				@click="showVariablesDialog = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" />
				</template>
				{{ t('libresign', 'Available variables') }}
			</NcButton>
			<NcButton
				variant="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="$emit('resetTemplate')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>
		</div>
		<textarea
			:id="`ste-tpl-${id}`"
			:value="template"
			class="ste__textarea"
			:placeholder="t('libresign', 'Enter signature text template…')"
			spellcheck="false"
			@input="onTemplateInput" />
	</div>

	<NcDialog
		:name="t('libresign', 'Available template variables')"
		v-model:open="showVariablesDialog"
		size="normal">
		<div class="ste__vars-dialog">
			<p class="ste__vars-description">
				{{ t('libresign', 'Click on a variable to copy it to clipboard') }}
			</p>
			<div class="ste__vars-list">
				<NcFormBoxButton
					v-for="variable in availableVariables"
					:key="variable.value"
					inverted-accent
					@click="copyVariableToClipboard(variable.value)">
					<template #default>
						<span class="hidden-visually">{{ t('libresign', 'Copy to clipboard') }}</span>
						{{ variable.value }}
					</template>
					<template #icon>
						<NcIconSvgWrapper v-if="copiedVariable === variable.value" :path="mdiCheck" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiContentCopy" :size="20" />
					</template>
					<template #description>
						<p class="ste__variable-description">{{ variable.description }}</p>
					</template>
				</NcFormBoxButton>
			</div>
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import { mdiCheck, mdiContentCopy, mdiHelpCircleOutline, mdiUndoVariant } from '@mdi/js'
import { ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

type DisplayMode = 'default' | 'graphic' | 'text' | 'description_only'

defineProps<{
	id: string
	renderMode: DisplayMode
	template: string
	displayModeOptions: Array<{ value: DisplayMode; label: string; description: string }>
	availableVariables: Array<{ value: string; description: string }>
}>()

const emit = defineEmits<{
	(event: 'update:renderMode', value: DisplayMode): void
	(event: 'update:template', value: string): void
	(event: 'resetTemplate'): void
}>()

const showVariablesDialog = ref(false)
const copiedVariable = ref<string | null>(null)

function onTemplateInput(event: Event): void {
	const target = event.target
	emit('update:template', target instanceof HTMLTextAreaElement ? target.value : '')
}

function copyVariableToClipboard(value: string): void {
	if (copiedVariable.value === value) {
		return
	}

	try {
		navigator.clipboard.writeText(value)
	} catch {
		prompt('', value)
	}

	copiedVariable.value = value
	setTimeout(() => {
		if (copiedVariable.value === value) {
			copiedVariable.value = null
		}
	}, 2000)
}
</script>

<style scoped>
.ste__group {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
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

.ste__seg {
	display: inline-flex;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-background-dark);
}

.ste__seg--modes {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	width: 100%;
}

.ste__seg-btn {
	flex: 1;
	padding: 0.42rem 0.45rem;
	border: none;
	background: transparent;
	font-size: 0.76rem;
	line-height: 1.15;
	min-height: 2.15rem;
	text-align: center;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	white-space: normal;
	overflow-wrap: anywhere;
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

.ste__textarea {
	width: 100%;
	min-height: 9rem;
	resize: vertical;
	padding: 0.65rem 0.8rem;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: 0.88rem;
	line-height: 1.55;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.ste__textarea:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

.ste__vars-dialog {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.ste__vars-description {
	margin: 0;
	font-size: 0.84rem;
	color: var(--color-text-maxcontrast);
}

.ste__vars-list {
	display: flex;
	flex-direction: column;
	gap: 0.45rem;
}

.ste__variable-description {
	margin: 0;
	font-size: 0.8rem;
	line-height: 1.35;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 640px) {
	.ste__seg--modes {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}
</style>
