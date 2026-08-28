<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="mail-sender-strategy-rule-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			type="radio"
			:model-value="selected === option.value"
			name="mail-sender-strategy-rule-editor"
			class="mail-sender-strategy-rule-editor__option"
			@update:modelValue="onChange(option.value, $event)">
			<div class="mail-sender-strategy-rule-editor__copy">
				<strong>{{ option.label }}</strong>
				<p>{{ option.description }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { EffectivePolicyValue } from '../../../../../types/index'
import { normalizeMailSenderStrategy, type MailSenderStrategy } from './model'

defineOptions({
	name: 'MailSenderStrategyRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const selected = computed(() => normalizeMailSenderStrategy(props.modelValue))

const options: Array<{ value: MailSenderStrategy, label: string, description: string }> = [
	{
		value: 'system',
		// TRANSLATORS Option label meaning notification emails are sent by the Nextcloud system mailer.
		label: t('libresign', 'System mailer'),
		// TRANSLATORS Option description for sending notification emails from the address configured for the Nextcloud instance.
		description: t('libresign', 'Send notifications from the email address configured for this Nextcloud instance.'),
	},
	{
		value: 'requester',
		// TRANSLATORS Option label meaning notification emails are sent from the mail account of the person who requested the signature.
		label: t('libresign', 'Requester mail account'),
		// TRANSLATORS Option description for sending notification emails through the requester mail account, with automatic fallback to the system mailer.
		description: t('libresign', 'Try to send notifications from the mail account of the person who requested the signature. This requires a mail provider such as the Mail app; when the account cannot be used, the system mailer is used instead.'),
	},
]

function onChange(nextValue: MailSenderStrategy, selectedOption?: unknown): void {
	if (selectedOption === false) {
		return
	}

	emit('update:modelValue', nextValue)
}
</script>

<style scoped lang="scss">
.mail-sender-strategy-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.mail-sender-strategy-rule-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.mail-sender-strategy-rule-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}
}
</style>
