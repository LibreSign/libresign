<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="approval-groups-editor">
		<p class="approval-groups-editor__hint">
			{{ t('libresign', 'Members of the selected groups can approve submitted identification documents. Admin group members can always approve.') }}
		</p>

		<NcSelect
			:model-value="selectedGroups"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Select groups allowed to approve identification documents')"
			:close-on-select="false"
			:disabled="loadingGroups"
			:loading="loadingGroups"
			:multiple="true"
			:options="availableGroups"
			:searchable="true"
			:show-no-options="false"
			@search-change="searchGroup"
			@update:modelValue="onGroupsChange" />
	</div>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onMounted, ref, watch } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'

import logger from '../../../../../logger.js'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { resolveApprovalGroups, serializeApprovalGroups } from './model'

type GroupRow = {
	id: string
	displayname: string
}

type GroupDetailsResponse = {
	ocs?: {
		data?: {
			groups?: Array<{
				id: string
				displayname?: string
			}>
		}
	}
}

type GroupListResponse = {
	ocs?: {
		data?: {
			groups?: string[]
		}
	}
}

defineOptions({
	name: 'ApprovalGroupsRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const selectedGroupIds = ref<string[]>([])
const availableGroups = ref<GroupRow[]>([])
const loadingGroups = ref(false)
const currentUser = getCurrentUser()
const isInstanceAdmin = currentUser?.isAdmin === true
const config = loadState<{ manageable_policy_group_ids?: string[] }>('libresign', 'config', {})
const manageableGroupIds = new Set(
	Array.isArray(config.manageable_policy_group_ids)
		? config.manageable_policy_group_ids.filter((groupId): groupId is string => typeof groupId === 'string' && groupId.trim().length > 0)
		: [],
)

function clampToManageableScope(groupIds: string[]): string[] {
	if (isInstanceAdmin || manageableGroupIds.size === 0) {
		return groupIds
	}

	return groupIds.filter((groupId) => manageableGroupIds.has(groupId))
}

selectedGroupIds.value = clampToManageableScope(resolveApprovalGroups(props.modelValue))

const selectedGroups = computed<Array<GroupRow | string>>(() => {
	return selectedGroupIds.value.map((groupId) => {
		return availableGroups.value.find((group) => group.id === groupId) ?? groupId
	})
})

watch(() => props.modelValue, (nextValue) => {
	selectedGroupIds.value = clampToManageableScope(resolveApprovalGroups(nextValue))
})

async function searchGroup(query: string) {
	loadingGroups.value = true
	try {
		const params = {
			search: query,
			limit: 40,
			offset: 0,
		}

		if (isInstanceAdmin) {
			const response = await axios.get<GroupDetailsResponse>(generateOcsUrl('cloud/groups/details'), { params })
			availableGroups.value = filterGroupsByManageableScope(
				(response.data?.ocs?.data?.groups ?? [])
					.map((group) => ({
						id: group.id,
						displayname: group.displayname || group.id,
					})),
			)
				.sort((left: GroupRow, right: GroupRow) => left.displayname.localeCompare(right.displayname))
			return
		}

		const response = await axios.get<GroupListResponse>(generateOcsUrl('cloud/groups'), { params })
		availableGroups.value = filterGroupsByManageableScope(
			(response.data?.ocs?.data?.groups ?? [])
				.map((groupId) => ({
					id: groupId,
					displayname: groupId,
				})),
		)
			.sort((left: GroupRow, right: GroupRow) => left.displayname.localeCompare(right.displayname))
	} catch (error) {
		logger.debug('Could not search groups for approval-group policy editor', { error })
	} finally {
		loadingGroups.value = false
	}
}

function filterGroupsByManageableScope(groups: GroupRow[]): GroupRow[] {
	if (isInstanceAdmin || manageableGroupIds.size === 0) {
		return groups
	}

	return groups.filter((group) => manageableGroupIds.has(group.id))
}

function onGroupsChange(value: Array<GroupRow | string>) {
	const nextSelectedGroupIds = value
		.map((group): string => typeof group === 'string' ? group : group.id)
		.map((groupId) => groupId.trim())
		.filter((groupId) => groupId.length > 0)

	selectedGroupIds.value = clampToManageableScope(nextSelectedGroupIds)

	emit('update:modelValue', serializeApprovalGroups(selectedGroupIds.value))
}

onMounted(async () => {
	await searchGroup('')
})
</script>

<style scoped lang="scss">
.approval-groups-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__hint {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}
}
</style>
