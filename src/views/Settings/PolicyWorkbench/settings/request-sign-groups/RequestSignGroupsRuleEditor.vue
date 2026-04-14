<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="request-sign-groups-editor">
		<p class="request-sign-groups-editor__hint">
			{{ t('libresign', 'Only members of the selected groups can request signatures. Users outside these groups will not see signing configuration options.') }}
		</p>

		<NcSelect
			:model-value="selectedGroups"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Select groups allowed to request signatures')"
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

defineOptions({
	name: 'RequestSignGroupsRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const selectedGroupIds = ref<string[]>(resolveRequestSignGroups(props.modelValue))
const availableGroups = ref<GroupRow[]>([])
const loadingGroups = ref(false)

const selectedGroups = computed<Array<GroupRow | string>>(() => {
	return selectedGroupIds.value.map((groupId) => {
		return availableGroups.value.find((group) => group.id === groupId) ?? groupId
	})
})

watch(() => props.modelValue, (nextValue) => {
	selectedGroupIds.value = resolveRequestSignGroups(nextValue)
})

async function searchGroup(query: string) {
	loadingGroups.value = true
	try {
		const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
			params: {
				search: query,
				limit: 40,
				offset: 0,
			},
		})

		availableGroups.value = (response.data?.ocs?.data?.groups ?? [])
			.map((group: GroupRow) => ({
				id: group.id,
				displayname: group.displayname || group.id,
			}))
			.sort((left: GroupRow, right: GroupRow) => left.displayname.localeCompare(right.displayname))
	} catch (error) {
		logger.debug('Could not search groups for request-sign policy editor', { error })
	} finally {
		loadingGroups.value = false
	}
}

function onGroupsChange(value: Array<GroupRow | string>) {
	selectedGroupIds.value = value
		.map((group): string => typeof group === 'string' ? group : group.id)
		.map((groupId) => groupId.trim())
		.filter((groupId) => groupId.length > 0)

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

	&__hint {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}
}
</style>
