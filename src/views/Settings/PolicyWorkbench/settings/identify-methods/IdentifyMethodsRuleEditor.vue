<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="identify-methods-editor">
		<div v-if="entries.length === 0" class="identify-methods-editor__empty">
			{{ t('libresign', 'No identification methods available.') }}
		</div>

		<div v-for="(identifyMethod, index) in entries"
			:key="identifyMethod.name"
			class="identify-methods-editor__method">
			<div class="identify-methods-editor__method-header">
				<NcCheckboxRadioSwitch type="switch"
					class="identify-methods-editor__method-main-toggle"
					:model-value="identifyMethod.enabled"
					@update:modelValue="onMethodToggle(index, $event)">
					{{ identifyMethod.friendly_name ?? identifyMethod.name }}
				</NcCheckboxRadioSwitch>

				<div v-if="identifyMethod.enabled" class="identify-methods-editor__requirement-area">
					<NcCheckboxRadioSwitch
						v-if="canAdjustRequirement"
						type="switch"
						class="identify-methods-editor__requirement-switch"
						:model-value="isRequired(identifyMethod)"
						@update:modelValue="onRequirementToggle(index, $event)">
						{{ t('libresign', 'Required') }}
					</NcCheckboxRadioSwitch>

					<p v-else class="identify-methods-editor__required-helper">
						{{ t('libresign', 'Only enabled factor') }}
					</p>
				</div>
			</div>

			<div v-if="identifyMethod.enabled" class="identify-methods-editor__method-details">
				<fieldset v-if="Object.keys(identifyMethod.signatureMethods).length > 0" class="identify-methods-editor__sub-section">
					<legend>{{ t('libresign', 'Verification method') }}</legend>
					<div class="identify-methods-editor__verification-options" role="radiogroup" :aria-label="t('libresign', 'Verification method')">
						<NcCheckboxRadioSwitch
							v-for="(signatureMethod, signatureMethodName) in identifyMethod.signatureMethods"
							:key="signatureMethodName"
							type="radio"
							:name="`verification-method-${identifyMethod.name}-${index}`"
							:value="signatureMethodName"
							:model-value="identifyMethod.signatureMethodEnabled"
							class="identify-methods-editor__verification-switch"
							:class="{ 'identify-methods-editor__verification-switch--selected': identifyMethod.signatureMethodEnabled === signatureMethodName }"
							@update:modelValue="onSignatureMethodChange(index, String($event))">
							{{ getVerificationMethodLabel(identifyMethod.name, signatureMethodName, signatureMethod.label) }}
						</NcCheckboxRadioSwitch>
					</div>
				</fieldset>
			</div>
		</div>

		<div v-if="showGlobalOnboardingToggle" class="identify-methods-editor__global-onboarding">
			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="canCreateAccount"
				@update:modelValue="onGlobalCanCreateAccountToggle($event)">
				<div class="identify-methods-editor__onboarding-content">
					<span>{{ t('libresign', 'Automatically create account') }}</span>
					<p>{{ t('libresign', 'Create an account when the signer does not already have one.') }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeIdentifyMethodsPolicyConfig,
	normalizeIdentifyMethodsPolicy,
	serializeIdentifyMethodsPolicy,
} from './model'
import type { IdentifyMethodPolicyEntry } from './model'

defineOptions({
	name: 'IdentifyMethodsRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const policyConfig = computed(() => normalizeIdentifyMethodsPolicyConfig(props.modelValue))

const entries = computed(() => {
	const normalized = policyConfig.value.factors
	return ensureSignatureMethodSelection(normalized)
})

const identifyMethodsCatalog = normalizeIdentifyMethodsPolicy(
	loadState<EffectivePolicyValue>('libresign', 'identify_methods', []),
)

const signatureMethodLabelsByMethod = new Map<string, Map<string, string>>()
const signatureMethodLabelsGlobal = new Map<string, string>()
for (const identifyMethod of identifyMethodsCatalog) {
	const labels = new Map<string, string>()
	for (const [signatureMethodName, signatureMethodConfig] of Object.entries(identifyMethod.signatureMethods)) {
		if (typeof signatureMethodConfig.label === 'string' && signatureMethodConfig.label.trim().length > 0) {
			labels.set(signatureMethodName, signatureMethodConfig.label)
			if (!signatureMethodLabelsGlobal.has(signatureMethodName)) {
				signatureMethodLabelsGlobal.set(signatureMethodName, signatureMethodConfig.label)
			}
		}
	}

	if (labels.size > 0) {
		signatureMethodLabelsByMethod.set(identifyMethod.name, labels)
	}
}

const methodsSupportingAccountCreation = new Set(['email'])

const showGlobalOnboardingToggle = computed(() => entries.value.some((entry) => methodsSupportingAccountCreation.has(entry.name)))
const canCreateAccount = computed(() => policyConfig.value.global.canCreateAccount ?? true)

const enabledCount = computed(() => entries.value.filter((entry) => entry.enabled).length)
const canAdjustRequirement = computed(() => enabledCount.value > 1)

function onMethodToggle(index: number, enabled: boolean): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		enabled,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries), policyConfig.value.global))
}

function onRequirementToggle(index: number, required: boolean): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		requirement: required ? 'required' : 'optional',
		mandatory: required,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries), policyConfig.value.global))
}

function onSignatureMethodChange(index: number, signatureMethodName: string): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		signatureMethodEnabled: signatureMethodName,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries), policyConfig.value.global))
}

function onGlobalCanCreateAccountToggle(canCreateAccount: boolean): void {
	emit('update:modelValue', serializeIdentifyMethodsPolicy(entries.value, {
		...policyConfig.value.global,
		canCreateAccount,
	}))
}

function isRequired(entry: IdentifyMethodPolicyEntry): boolean {
	return entry.requirement === 'required' || Boolean(entry.mandatory)
}

function getVerificationMethodLabel(identifyMethodName: string, signatureMethodName: string, fallbackLabel?: string): string {
	if (typeof fallbackLabel === 'string' && fallbackLabel.trim().length > 0) {
		return fallbackLabel
	}

	const catalogLabel = signatureMethodLabelsByMethod.get(identifyMethodName)?.get(signatureMethodName)
	if (catalogLabel) {
		return catalogLabel
	}

	const globalCatalogLabel = signatureMethodLabelsGlobal.get(signatureMethodName)
	if (globalCatalogLabel) {
		return globalCatalogLabel
	}

	return t('libresign', 'Verification option')
}

function ensureSignatureMethodSelection(entries: IdentifyMethodPolicyEntry[]): IdentifyMethodPolicyEntry[] {
	return entries.map((entry) => {
		const signatureMethodNames = Object.keys(entry.signatureMethods)
		if (signatureMethodNames.length === 0) {
			return {
				...entry,
				signatureMethodEnabled: undefined,
			}
		}

		let selectedSignatureMethod = entry.signatureMethodEnabled
		if (!selectedSignatureMethod || !signatureMethodNames.includes(selectedSignatureMethod)) {
			selectedSignatureMethod = signatureMethodNames.find((signatureMethodName) => entry.signatureMethods[signatureMethodName]?.enabled)
				?? signatureMethodNames[0]
		}

		const signatureMethods = Object.fromEntries(
			signatureMethodNames.map((signatureMethodName) => [
				signatureMethodName,
				{
					...entry.signatureMethods[signatureMethodName],
					enabled: signatureMethodName === selectedSignatureMethod,
				},
			]),
		)

		return {
			...entry,
			signatureMethods,
			signatureMethodEnabled: selectedSignatureMethod,
		}
	})
}
</script>

<style scoped lang="scss">
.identify-methods-editor {
	display: flex;
	flex-direction: column;
	gap: 0.22rem;
}

.identify-methods-editor__empty {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.identify-methods-editor__method {
	display: flex;
	flex-direction: column;
	gap: 0.14rem;
	padding: 0.3rem 0.42rem;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background-color: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-darker));
}

.identify-methods-editor__method-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 0.28rem;
	flex-wrap: wrap;

	:deep(.checkbox-radio-switch) {
		margin: 0;
	}
}

.identify-methods-editor__method-main-toggle {
	flex: 1 1 auto;

	:deep(.checkbox-content) {
		font-weight: 500;
	}
}

.identify-methods-editor__requirement-area {
	flex-shrink: 0;
	display: inline-flex;
	align-items: center;
	margin-top: 0.06rem;
}

.identify-methods-editor__requirement-switch {
	:deep(.checkbox-radio-switch) {
		--checkbox-padding: 0.28rem 0;
	}

	:deep(.checkbox-content) {
		font-size: 0.74rem;
		font-weight: 400;
		color: var(--color-text-maxcontrast);
		opacity: 0.75;
	}
}

.identify-methods-editor__required-helper {
	margin: 0;
	font-size: 0.72rem;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
	opacity: 0.75;
}

.identify-methods-editor__method-details {
	display: flex;
	flex-direction: column;
	gap: 0.12rem;
}

.identify-methods-editor__sub-section {
	display: flex;
	flex-direction: column;
	gap: 0.08rem;
	border: 0;
	margin: 0 0 0 0.95rem;
	padding: 0;

	:deep(.checkbox-radio-switch) {
		margin: 0.02rem 0;
	}
}

.identify-methods-editor__sub-section legend {
	padding: 0;
	margin-bottom: 0.02rem;
	font-weight: 500;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
	opacity: 0.82;
}

.identify-methods-editor__verification-options {
	display: flex;
	flex-direction: column;
	gap: 0.12rem;
}

.identify-methods-editor__verification-switch {
	:deep(.checkbox-radio-switch) {
		margin: 0;
	}

	:deep(.checkbox-content) {
		font-size: 0.77rem;
		line-height: 1.28;
		color: var(--color-text-maxcontrast);
		opacity: 0.88;
		white-space: normal;
		word-break: break-word;
	}
}

.identify-methods-editor__verification-switch--selected {
	:deep(.checkbox-content) {
		opacity: 1;
		font-weight: 500;
	}
}

.identify-methods-editor__global-onboarding {
	padding: 0.18rem 0 0;
	border-top: 1px solid color-mix(in srgb, var(--color-border) 48%, transparent);

	:deep(.checkbox-radio-switch) {
		--checkbox-padding: 0.28rem 0;
		margin: 0;
	}
}

.identify-methods-editor__onboarding-content {
	display: flex;
	flex-direction: column;
	gap: 0.1rem;

	span {
		font-size: 0.85rem;
		font-weight: 500;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.77rem;
		font-weight: 400;
		color: var(--color-text-maxcontrast);
		opacity: 0.8;
		line-height: 1.32;
	}
}
</style>
