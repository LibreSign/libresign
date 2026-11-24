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
		<p v-if="editorMode === 'create'" class="external-signing-enabled-editor__help">
			{{ saveFirstHelp }}
		</p>
		<p v-else-if="editorScope !== 'system' && scopeTargetIds.length !== 1" class="external-signing-enabled-editor__help">
			{{ selectSingleScopeHelp }}
		</p>
		<ExternalSigningAdmin
			v-else-if="adminAdapter"
			:key="scopeKey"
			:adapter="adminAdapter"
			embedded
			layout="compact" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { ExternalSigningScope } from '../../../../../services/externalSigningAdmin'
import { createExternalSigningAdminOcsAdapter } from '../../../../../services/externalSigningAdminOcs'
import ExternalSigningAdmin from '../../../ExternalSigning/ExternalSigningAdmin.vue'

const props = withDefaults(defineProps<{
	modelValue: EffectivePolicyValue
	editorScope?: 'system' | 'group' | 'user'
	editorMode?: 'create' | 'edit' | null
	scopeTargetIds?: string[]
}>(), {
	editorScope: 'system',
	editorMode: null,
	scopeTargetIds: () => [],
})
const emit = defineEmits<{ 'update:modelValue': [value: EffectivePolicyValue] }>()

// TRANSLATORS Policy editor toggle that permits the selected scope to use external signing.
const enableLabel = t('libresign', 'Allow external signing providers')
// TRANSLATORS Policy editor help for account/group signer access, which requires separate backend checks.
const enableHelp = t('libresign', 'Control whether the selected accounts or groups can use external signing connections.')
// TRANSLATORS Help explaining that a policy rule must be created before provider credentials can be configured for its scope.
const saveFirstHelp = t('libresign', 'Save the policy rule first, then edit it to configure CSC connections for this scope.')
// TRANSLATORS Help when multiple group or account targets are selected in a rule editor.
const selectSingleScopeHelp = t('libresign', 'Edit one group or account rule at a time to configure its CSC connections.')

const enabled = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === 'true' || props.modelValue === '1')
const scope = computed<ExternalSigningScope | null>(() => {
	if (props.editorMode !== 'edit') {
		return null
	}
	if (props.editorScope === 'system') {
		return { scopeType: 'system', scopeId: '' }
	}
	if (props.scopeTargetIds.length !== 1 || !props.scopeTargetIds[0]) {
		return null
	}
	return { scopeType: props.editorScope, scopeId: props.scopeTargetIds[0] }
})
const scopeKey = computed(() => `${scope.value?.scopeType ?? ''}:${scope.value?.scopeId ?? ''}`)
const adminAdapter = computed(() => scope.value ? createExternalSigningAdminOcsAdapter(scope.value) : null)
function onChange(value: boolean) {
	emit('update:modelValue', value)
}
</script>

<style scoped lang="scss">
.external-signing-enabled-editor {
	p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__help {
		margin-top: 1rem;
	}
}
</style>
