<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="request-sign-groups-editor">
		<template v-if="shouldShowRequesterGroupsEditor">
			<template v-if="!hideAllowGroups">
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
					:model-value="selectedAllowGroups"
					label="displayname"
					:no-wrap="false"
					:aria-label-combobox="authorizedRequesterGroupsAriaLabel"
					:close-on-select="false"
					:disabled="loadingGroups"
					:loading="loadingGroups"
					:multiple="true"
					:options="availableAllowGroups"
					:placeholder="searchGroupsPlaceholder"
					:searchable="true"
					:show-no-options="false"
					@search-change="searchGroup"
					@update:modelValue="onAllowGroupsChange" />
			</template>

			<section :class="['request-sign-groups-editor__deny-section', { 'request-sign-groups-editor__deny-section--standalone': hideAllowGroups }]">
				<h4 class="request-sign-groups-editor__title">
					<!-- TRANSLATORS Section title for explicitly denied groups in signature-request access policy. -->
					{{ t('libresign', 'Denied requester groups') }}
				</h4>
				<p class="request-sign-groups-editor__description">
					<!-- TRANSLATORS Description for deny list in signature-request access policy. -->
					{{ t('libresign', 'Optionally block selected groups from creating signature requests in this scope.') }}
				</p>

				<NcSelect
					:model-value="selectedDenyGroups"
					label="displayname"
					:no-wrap="false"
					:aria-label-combobox="deniedRequesterGroupsAriaLabel"
					:close-on-select="false"
					:disabled="loadingGroups"
					:loading="loadingGroups"
					:multiple="true"
					:options="availableGroups"
					:placeholder="searchGroupsPlaceholder"
					:searchable="true"
					:show-no-options="false"
					@search-change="searchGroup"
					@update:modelValue="onDenyGroupsChange" />

				<p v-if="!hideAllowGroups && overlappingGroupIds.length > 0" class="request-sign-groups-editor__helper request-sign-groups-editor__helper--warning">
					<!-- TRANSLATORS Warning shown when a group appears in both allow and deny lists; deny takes precedence. -->
					{{ t('libresign', 'One or more groups are present in both allow and deny lists. Deny takes precedence.') }}
				</p>
			</section>

			<p v-if="!hideAllowGroups" class="request-sign-groups-editor__helper">
				<!-- TRANSLATORS Helper note: administrators can only authorize groups they are members of. -->
				{{ t('libresign', 'Only groups you belong to may be configured in allow or deny lists.') }}
			</p>

			<!-- The warning is only meaningful when the Authorized section is visible;
			     when hideAllowGroups is true the managed group is preserved automatically. -->
			<p v-if="requiredManagedGroupId && !hideAllowGroups" class="request-sign-groups-editor__helper request-sign-groups-editor__helper--warning">
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
import {
	resolveDeniedRequestSignGroups,
	resolveRequestSignGroups,
	serializeRequestSignGroups,
} from './model'

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
	editorInitialTargetIds?: string[]
	editorTargetIds?: string[]
	hasSelectedTargets?: boolean
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const selectedAllowGroupIds = ref<string[]>([])
const selectedDenyGroupIds = ref<string[]>([])
const availableGroups = ref<GroupRow[]>([])
const loadingGroups = ref(false)
const currentUser = getCurrentUser()
const isInstanceAdmin = currentUser?.isAdmin === true
const isDelegatedGroupCreateFlow = computed(() => {
	return !isInstanceAdmin && props.editorScope === 'group' && props.editorMode === 'create'
})
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

function normalizeTargetIds(targetIds?: string[]): string[] {
	return Array.isArray(targetIds)
		? targetIds.filter((id): id is string => typeof id === 'string' && id.trim().length > 0)
		: []
}

function resolveCurrentScopeTargetIds(): string[] {
	const initialTargetIds = normalizeTargetIds(props.editorInitialTargetIds)
	if (initialTargetIds.length > 0) {
		return initialTargetIds
	}

	const draftTargetIds = normalizeTargetIds(props.editorTargetIds)
	if (draftTargetIds.length > 0) {
		return draftTargetIds
	}

	if (props.hasSelectedTargets !== true) {
		return []
	}

	return Array.from(new Set([
		...resolveRequestSignGroups(props.modelValue),
		...resolveDeniedRequestSignGroups(props.modelValue),
	]))
}

const lockedInheritedAllowGroupIds = new Set(
	isDelegatedGroupCreateFlow.value
		? clampToManageableScope(resolveRequestSignGroups(props.modelValue))
		: [],
)

function stripLockedInheritedAllowGroups(groupIds: string[]): string[] {
	if (!isDelegatedGroupCreateFlow.value || lockedInheritedAllowGroupIds.size === 0) {
		return groupIds
	}

	return groupIds.filter((groupId) => !lockedInheritedAllowGroupIds.has(groupId))
}

selectedAllowGroupIds.value = clampToManageableScope(stripLockedInheritedAllowGroups(resolveRequestSignGroups(props.modelValue)))
selectedDenyGroupIds.value = clampToManageableScope(resolveDeniedRequestSignGroups(props.modelValue))

const selectedAllowGroups = computed<Array<GroupRow | string>>(() => {
	return selectedAllowGroupIds.value.map((groupId) => {
		return availableGroups.value.find((group) => group.id === groupId) ?? groupId
	})
})

const selectedDenyGroups = computed<Array<GroupRow | string>>(() => {
	return selectedDenyGroupIds.value.map((groupId) => {
		return availableGroups.value.find((group) => group.id === groupId) ?? groupId
	})
})

const availableAllowGroups = computed(() => {
	if (lockedInheritedAllowGroupIds.size === 0) {
		return availableGroups.value
	}

	return availableGroups.value.filter((group) => !lockedInheritedAllowGroupIds.has(group.id))
})

const overlappingGroupIds = computed(() => {
	return selectedAllowGroupIds.value.filter((groupId) => selectedDenyGroupIds.value.includes(groupId))
})

const shouldShowRequesterGroupsEditor = computed(() => {
	return !(
		props.editorScope === 'group'
		&& props.editorMode === 'create'
		&& props.hasSelectedTargets === false
	)
})

/**
 * True when the current user is a group admin creating/editing a rule exclusively
 * for groups they already manage.
 *
 * In that situation the Authorized section is controlled by the system administrator
 * (via `allowChildOverride`) and the group admin should only be able to refine
 * the deny list. Showing the Authorized selector would be confusing and redundant.
 *
 * Prefer original editor targets when available. During create flows where
 * scope targets are derived from the current allowGroups payload, initial targets
 * can be empty; in that case fall back to current draft target ids.
 */
const hideAllowGroups = computed(() => {
	if (props.editorMode === 'create') {
		return false
	}

	if (isInstanceAdmin) {
		return false
	}

	if (props.editorScope !== 'group') {
		return false
	}

	const targetIds = resolveCurrentScopeTargetIds()

	if (targetIds.length === 0) {
		return false
	}

	// Preferred signal: explicit manageable groups configured for delegated admins.
	if (manageableGroupIds.size > 0) {
		return targetIds.every((id) => manageableGroupIds.has(id))
	}

	// Fallback signal: when manageable groups are not exposed by bootstrap config,
	// consider delegated mode if all scope targets are already authorized by the
	// inherited rule payload currently being edited.
	const authorizedGroups = new Set(resolveRequestSignGroups(props.modelValue))
	if (authorizedGroups.size === 0) {
		return false
	}

	return targetIds.every((id) => authorizedGroups.has(id))
})

const requiredManagedGroupId = computed(() => {
	if (isInstanceAdmin) {
		return null
	}

	if (props.editorScope !== 'group' || props.editorMode !== 'edit') {
		return null
	}

	const targetIds = normalizeTargetIds(props.editorInitialTargetIds)

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

// TRANSLATORS Accessible label for the group multi-select that defines explicitly denied signature-request groups.
const deniedRequesterGroupsAriaLabel = t('libresign', 'Denied requester groups')

// TRANSLATORS Placeholder text shown while searching available groups in the requester authorization selector.
const searchGroupsPlaceholder = t('libresign', 'Search groups')

watch(() => props.modelValue, (nextValue) => {
	selectedAllowGroupIds.value = clampToManageableScope(stripLockedInheritedAllowGroups(resolveRequestSignGroups(nextValue)))
	selectedDenyGroupIds.value = clampToManageableScope(resolveDeniedRequestSignGroups(nextValue))
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

function toGroupIds(value: Array<GroupRow | string>): string[] {
	return value
		.map((group): string => typeof group === 'string' ? group : group.id)
		.map((groupId) => groupId.trim())
		.filter((groupId) => groupId.length > 0)
}

function emitValue() {
	// When Authorized is hidden the group admin must not overwrite the inherited
	// allowGroups that come from the parent (system/group) rule.
	const effectiveAllowGroups = hideAllowGroups.value
		? resolveRequestSignGroups(props.modelValue)
		: selectedAllowGroupIds.value

	emit('update:modelValue', serializeRequestSignGroups({
		allowGroups: effectiveAllowGroups,
		denyGroups: selectedDenyGroupIds.value,
	}))
}

function onAllowGroupsChange(value: Array<GroupRow | string>) {
	let nextSelectedGroupIds = value
		? toGroupIds(value)
		: []

	nextSelectedGroupIds = stripLockedInheritedAllowGroups(nextSelectedGroupIds)

	if (requiredManagedGroupId.value && !nextSelectedGroupIds.includes(requiredManagedGroupId.value)) {
		nextSelectedGroupIds = [...nextSelectedGroupIds, requiredManagedGroupId.value]
	}

	selectedAllowGroupIds.value = clampToManageableScope(nextSelectedGroupIds)
	emitValue()
}

function onDenyGroupsChange(value: Array<GroupRow | string>) {
	selectedDenyGroupIds.value = clampToManageableScope(toGroupIds(value))
	emitValue()
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

	&__deny-section {
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
		margin-top: 0.5rem;
		padding-top: 0.75rem;
		border-top: 1px solid var(--color-border);

		// When Authorized is hidden the section needs no visual separator.
		&--standalone {
			margin-top: 0;
			padding-top: 0;
			border-top: none;
		}
	}

}
</style>
