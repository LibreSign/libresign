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
			<hr v-if="index !== 0">

			<NcCheckboxRadioSwitch type="switch"
				:model-value="identifyMethod.enabled"
				@update:modelValue="onMethodToggle(index, $event)">
				{{ identifyMethod.friendly_name ?? identifyMethod.name }}
			</NcCheckboxRadioSwitch>

			<div v-if="identifyMethod.enabled" class="identify-methods-editor__method-details">
				<fieldset v-if="identifyMethod.name === 'email'" class="identify-methods-editor__sub-section">
					<NcCheckboxRadioSwitch :model-value="Boolean(identifyMethod.can_create_account)"
						@update:modelValue="onCanCreateAccountToggle(index, $event)">
						{{ t('libresign', 'Request to create account when the user does not have an account') }}
					</NcCheckboxRadioSwitch>
				</fieldset>

				<fieldset class="identify-methods-editor__sub-section">
					<legend>{{ t('libresign', 'Signature methods') }}</legend>
					<NcCheckboxRadioSwitch v-for="(signatureMethod, signatureMethodName) in identifyMethod.signatureMethods"
						:key="signatureMethodName"
						type="radio"
						:name="identifyMethod.name"
						:value="signatureMethodName"
						:model-value="identifyMethod.signatureMethodEnabled"
						@update:modelValue="onSignatureMethodChange(index, String($event))">
						{{ signatureMethod.label ?? signatureMethodName }}
					</NcCheckboxRadioSwitch>
				</fieldset>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeIdentifyMethodsPolicy,
	serializeIdentifyMethodsPolicy,
	type IdentifyMethodPolicyEntry,
} from './model'

defineOptions({
	name: 'IdentifyMethodsRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: IdentifyMethodPolicyEntry[]]
}>()

const entries = computed(() => {
	const normalized = normalizeIdentifyMethodsPolicy(props.modelValue)
	return ensureSignatureMethodSelection(normalized)
})

function onMethodToggle(index: number, enabled: boolean): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		enabled,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries).filter((entry) => entry.enabled)))
}

function onCanCreateAccountToggle(index: number, canCreateAccount: boolean): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		can_create_account: canCreateAccount,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries).filter((entry) => entry.enabled)))
}

function onSignatureMethodChange(index: number, signatureMethodName: string): void {
	const nextEntries = [...entries.value]
	nextEntries[index] = {
		...nextEntries[index],
		signatureMethodEnabled: signatureMethodName,
	}
	emit('update:modelValue', serializeIdentifyMethodsPolicy(ensureSignatureMethodSelection(nextEntries).filter((entry) => entry.enabled)))
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
	gap: 0.5rem;
}

.identify-methods-editor__empty {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.identify-methods-editor__method {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.identify-methods-editor__method-details {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.identify-methods-editor__sub-section {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
	border: 0;
	margin: 0 0 0 1.5rem;
	padding: 0;
}

.identify-methods-editor__sub-section legend {
	padding: 0;
	margin-bottom: 0.25rem;
	font-weight: 600;
}
</style>
