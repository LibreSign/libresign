<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="identification-documents-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="draft.enabled"
			@update:modelValue="updateEnabled">
			<div class="identification-documents-editor__switch-copy">
				<span>{{ identificationDocumentsFlowTitle }}</span>
				<p>{{ identificationDocumentsFlowDescription }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<div v-if="draft.enabled" class="identification-documents-editor__approvers-section">
			<label class="identification-documents-editor__approvers-title">{{ approverGroupsLabel }}</label>
			<p class="identification-documents-editor__help-text">
				{{ approverGroupsHelpText }}
			</p>
			<p v-if="!groupsLoading && groupOptions.length === 0" class="identification-documents-editor__empty-state">
				{{ noGroupsAvailableText }}
			</p>
			<NcSelect
				:model-value="selectedApprovers"
				:options="groupOptions"
				:placeholder="selectGroupsPlaceholder"
				:aria-label-combobox="selectApproverGroupsAriaLabel"
				multiple
				track-by="id"
				label="displayName"
				:clearable="false"
				:loading="groupsLoading"
				@update:modelValue="updateApprovers"
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

type ApproverSelection = Array<GroupOption | string>

const props = defineProps<Props>()

const emit = defineEmits<{
	'update:modelValue': [value: IdentificationDocumentsPayload]
}>()

// TRANSLATORS Toggle label for enabling the workflow that asks signers to upload identification documents.
const identificationDocumentsFlowTitle = t('libresign', 'Enable identification documents flow')
// TRANSLATORS Toggle description explaining that signers must provide identification documents before a certificate is issued.
const identificationDocumentsFlowDescription = t('libresign', 'Request signers to submit identification documents before certificate issuance.')
// TRANSLATORS Section label for the group selector that chooses who may approve identification documents.
const approverGroupsLabel = t('libresign', 'Approver groups')
// TRANSLATORS Helper text explaining that the selected groups are allowed to approve submitted identification documents.
const approverGroupsHelpText = t('libresign', 'Select which groups can approve identification documents.')
// TRANSLATORS Empty-state message shown when there are no available groups to choose as document approvers in the current scope.
const noGroupsAvailableText = t('libresign', 'No groups available for this scope. Keep the default approver group or choose another scope.')
// TRANSLATORS Placeholder text in the multi-select used to choose approver groups.
const selectGroupsPlaceholder = t('libresign', 'Select groups...')
// TRANSLATORS Accessible label for the multi-select that chooses approver groups for identification documents.
const selectApproverGroupsAriaLabel = t('libresign', 'Select approver groups')
// TRANSLATORS Fallback display name for the administrator group in the temporary approver-group selector.
const adminGroupDisplayName = t('libresign', 'Admin')
// TRANSLATORS Fallback display name for the generic approvers group in the temporary approver-group selector.
const approversGroupDisplayName = t('libresign', 'Approvers')

const groupsLoading = ref(false)
const availableGroups = ref<GroupOption[]>([])

function createDraftFromValue(value: IdentificationDocumentsPayload): IdentificationDocumentsPayload {
	return {
		enabled: value.enabled,
		approvers: normalizeApproverIds(value.approvers),
	}
}

const draft = ref<IdentificationDocumentsPayload>(createDraftFromValue(props.modelValue))

const selectedApprovers = computed<ApproverSelection>(() => {
	return draft.value.approvers.map((approverId: string) => {
		return availableGroups.value.find((group: GroupOption) => group.id === approverId) ?? approverId
	})
})

const groupOptions = computed(() => {
	return availableGroups.value.map((group: GroupOption) => ({
		id: group.id,
		displayName: group.displayName,
	}))
})

function normalizeApproverIds(value: unknown): string[] {
	if (!Array.isArray(value)) {
		return ['admin']
	}

	const approvers = value
		.map((entry): string => {
			if (typeof entry === 'string') {
				return entry.trim()
			}

			if (entry && typeof entry === 'object' && 'id' in (entry as GroupOption)) {
				const id = (entry as { id?: unknown }).id
				return typeof id === 'string' ? id.trim() : ''
			}

			return ''
		})
		.filter((entry) => entry.length > 0)

	return approvers.length > 0 ? approvers : ['admin']
}

function updateEnabled(enabled: boolean) {
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

function updateApprovers(approvers: ApproverSelection) {
	draft.value.approvers = normalizeApproverIds(approvers)
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
			{ id: 'admin', displayName: adminGroupDisplayName },
			{ id: 'approvers', displayName: approversGroupDisplayName },
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

	&__switch-copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__approvers-section {
		margin-left: 0.25rem;
	}

	&__approvers-title {
		display: block;
		font-weight: 600;
		margin-bottom: 0.25rem;
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
}
</style>
