<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="external-signing-enabled-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="enabled"
			@update:modelValue="onChange">
			<div>
				<strong>{{ enableLabel }}</strong>
				<p>{{ enableHelp }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'

const props = defineProps<{ modelValue: EffectivePolicyValue }>()
const emit = defineEmits<{ 'update:modelValue': [value: EffectivePolicyValue] }>()

// TRANSLATORS Policy editor toggle that permits the selected scope to use external signing.
const enableLabel = t('libresign', 'Allow external signing providers')
// TRANSLATORS Policy editor help for account/group signer access, which requires separate backend checks.
const enableHelp = t('libresign', 'Control whether the selected accounts or groups can use an external signing integration. Providers must also be configured and enabled.')

const enabled = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === 'true' || props.modelValue === '1')
function onChange(value: boolean) {
	emit('update:modelValue', value)
}
</script>

<style scoped lang="scss">
.external-signing-enabled-editor p {
	margin: 0.35rem 0 0;
	color: var(--color-text-maxcontrast);
}
</style>
