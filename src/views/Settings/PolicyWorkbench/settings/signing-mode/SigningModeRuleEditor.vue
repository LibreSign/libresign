<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signing-mode-rule-editor">
		<NcCheckboxRadioSwitch
			type="radio"
			name="signing-mode-rule-editor"
			:model-value="normalizedValue === 'sync'"
			@update:modelValue="onModeChange('sync', $event)">
			<div class="signing-mode-rule-editor__copy">
				<strong>{{ t('libresign', 'Synchronous') }}</strong>
				<p>{{ t('libresign', 'Signatures are processed immediately in the request lifecycle.') }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			type="radio"
			name="signing-mode-rule-editor"
			:model-value="normalizedValue === 'async'"
			@update:modelValue="onModeChange('async', $event)">
			<div class="signing-mode-rule-editor__copy">
				<strong>{{ t('libresign', 'Asynchronous') }}</strong>
				<p>{{ t('libresign', 'Signatures are queued and processed in background workers.') }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { SigningModeValue } from './model'
import { resolveSigningMode } from './model'

defineOptions({
	name: 'SigningModeRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const normalizedValue = computed<SigningModeValue>(() => resolveSigningMode(props.modelValue))

function onModeChange(mode: SigningModeValue, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', mode)
}
</script>

<style scoped lang="scss">
.signing-mode-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
