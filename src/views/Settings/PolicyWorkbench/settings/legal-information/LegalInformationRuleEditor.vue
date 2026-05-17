<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="legal-information-editor">
		<MarkdownEditor
			:model-value="legalInformation"
			:label="t('libresign', 'Legal Information')"
			min-height="80px"
			:placeholder="t('libresign', 'Legal Information')"
			@update:model-value="onChange" />

		<div v-if="legalInformation" class="legal-information-editor__preview">
			<strong>{{ t('libresign', 'Preview') }}</strong>
			<NcRichText
				class="legal-information-editor__preview-content"
				:text="legalInformation"
				:use-markdown="true" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcRichText from '@nextcloud/vue/components/NcRichText'

import MarkdownEditor from '../../../../../components/MarkdownEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { normalizeLegalInformation } from './model'

defineOptions({
	name: 'LegalInformationRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const legalInformation = computed(() => normalizeLegalInformation(props.modelValue))

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
	gap: 8px;
}

.legal-information-editor__preview-content {
	overflow-wrap: anywhere;
}
</style>
