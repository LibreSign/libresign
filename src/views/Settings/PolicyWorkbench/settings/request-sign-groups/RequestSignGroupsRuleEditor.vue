<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="request-sign-groups-editor">
		<template v-if="shouldShowRequesterGroupsEditor">
			<header class="request-sign-groups-editor__header">
				<h4 class="request-sign-groups-editor__title">
					<!-- TRANSLATORS Section title for the policy that delegates who may create signature requests. -->
					{{ t('libresign', 'Authorized requester groups') }}
				</h4>
				<p class="request-sign-groups-editor__description">
					<!-- TRANSLATORS Description explaining this scope controls which groups may create signature requests. -->
					{{ t('libresign', 'Choose which groups may create signature requests within this scope.') }}
				</p>
			</header>

			<NcSelect
				:model-value="selectedGroups"
				label="displayname"
				:no-wrap="false"
				:aria-label-combobox="authorizedRequesterGroupsAriaLabel"
				:close-on-select="false"
				:disabled="loadingGroups"
				:loading="loadingGroups"
				:multiple="true"
				:options="availableGroups"
				:placeholder="searchGroupsPlaceholder"
				:searchable="true"
				:show-no-options="false"
				@search-change="searchGroup"
				@update:modelValue="onGroupsChange" />

			<p class="request-sign-groups-editor__helper">
				<!-- TRANSLATORS Helper note: administrators can only authorize groups they are members of. -->
				{{ t('libresign', 'Only groups you belong to may be authorized.') }}
			</p>

			<p v-if="requiredManagedGroupId" class="request-sign-groups-editor__helper request-sign-groups-editor__helper--warning">
				<!-- TRANSLATORS Warning shown to delegated group admins while editing requester groups. -->
				{{ t('libresign', 'Your managed group must remain authorized in this rule.') }}
			</p>
		</template>

		<p v-else class="request-sign-groups-editor__setup-hint">
			<!-- TRANSLATORS Hint shown in group rule creation: choose scope groups first, then authorized requester groups. -->
			{{ t('libresign', 'Select scope groups first to define authorized requester groups.') }}
		</p>
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
import { resolveRequestSignGroups, serializeRequestSignGroups } from './model'

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
	name: 'RequestSignGroupsRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
	editorScope?: 'system' | 'group' | 'user'
	editorMode?: 'create' | 'edit' | null
	editorTargetIds?: string[]
	hasSelectedTargets?: boolean
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

selectedGroupIds.value = clampToManageableScope(resolveRequestSignGroups(props.modelValue))

const selectedGroups = computed<Array<GroupRow | string>>(() => {
	return selectedGroupIds.value.map((groupId) => {
		return availableGroups.value.find((group) => group.id === groupId) ?? groupId
	})
})

const shouldShowRequesterGroupsEditor = computed(() => {
	return !(
		props.editorScope === 'group'
		&& props.editorMode === 'create'
		&& props.hasSelectedTargets === false
	)
})

const requiredManagedGroupId = computed(() => {
	if (isInstanceAdmin) {
		return null
	}

	if (props.editorScope !== 'group' || props.editorMode !== 'edit') {
		return null
	}

	const targetIds = Array.isArray(props.editorTargetIds)
		? props.editorTargetIds.filter((targetId): targetId is string => typeof targetId === 'string' && targetId.trim().length > 0)
		: []

	if (targetIds.length !== 1) {
		return null
	}

	const targetGroupId = targetIds[0] as string
	if (!manageableGroupIds.has(targetGroupId)) {
		return null
	}

	return targetGroupId
})

// TRANSLATORS Accessible label for the group multi-select that defines who can create signature requests.
const authorizedRequesterGroupsAriaLabel = t('libresign', 'Authorized requester groups')

// TRANSLATORS Placeholder text shown while searching available groups in the requester authorization selector.
const searchGroupsPlaceholder = t('libresign', 'Search groups')

watch(() => props.modelValue, (nextValue) => {
	selectedGroupIds.value = clampToManageableScope(resolveRequestSignGroups(nextValue))
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
		logger.debug('Could not search groups for request-sign policy editor', { error })
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
	let nextSelectedGroupIds = value
		.map((group): string => typeof group === 'string' ? group : group.id)
		.map((groupId) => groupId.trim())
		.filter((groupId) => groupId.length > 0)

	if (requiredManagedGroupId.value && !nextSelectedGroupIds.includes(requiredManagedGroupId.value)) {
		nextSelectedGroupIds = [...nextSelectedGroupIds, requiredManagedGroupId.value]
	}

	selectedGroupIds.value = clampToManageableScope(nextSelectedGroupIds)

	emit('update:modelValue', serializeRequestSignGroups(selectedGroupIds.value))
}

onMounted(async () => {
	await searchGroup('')
})
</script>

<style scoped lang="scss">
.request-sign-groups-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__header {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	&__title {
		margin: 0;
		font-size: 0.95rem;
		font-weight: 700;
		line-height: 1.35;
	}

	&__description {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
		overflow-wrap: anywhere;
	}

	&__helper {
		margin: 0;
		font-size: 0.78rem;
		color: var(--color-text-maxcontrast);
		overflow-wrap: anywhere;

		&--warning {
			color: var(--color-warning-text);
			font-weight: 600;
		}
	}

	&__setup-hint {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
		overflow-wrap: anywhere;
	}

}
</style>
