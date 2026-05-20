<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="legal-information-editor">
		<MarkdownEditor
			:model-value="legalInformation"
			:label="t('libresign', 'Legal information')"
			:description="t('libresign', 'Supports Markdown formatting.')"
			min-height="100px"
			max-height="300px"
			:placeholder="t('libresign', 'Add legal information displayed on the validation page using Markdown formatting...')"
			@update:model-value="onChange" />

		<div class="legal-information-editor__preview">
			<strong>{{ t('libresign', 'Preview') }}</strong>
			<p class="legal-information-editor__compatibility-note">
				{{ t('libresign', 'Supported in preview: headings, bold, italic, lists, blockquotes, code, links and horizontal rules.') }}
			</p>
			<div class="legal-information-editor__preview-surface">
				<NcRichText
					v-if="legalInformation"
					class="legal-information-editor__preview-content"
					:text="legalInformation"
					:use-markdown="true" />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcRichText from '@nextcloud/vue/components/NcRichText'

import MarkdownEditor from '../../../../../components/MarkdownEditor.vue'
import { normalizeLegalInformation } from './model'

type LegalInformationValue = string | number | boolean | object | null

defineOptions({
	name: 'LegalInformationRuleEditor',
})

const props = defineProps<{
	modelValue: LegalInformationValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: LegalInformationValue]
}>()

const legalInformation = computed(() => {
	const normalized = normalizeLegalInformation(props.modelValue)
	// Convert escaped newlines from backend to actual newlines for proper markdown rendering
	return normalized.replace(/\\n/g, '\n')
})

function onChange(nextValue: string | number): void {
	emit('update:modelValue', normalizeLegalInformation(nextValue))
}
</script>

<style scoped>
.legal-information-editor {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.legal-information-editor__preview {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.legal-information-editor__compatibility-note {
	margin: 0;
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
}

.legal-information-editor__preview-surface {
	min-height: 60px;
	max-height: 300px;
	overflow-y: auto;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
}

.legal-information-editor__preview-content {
	overflow-wrap: anywhere;
	line-height: 1.5;
}
</style>
