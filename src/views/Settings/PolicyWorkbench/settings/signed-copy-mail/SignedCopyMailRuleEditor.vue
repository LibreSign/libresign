<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signed-copy-mail-editor">
		<NcCheckboxRadioSwitch class="signed-copy-mail-editor__switch"
			type="switch"
			:model-value="value.enabled"
			@update:modelValue="onToggleEnabled">
			<div class="signed-copy-mail-editor__copy">
				<strong>{{ title }}</strong>
				<p>{{ description }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<NcTextField v-if="value.enabled"
			:model-value="archiveText"
			:label="archiveLabel"
			:helper-text="archiveHelperText"
			@update:modelValue="onArchiveChange" />
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeSignedCopyMailPolicyConfig,
	parseArchiveAddresses,
	serializeSignedCopyMailPolicyConfig,
} from './model'

defineOptions({
	name: 'SignedCopyMailRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: string]
}>()

// TRANSLATORS Toggle title for mailing the fully signed document after the last signature.
const title = t('libresign', 'Email the signed document')
// TRANSLATORS Toggle description. The requester chooses, for each signer, whether that signer receives the signed document by email.
const description = t('libresign', 'After the last signature, send the signed document as an attachment to the signers chosen by the requester.')
// TRANSLATORS Label of an optional field with email addresses that receive every fully signed document.
const archiveLabel = t('libresign', 'Archive addresses (optional)')
// TRANSLATORS Helper text below the archive addresses field.
const archiveHelperText = t('libresign', 'Every signed document is also sent to these addresses. Separate addresses with commas.')

const value = computed(() => normalizeSignedCopyMailPolicyConfig(props.modelValue))
const archiveText = ref(value.value.archive_addresses.join(', '))

watch(() => value.value.archive_addresses, (addresses) => {
	// Keep what the user is typing unless the stored list really changed.
	if (parseArchiveAddresses(archiveText.value).join(',') !== addresses.join(',')) {
		archiveText.value = addresses.join(', ')
	}
})

function onToggleEnabled(enabled: boolean) {
	emit('update:modelValue', serializeSignedCopyMailPolicyConfig({ ...value.value, enabled }))
}

function onArchiveChange(text: string | number) {
	archiveText.value = String(text)
	emit('update:modelValue', serializeSignedCopyMailPolicyConfig({
		...value.value,
		archive_addresses: parseArchiveAddresses(archiveText.value),
	}))
}
</script>

<style scoped lang="scss">
.signed-copy-mail-editor {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.signed-copy-mail-editor__switch.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signed-copy-mail-editor__switch .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signed-copy-mail-editor__switch.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
