<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Signing order by group')"
		:description="t('libresign', 'Select a group to define a signing order override that is applied before user and request-level preferences.')">
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>

		<NcSelect
			v-model="selectedGroup"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Select a group to configure signing order policy')"
			:disabled="loadingGroups || saving"
			:loading="loadingGroups"
			:options="groups"
			:searchable="true"
			:show-no-options="false"
			@search-change="searchGroup"
			@update:modelValue="onGroupChange" />

		<div v-if="selectedGroup" class="signature-flow-group-policy">
			<div class="signature-flow-group-policy__toggle">
				<NcCheckboxRadioSwitch
					type="switch"
					v-model="enabled"
					:disabled="saving"
					@update:modelValue="onToggleChange">
					<span>{{ t('libresign', 'Use a custom signing order for this group') }}</span>
				</NcCheckboxRadioSwitch>
				<span v-if="saving" class="signature-flow-group-policy__status">
					<NcLoadingIcon :size="20" />
				</span>
				<span v-else-if="saved" class="signature-flow-group-policy__status">
					<NcSavingIndicatorIcon :size="20" />
				</span>
			</div>

			<div v-if="enabled" class="signature-flow-group-policy__content">
				<NcCheckboxRadioSwitch
					type="switch"
					v-model="allowChildOverride"
					:disabled="saving"
					@update:modelValue="onAllowChildOverrideChange">
					<span>{{ t('libresign', 'Allow user and request-level overrides for this group default') }}</span>
				</NcCheckboxRadioSwitch>

				<NcCheckboxRadioSwitch
					v-for="flow in availableFlows"
					:key="flow.value"
					type="radio"
					v-model="selectedFlowValue"
					:value="flow.value"
					:disabled="saving"
					name="signature_flow_group_policy"
					@update:modelValue="onFlowChange">
					<div class="signature-flow-group-policy__option">
						<div>
							<strong>{{ flow.label }}</strong>
							<p>{{ flow.description }}</p>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../store/policies'
import type { GroupPolicyState, SignatureFlowMode } from '../../types/index'

import '@nextcloud/password-confirmation/style.css'

defineOptions({
	name: 'SignatureFlowGroupPolicy',
})

type GroupRow = {
	id: string
	displayname: string
}

type FlowOption = {
	value: SignatureFlowMode
	label: string
	description: string
}

const policiesStore = usePoliciesStore()
const groups = ref<GroupRow[]>([])
const selectedGroup = ref<GroupRow | null>(null)
const loadingGroups = ref(false)
const saving = ref(false)
const saved = ref(false)
const errorMessage = ref('')
const enabled = ref(false)
const allowChildOverride = ref(true)
const selectedFlow = ref<FlowOption | null>(null)

const availableFlows: FlowOption[] = [
	{
		value: 'parallel',
		label: t('libresign', 'Simultaneous (Parallel)'),
		description: t('libresign', 'All signers receive the document at the same time and can sign in any order.'),
	},
	{
		value: 'ordered_numeric',
		label: t('libresign', 'Sequential'),
		description: t('libresign', 'Signers are organized by signing order number. Only those with the lowest pending order number can sign.'),
	},
]

const defaultFlow = availableFlows[0]!
const selectedFlowValue = ref<SignatureFlowMode>(defaultFlow.value)

function applyGroupPolicy(policy: GroupPolicyState | null) {
	const value = policy?.value
	if (policy && (value === 'parallel' || value === 'ordered_numeric')) {
		enabled.value = true
		allowChildOverride.value = policy.allowChildOverride
		selectedFlow.value = availableFlows.find((flow) => flow.value === value) ?? defaultFlow
		selectedFlowValue.value = selectedFlow.value.value
		return
	}

	enabled.value = false
	allowChildOverride.value = true
	selectedFlow.value = defaultFlow
	selectedFlowValue.value = defaultFlow.value
}

async function searchGroup(query: string) {
	loadingGroups.value = true
	try {
		const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
			params: {
				search: query,
				limit: 20,
				offset: 0,
			},
		})
		groups.value = response.data.ocs.data.groups.sort((a: GroupRow, b: GroupRow) => a.displayname.localeCompare(b.displayname))
	} catch (error) {
		console.error('Could not search groups for signature flow policy', error)
		errorMessage.value = t('libresign', 'Could not load groups. Try again.')
	} finally {
		loadingGroups.value = false
	}
}

async function onGroupChange(group: GroupRow | null) {
	selectedGroup.value = group
	errorMessage.value = ''
	if (!group) {
		applyGroupPolicy(null)
		return
	}

	try {
		const policy = await policiesStore.fetchGroupPolicy(group.id, 'signature_flow')
		applyGroupPolicy(policy)
	} catch (error) {
		console.error('Could not load group signature flow policy', error)
		errorMessage.value = t('libresign', 'Could not load the selected group policy.')
		applyGroupPolicy(null)
	}
}

async function persistPolicy() {
	if (!selectedGroup.value) {
		return
	}

	saving.value = true
	errorMessage.value = ''
	saved.value = false

	try {
		await confirmPassword()
		if (!enabled.value) {
			const clearedPolicy = await policiesStore.clearGroupPolicy(selectedGroup.value.id, 'signature_flow')
			applyGroupPolicy(clearedPolicy)
		} else {
			const policy = await policiesStore.saveGroupPolicy(
				selectedGroup.value.id,
				'signature_flow',
				selectedFlowValue.value,
				allowChildOverride.value,
			)
			applyGroupPolicy(policy)
		}
		saved.value = true
		setTimeout(() => {
			saved.value = false
		}, 3000)
	} catch (error) {
		console.error('Could not save group signature flow policy', error)
		errorMessage.value = t('libresign', 'Could not save the group policy. Try again.')
	} finally {
		saving.value = false
	}
}

async function onToggleChange() {
	await persistPolicy()
}

async function onAllowChildOverrideChange() {
	await persistPolicy()
}

async function onFlowChange() {
	selectedFlow.value = availableFlows.find((flow) => flow.value === selectedFlowValue.value) ?? defaultFlow
	await persistPolicy()
}

onMounted(async () => {
	await searchGroup('')
})

defineExpose({
	groups,
	selectedGroup,
	loadingGroups,
	saving,
	saved,
	errorMessage,
	enabled,
	allowChildOverride,
	selectedFlow,
	selectedFlowValue,
	availableFlows,
	searchGroup,
	onGroupChange,
	onToggleChange,
	onAllowChildOverrideChange,
	onFlowChange,
})
</script>

<style lang="scss" scoped>
.signature-flow-group-policy {
	margin-top: 1rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__toggle {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	&__status {
		display: flex;
		align-items: center;
	}

	&__content {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__option p {
		margin: 0.25rem 0 0;
		color: var(--color-text-maxcontrast);
		font-size: 90%;
	}
}
</style>
