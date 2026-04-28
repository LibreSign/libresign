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
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from '../../logger.js'
import { usePoliciesStore } from '../../store/policies'

defineOptions({
	name: 'IdentificationDocuments',
})

type Group = {
	id: string
	displayname: string
}

const policiesStore = usePoliciesStore()

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

function resolveApprovalGroupsValue(value: unknown): string[] {
	if (Array.isArray(value)) {
		return value.filter((groupId): groupId is string => typeof groupId === 'string' && groupId.trim().length > 0)
	}

	if (typeof value === 'string') {
		const trimmed = value.trim()
		if (!trimmed) {
			return []
		}

		try {
			const parsed = JSON.parse(trimmed)
			if (Array.isArray(parsed)) {
				return parsed.filter((groupId): groupId is string => typeof groupId === 'string' && groupId.trim().length > 0)
			}
		} catch (error) {
			logger.debug('Could not parse approval groups policy value', { error })
		}
	}

	return ['admin']
}

const identificationDocumentsFlowEnabled = ref(resolveEnabledValue(policiesStore.getEffectiveValue('identification_documents')))
const approvalGroupIds = ref<string[]>(resolveApprovalGroupsValue(policiesStore.getEffectiveValue('approval_group')))
const approvalGroups = ref<Array<Group | string>>([])
const groups = ref<Group[]>([])
const loadingGroups = ref(false)
const idApprovalGroupsKey = ref(0)

const description = computed(() => t('libresign', 'Search groups'))

function syncApprovalGroupsFromState() {
	approvalGroups.value = groups.value.filter((group) => approvalGroupIds.value.indexOf(group.id) !== -1)
}

async function saveApprovalGroups() {
	const groupIds = approvalGroups.value.map((group) => {
		if (typeof group === 'object') {
			return group.id
		}
		return group
	})

	const normalized = Array.from(new Set(groupIds.filter((groupId): groupId is string => typeof groupId === 'string' && groupId.trim().length > 0))).sort()
	const saved = await policiesStore.saveSystemPolicy('approval_group', JSON.stringify(normalized), false)
	if (saved) {
		approvalGroupIds.value = resolveApprovalGroupsValue(saved.effectiveValue)
	}
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
	await policiesStore.fetchEffectivePolicies()
	identificationDocumentsFlowEnabled.value = resolveEnabledValue(policiesStore.getEffectiveValue('identification_documents'))
	approvalGroupIds.value = resolveApprovalGroupsValue(policiesStore.getEffectiveValue('approval_group'))
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
