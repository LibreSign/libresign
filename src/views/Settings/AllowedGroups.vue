<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Signature request access')"
		:description="t('libresign', 'Choose which groups are authorized to create signature requests. Administrators may authorize only groups they belong to. The default admin group always has this permission.')"
	>
		<NcSelect :key="idKey"
			v-model="groupsSelected"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Choose groups authorized to create signature requests. Administrators may authorize only groups they belong to.')"
			:close-on-select="false"
			:disabled="loadingGroups"
			:loading="loadingGroups"
			:multiple="true"
			:options="groups"
			:searchable="true"
			:show-no-options="false"
			@search-change="searchGroup"
			@update:modelValue="saveGroups" />
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from '../../logger.js'
import { usePoliciesStore } from '../../store/policies'
import {
	DEFAULT_REQUEST_SIGN_GROUPS,
	resolveDeniedRequestSignGroups,
	resolveRequestSignGroups,
	serializeRequestSignGroups,
} from './PolicyWorkbench/settings/request-sign-groups/model'

import '@nextcloud/password-confirmation/style.css'

defineOptions({
	name: 'AllowedGroups',
})

type GroupRow = {
	id: string
	displayname: string
}

const groupsSelected = ref<Array<GroupRow | string>>([])
const groups = ref<GroupRow[]>([])
const loadingGroups = ref(false)
const idKey = ref(0)
const policiesStore = usePoliciesStore()

async function getData() {
	loadingGroups.value = true
	await policiesStore.fetchEffectivePolicies()
	const selected = resolveRequestSignGroups(policiesStore.getEffectiveValue('groups_request_sign') ?? DEFAULT_REQUEST_SIGN_GROUPS)
	groupsSelected.value = groups.value.filter(group => selected.includes(group.id))
	loadingGroups.value = false
}

async function saveGroups(value: Array<GroupRow | string>) {
	if (Array.isArray(value)) {
		groupsSelected.value = value
	}

	await confirmPassword()

	const groupIds = groupsSelected.value.map((g) => {
		if (typeof g === 'object') {
			return g.id
		}
		return g
	})
	const existingPolicyValue = policiesStore.getEffectiveValue('groups_request_sign')
	const denyGroupIds = resolveDeniedRequestSignGroups(existingPolicyValue)

	await policiesStore.saveSystemPolicy('groups_request_sign', serializeRequestSignGroups({
		allowGroups: groupIds,
		denyGroups: denyGroupIds,
	}), false)
	idKey.value += 1
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
			groups.value = data.ocs.data.groups.sort((a: GroupRow, b: GroupRow) => a.displayname.localeCompare(b.displayname))
		})
		.catch((error) => logger.debug('Could not search by groups', { error }))
	loadingGroups.value = false
}

onMounted(async () => {
	await searchGroup('')
	await getData()
})

defineExpose({
	groupsSelected,
	groups,
	loadingGroups,
	idKey,
	getData,
	saveGroups,
	searchGroup,
})
</script>
