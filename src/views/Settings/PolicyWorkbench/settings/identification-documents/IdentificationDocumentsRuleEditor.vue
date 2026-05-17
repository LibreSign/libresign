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
			<label>{{ t('libresign', 'Approver groups') }}</label>
			<p class="identification-documents-editor__help-text">
				{{ t('libresign', 'Select which groups can approve identification documents.') }}
			</p>
			<p v-if="!groupsLoading && groupOptions.length === 0" class="identification-documents-editor__empty-state">
				{{ t('libresign', 'No groups available for this scope. Keep the default approver group or choose another scope.') }}
			</p>
			<NcSelect
				v-model="draft.approvers"
				:options="groupOptions"
				:placeholder="t('libresign', 'Select groups...')"
				:aria-label-combobox="t('libresign', 'Select approver groups')"
				multiple
				track-by="id"
				label="displayName"
				:clearable="false"
				:loading="groupsLoading"
				@update:model-value="updateApprovers"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { IdentificationDocumentsPayload } from './realDefinition'

defineOptions({
	name: 'IdentificationDocumentsRuleEditor',
})

interface Props {
	modelValue: IdentificationDocumentsPayload
	scope?: 'system' | 'group' | 'user'
	targetId?: string
}

interface GroupOption {
	id: string
	displayName: string
}

const props = defineProps<Props>()

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

function createDraftFromValue(value: IdentificationDocumentsPayload): IdentificationDocumentsPayload {
	return {
		enabled: value.enabled,
		approvers: Array.isArray(value.approvers) ? value.approvers : ['admin'],
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

function updateApprovers(approvers: string[]) {
	draft.value.approvers = approvers.length > 0 ? approvers : ['admin']
	emitChange()
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

	&__empty-state {
		margin: 0 0 0.75rem;
		font-size: 0.85rem;
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
