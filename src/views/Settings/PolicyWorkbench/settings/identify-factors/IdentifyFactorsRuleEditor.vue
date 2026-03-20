<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="identify-factors-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="modelValue.enabled"
			@update:modelValue="onEnabledChange">
			<span>{{ t('libresign', 'Enable identification factors policy') }}</span>
		</NcCheckboxRadioSwitch>

		<div v-if="modelValue.enabled" class="identify-factors-rule-editor__content">
			<div class="identify-factors-rule-editor__health">
				<p>
					{{ t('libresign', '{enabled} enabled, {required} required', {
						enabled: String(enabledFactorsCount),
						required: String(requiredFactorsCount),
					}) }}
				</p>
				<p>
					{{ modelValue.requireAnyTwo
						? t('libresign', 'Rule strategy: any two factors')
						: t('libresign', 'Rule strategy: a single configured factor') }}
				</p>
			</div>

			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="modelValue.requireAnyTwo"
				@update:modelValue="onRequireAnyTwoChange">
				<span>{{ t('libresign', 'Require at least two enabled factors') }}</span>
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="showTwoFactorWarning" type="warning">
				{{ t('libresign', 'At least two factors must be enabled to satisfy this policy strategy.') }}
			</NcNoteCard>

			<div
				v-for="factor in modelValue.factors"
				:key="factor.key"
				class="identify-factors-rule-editor__factor">
				<div class="identify-factors-rule-editor__factor-header">
					<strong>{{ factor.label }}</strong>
					<span>{{ factor.key }}</span>
				</div>

				<NcCheckboxRadioSwitch
					type="switch"
					:model-value="factor.enabled"
					@update:modelValue="onFactorSwitch(factor.key, 'enabled', $event)">
					<span>{{ t('libresign', 'Enabled for this target') }}</span>
				</NcCheckboxRadioSwitch>

				<div v-if="factor.enabled" class="identify-factors-rule-editor__factor-options">
					<NcCheckboxRadioSwitch
						type="switch"
						:model-value="factor.required"
						@update:modelValue="onFactorSwitch(factor.key, 'required', $event)">
						<span>{{ t('libresign', 'Mandatory for signer') }}</span>
					</NcCheckboxRadioSwitch>

					<NcCheckboxRadioSwitch
						v-if="factor.key === 'email'"
						type="switch"
						:model-value="factor.allowCreateAccount"
						@update:modelValue="onFactorSwitch(factor.key, 'allowCreateAccount', $event)">
						<span>{{ t('libresign', 'Allow account creation fallback') }}</span>
					</NcCheckboxRadioSwitch>

					<fieldset class="identify-factors-rule-editor__signature-methods">
						<legend>{{ t('libresign', 'Signature method') }}</legend>
						<NcCheckboxRadioSwitch
							v-for="methodOption in signatureMethodOptionsByFactor[factor.key]"
							:key="methodOption.value"
							type="radio"
							:name="`identify-factor-${factor.key}`"
							:model-value="factor.signatureMethod === methodOption.value"
							@update:modelValue="onSignatureMethodChange(factor.key, methodOption.value)">
							{{ methodOption.label }}
						</NcCheckboxRadioSwitch>
					</fieldset>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import type {
	IdentifyFactorKey,
	IdentifyFactorOption,
	IdentifyFactorSignatureMethod,
	IdentifyFactorsRuleValue,
} from '../../types'

defineOptions({
	name: 'IdentifyFactorsRuleEditor',
})

const props = defineProps<{
	modelValue: IdentifyFactorsRuleValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: IdentifyFactorsRuleValue]
}>()

const signatureMethodOptionsByFactor: Record<IdentifyFactorKey, Array<{ value: IdentifyFactorSignatureMethod, label: string }>> = {
	email: [
		{ value: 'email_token', label: t('libresign', 'Email token') },
		{ value: 'document_validation', label: t('libresign', 'Document validation') },
	],
	sms: [
		{ value: 'sms_token', label: t('libresign', 'SMS token') },
		{ value: 'document_validation', label: t('libresign', 'Document validation') },
	],
	whatsapp: [
		{ value: 'whatsapp_token', label: t('libresign', 'WhatsApp token') },
		{ value: 'document_validation', label: t('libresign', 'Document validation') },
	],
	document: [
		{ value: 'document_validation', label: t('libresign', 'Document validation') },
	],
}

const enabledFactorsCount = computed(() => props.modelValue.factors.filter((factor) => factor.enabled).length)
const requiredFactorsCount = computed(() => props.modelValue.factors.filter((factor) => factor.enabled && factor.required).length)
const showTwoFactorWarning = computed(() => props.modelValue.requireAnyTwo && enabledFactorsCount.value < 2)

function updateValue(nextValue: Partial<IdentifyFactorsRuleValue>) {
	emit('update:modelValue', {
		...props.modelValue,
		...nextValue,
	})
}

function onEnabledChange(enabled: boolean) {
	updateValue({ enabled })
}

function onRequireAnyTwoChange(requireAnyTwo: boolean) {
	updateValue({ requireAnyTwo })
}

function onFactorSwitch(
	factorKey: IdentifyFactorKey,
	field: 'enabled' | 'required' | 'allowCreateAccount',
	value: boolean,
) {
	updateFactor(factorKey, {
		[field]: value,
	} as Partial<IdentifyFactorOption>)
}

function onSignatureMethodChange(factorKey: IdentifyFactorKey, signatureMethod: IdentifyFactorSignatureMethod) {
	updateFactor(factorKey, { signatureMethod })
}

function updateFactor(factorKey: IdentifyFactorKey, patch: Partial<IdentifyFactorOption>) {
	updateValue({
		factors: props.modelValue.factors.map((factor) => {
			if (factor.key !== factorKey) {
				return factor
			}
			return {
				...factor,
				...patch,
			}
		}),
	})
}
</script>

<style scoped lang="scss">
.identify-factors-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__content {
		display: flex;
		flex-direction: column;
		gap: 0.9rem;
	}

	&__health {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.5rem;
		border-radius: 10px;
		padding: 0.65rem;
		background: color-mix(in srgb, var(--color-primary-element) 9%, var(--color-main-background));

		p {
			margin: 0;
			font-size: 0.85rem;
			color: var(--color-text-maxcontrast);
		}
	}

	&__factor {
		border-radius: 12px;
		border: 1px solid var(--color-border-maxcontrast);
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.6rem;
	}

	&__factor-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.6rem;

		span {
			font-size: 0.8rem;
			color: var(--color-text-maxcontrast);
			text-transform: uppercase;
		}
	}

	&__factor-options {
		display: flex;
		flex-direction: column;
		gap: 0.55rem;
		padding-left: 0.4rem;
	}

	&__signature-methods {
		margin: 0;
		padding: 0.6rem;
		border-radius: 10px;
		border: 1px dashed color-mix(in srgb, var(--color-primary-element) 45%, var(--color-border-maxcontrast));
		display: flex;
		flex-direction: column;
		gap: 0.35rem;

		legend {
			font-weight: 600;
			padding: 0 0.35rem;
		}
	}
}

@media (max-width: 640px) {
	.identify-factors-rule-editor {
		&__health {
			grid-template-columns: 1fr;
		}

		&__factor-header {
			flex-wrap: wrap;
		}
	}
}
</style>
