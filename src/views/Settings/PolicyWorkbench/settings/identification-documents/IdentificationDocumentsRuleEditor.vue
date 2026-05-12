<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="identification-documents-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="String(option.value)"
			class="identification-documents-editor__option"
			type="radio"
			:model-value="draft.enabled === option.value"
			name="identification-documents-editor"
			@update:modelValue="updateEnabled(option.value, $event)">
			<div class="identification-documents-editor__copy">
				<strong>{{ option.label }}</strong>
				<p>{{ option.description }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<!-- Approvers section - visible only when enabled -->
		<div v-if="draft.enabled" class="identification-documents-editor__approvers-section">
			<label>{{ $t('libresign', 'Approver groups') }}</label>
			<p class="identification-documents-editor__help-text">
				{{ $t('libresign', 'Select which groups can approve identification documents.') }}
			</p>
			<NcSelect
				v-model="draft.approvers"
				:options="groupOptions"
				:placeholder="$t('libresign', 'Select groups...')"
				multiple
				track-by="id"
				label="displayName"
				:clearable="false"
				:loading="groupsLoading"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { EffectivePolicyValue } from '../../../../../types/index'

defineOptions({
	name: 'IdentificationDocumentsRuleEditor',
})

interface IdentificationDocumentsPayload {
	enabled: boolean
	approvers: string[]
}

interface GroupOption {
	id: string
	displayName: string
}

const props = defineProps<{
	modelValue: EffectivePolicyValue
	scope?: 'system' | 'group' | 'user'
	targetId?: string
}>()

const emit = defineEmits<{
	'update:modelValue': [value: IdentificationDocumentsPayload]
}>()

const options = [
	{
		value: true,
		label: t('libresign', 'Enabled'),
		description: t('libresign', 'Request signers to submit identification documents before certificate issuance.'),
	},
	{
		value: false,
		label: t('libresign', 'Disabled'),
		description: t('libresign', 'Do not request identification documents in the signing flow.'),
	},
]

const groupsLoading = ref(false)
const availableGroups = ref<GroupOption[]>([])

function createDraftFromValue(value: EffectivePolicyValue): IdentificationDocumentsPayload {
	if (typeof value === 'object' && value !== null && 'enabled' in value) {
		const payload = value as Record<string, unknown>
		return {
			enabled: typeof payload.enabled === 'boolean' ? payload.enabled : false,
			approvers: Array.isArray(payload.approvers) ? (payload.approvers as string[]) : ['admin'],
		}
	}

	return {
		enabled: false,
		approvers: ['admin'],
	}
}

const draft = ref<IdentificationDocumentsPayload>(createDraftFromValue(props.modelValue))

const groupOptions = computed(() => {
	return availableGroups.value.map(group => ({
		id: group.id,
		displayName: group.displayName,
	}))
})

function updateEnabled(enabled: boolean, selected?: unknown) {
	if (selected === false) {
		return
	}

	draft.value.enabled = enabled

	// Reset approvers to default if disabling
	if (!enabled) {
		draft.value.approvers = ['admin']
	}

	emitChange()
}

function emitChange() {
	emit('update:modelValue', draft.value)
}

onMounted(async () => {
	// Load available groups based on scope
	await loadGroups()
})

async function loadGroups() {
	groupsLoading.value = true
	try {
		// For now, we'll use a placeholder implementation
		// In production, this would call an API to fetch groups
		const systemGroups: GroupOption[] = [
			{ id: 'admin', displayName: t('libresign', 'Admin') },
			{ id: 'approvers', displayName: t('libresign', 'Approvers') },
		]

		if (props.scope === 'user') {
			// Users don't need group selection
			availableGroups.value = []
		} else {
			availableGroups.value = systemGroups
		}
	} finally {
		groupsLoading.value = false
	}
}
</script>

<style scoped lang="scss">
.identification-documents-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__approvers-section {
		margin-top: 1.5rem;
		padding: 1rem;
		border: 1px solid var(--color-border);
		border-radius: 4px;
		background-color: var(--color-background-hover);

		& label {
			display: block;
			font-weight: 600;
			margin-bottom: 0.25rem;
		}
	}

	&__help-text {
		margin: 0.25rem 0 0.75rem;
		font-size: 0.9rem;
		color: var(--color-text-maxcontrast);
	}

	:deep(.identification-documents-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.identification-documents-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.identification-documents-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
