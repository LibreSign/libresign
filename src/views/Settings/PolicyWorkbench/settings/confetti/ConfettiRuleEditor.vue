<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="confetti-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="modelValue.enabled"
			@update:modelValue="onEnabledChange">
			<span>{{ t('libresign', 'Show confetti animation after signing') }}</span>
		</NcCheckboxRadioSwitch>

		<p class="confetti-rule-editor__hint">
			{{ t('libresign', 'This is intentionally simple so we can validate the policy shell with a lightweight setting too.') }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { ConfettiRuleValue } from '../../types'

defineOptions({
	name: 'ConfettiRuleEditor',
})

const props = defineProps<{
	modelValue: ConfettiRuleValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: ConfettiRuleValue]
}>()

function onEnabledChange(enabled: boolean) {
	emit('update:modelValue', {
		...props.modelValue,
		enabled,
	})
}
</script>

<style scoped lang="scss">
.confetti-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
