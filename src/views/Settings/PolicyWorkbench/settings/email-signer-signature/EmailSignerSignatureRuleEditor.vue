<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="email-signer-signature-editor">
		<NcCheckboxRadioSwitch
			class="email-signer-signature-editor__switch"
			type="switch"
			:model-value="normalizedValue === true"
			@update:modelValue="onChange">
			<div class="email-signer-signature-editor__copy">
				<strong>{{ title }}</strong>
				<p>{{ description }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { resolveEmailSignerSignature } from './model'

defineOptions({
	name: 'EmailSignerSignatureRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Toggle title for letting signers without an account, identified by email, reuse their visible signature on later documents.
const title = t('libresign', 'Remember signatures of email signers')
// TRANSLATORS Toggle description. The "confirm method" is the one configured for the Email identification factor, such as click to sign or email token.
const description = t('libresign', 'Signers identified by email can choose to save their visible signature and reuse it on later documents after passing the confirm method configured for the Email identification factor.')

const normalizedValue = computed<boolean | null>(() => resolveEmailSignerSignature(props.modelValue))

function onChange(enabled: boolean) {
	emit('update:modelValue', enabled)
}
</script>

<style scoped lang="scss">
.email-signer-signature-editor {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.email-signer-signature-editor__switch.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.email-signer-signature-editor__switch .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.email-signer-signature-editor__switch.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
