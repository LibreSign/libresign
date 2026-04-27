<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Identification documents')"
		:description="t('libresign', 'Configure which groups can approve submitted identification documents. Admin group members can always approve.')">
		<p>
			{{ t('libresign', 'The Identification documents flow itself is managed in Policies.') }}
		</p>
		<p>
			{{ t('libresign', 'Approval groups are used only when the effective policy enables this flow.') }}
		</p>
		<NcSelect :key="idApprovalGroupsKey"
			v-model="approvalGroups"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="description"
			:close-on-select="false"
			:disabled="loadingGroups || !identificationDocumentsFlowEnabled"
			:loading="loadingGroups"
			:multiple="true"
			:options="groups"
			:searchable="true"
			:show-no-options="false"
			@search-change="searchGroup"
			@update:modelValue="saveApprovalGroups" />
		<p v-if="!identificationDocumentsFlowEnabled" class="identification-documents-content__hint">
			{{ t('libresign', 'This list is disabled because the effective policy currently disables identification documents flow.') }}
		</p>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from '../../logger.js'

defineOptions({
	name: 'IdentificationDocuments',
})

type Group = {
	id: string
	displayname: string
}

const approvalGroupState = loadState('libresign', 'approval_group', ['admin'])
const effectivePoliciesState = loadState<{ policies?: Record<string, { effectiveValue?: unknown }> }>('libresign', 'effective_policies', { policies: {} })

function resolveEnabledValue(value: unknown): boolean {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		return value !== 0
	}

	if (typeof value === 'string') {
		return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase())
	}

	return false
}

const effectivePolicies = effectivePoliciesState?.policies ?? {}
const identificationDocumentsFlowEnabled = ref(resolveEnabledValue(effectivePolicies.identification_documents?.effectiveValue))
const approvalGroupIds = ref<string[]>(Array.isArray(approvalGroupState) ? approvalGroupState : ['admin'])
const approvalGroups = ref<Array<Group | string>>([])
const groups = ref<Group[]>([])
const loadingGroups = ref(false)
const idApprovalGroupsKey = ref(0)

const description = computed(() => t('libresign', 'Search groups'))

function syncApprovalGroupsFromState() {
	approvalGroups.value = groups.value.filter((group) => approvalGroupIds.value.indexOf(group.id) !== -1)
}

function saveApprovalGroups() {
	const listOfInputGroupsSelected = JSON.stringify(approvalGroups.value.map((group) => {
		if (typeof group === 'object') {
			return group.id
		}
		return group
	}))
	approvalGroupIds.value = JSON.parse(listOfInputGroupsSelected)
	OCP.AppConfig.setValue('libresign', 'approval_group', listOfInputGroupsSelected)
	idApprovalGroupsKey.value += 1
}

async function searchGroup(query: string) {
	loadingGroups.value = true
	await axios.get(generateOcsUrl('cloud/groups/details'), {
		params: {
			search: query,
			limit: 20,
			offset: 0,
		},
	})
		.then(({ data }) => {
			groups.value = data.ocs.data.groups.sort((groupA: Group, groupB: Group) => groupA.displayname.localeCompare(groupB.displayname))
		})
		.catch((error) => logger.debug('Could not search by groups', { error }))
	loadingGroups.value = false
}

onMounted(async () => {
	await searchGroup('')
	syncApprovalGroupsFromState()
})
</script>
<style scoped>
.identification-documents-content {
	display: flex;
	flex-direction: column;
}
</style>
