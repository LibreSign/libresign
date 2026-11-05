<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-rejection-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			class="signature-rejection-editor__enabled"
			:model-value="normalizedValue.enabled"
			:disabled="!canToggleEnabled"
			@update:modelValue="onEnabledChange">
			{{ enabledLabel }}
		</NcCheckboxRadioSwitch>

		<p v-if="!normalizedValue.enabled" class="signature-rejection-editor__hint" role="note">
			{{ disabledHint }}
		</p>

		<fieldset
			v-for="field in fields"
			:key="field.name"
			class="signature-rejection-editor__field">
			<legend>{{ field.legend }}</legend>
			<NcCheckboxRadioSwitch
				v-for="option in field.options"
				:key="option.value"
				type="radio"
				:name="`signature-rejection-${field.name}`"
				:value="option.value"
				:model-value="normalizedValue[field.name]"
				@update:modelValue="onOptionChange(field.name, $event)">
				{{ option.label }}
			</NcCheckboxRadioSwitch>
		</fieldset>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	getSignatureRejectionBehaviorLabel,
	getSignatureRejectionCommentModeLabel,
	getSignatureRejectionVisibilityLabel,
	normalizeSignatureRejectionDraftValue,
	SIGNATURE_REJECTION_BEHAVIORS,
	SIGNATURE_REJECTION_COMMENT_MODES,
	SIGNATURE_REJECTION_VISIBILITIES,
	type SignatureRejectionDraftValue,
} from './model'

defineOptions({
	name: 'SignatureRejectionRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
	allowedEnabledValues?: boolean[]
	editorScope?: 'system' | 'group' | 'user'
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

type OptionFieldName = Exclude<keyof SignatureRejectionDraftValue, 'enabled'>

// TRANSLATORS Switch label that lets signers reject a signing request.
const enabledLabel = t('libresign', 'Allow signers to reject')
// TRANSLATORS Hint shown while signature rejection is off: the options below can be prepared now and apply once it is turned on.
const disabledHint = t('libresign', 'The options below take effect once signature rejection is allowed.')

const normalizedValue = computed(() => normalizeSignatureRejectionDraftValue(props.modelValue))

const canToggleEnabled = computed(() => {
	// The allowed values describe what the rules above leave to the current
	// actor as a user, not to the system or group rule being edited. Whether
	// the actor may write that rule is decided by the backend when saving.
	if (props.editorScope === 'system' || props.editorScope === 'group') {
		return true
	}

	const allowed = props.allowedEnabledValues
	if (!allowed || allowed.length === 0) {
		return true
	}

	return allowed.includes(!normalizedValue.value.enabled)
})

const fields = computed<Array<{ name: OptionFieldName, legend: string, options: Array<{ value: string, label: string }> }>>(() => [
	{
		name: 'behavior',
		// TRANSLATORS Group label: what happens to the signing request after a signer rejects it.
		legend: t('libresign', 'After a rejection'),
		options: SIGNATURE_REJECTION_BEHAVIORS.map((value) => ({ value, label: getSignatureRejectionBehaviorLabel(value, t) })),
	},
	{
		name: 'commentMode',
		// TRANSLATORS Group label: whether the signer can or must write why they reject.
		legend: t('libresign', 'Rejection comment'),
		options: SIGNATURE_REJECTION_COMMENT_MODES.map((value) => ({ value, label: getSignatureRejectionCommentModeLabel(value, t) })),
	},
	{
		name: 'visibility',
		// TRANSLATORS Group label: who is told that a signer rejected.
		legend: t('libresign', 'Who can see the rejection'),
		options: SIGNATURE_REJECTION_VISIBILITIES.map((value) => ({ value, label: getSignatureRejectionVisibilityLabel(value, t) })),
	},
	{
		name: 'commentVisibility',
		// TRANSLATORS Group label: who can read the comment a signer wrote when rejecting.
		legend: t('libresign', 'Who can read the comment'),
		options: SIGNATURE_REJECTION_VISIBILITIES.map((value) => ({ value, label: getSignatureRejectionVisibilityLabel(value, t) })),
	},
])

function emitValue(patch: Partial<SignatureRejectionDraftValue>) {
	emit('update:modelValue', { ...normalizedValue.value, ...patch })
}

function onEnabledChange(enabled: boolean) {
	emitValue({ enabled })
}

function onOptionChange(name: OptionFieldName, value: unknown) {
	emit('update:modelValue', normalizeSignatureRejectionDraftValue({ ...normalizedValue.value, [name]: value }))
}
</script>

<style scoped lang="scss">
.signature-rejection-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		margin: 0;
		padding: 0;
		border: 0;

		legend {
			margin-bottom: 0.25rem;
			font-weight: bold;
		}
	}
}
</style>
