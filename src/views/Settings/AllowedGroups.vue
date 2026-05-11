<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Allow request to sign')"
		:description="t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and don\'t need to be defined.')"
	>
		<NcSelect :key="idKey"
			v-model="groupsSelected"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and don\'t need to be defined.')"
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

function stringifyWithEscapedUnicode(value: string[]): string {
	return JSON.stringify(value).replace(/[^\x00-\x7F]/gu, (character) => {
		const codePoint = character.codePointAt(0)
		if (codePoint === undefined) {
			return character
		}

		if (codePoint <= 0xFFFF) {
			return `\\u${codePoint.toString(16).padStart(4, '0')}`
		}

		const surrogateOffset = codePoint - 0x10000
		const highSurrogate = 0xD800 + (surrogateOffset >> 10)
		const lowSurrogate = 0xDC00 + (surrogateOffset & 0x3FF)
		return `\\u${highSurrogate.toString(16).padStart(4, '0')}\\u${lowSurrogate.toString(16).padStart(4, '0')}`
	})
}

async function getData() {
	loadingGroups.value = true
	await axios.get(
		generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/groups_request_sign'),
	)
		.then(({ data }) => {
			const selected = JSON.parse(data.ocs.data.data)
			if (!Array.isArray(selected)) {
				groupsSelected.value = []
				return
			}
			groupsSelected.value = groups.value.filter(group => selected.indexOf(group.id) !== -1)
		})
		.catch((error) => logger.debug('Could not fetch groups_request_sign', { error }))
	loadingGroups.value = false
}

async function saveGroups(value: Array<GroupRow | string>) {
	if (Array.isArray(value)) {
		groupsSelected.value = value
	}

	await confirmPassword()

	const listOfInputGroupsSelected = stringifyWithEscapedUnicode(groupsSelected.value.map((g) => {
		if (typeof g === 'object') {
			return g.id
		}
		return g
	}))
	OCP.AppConfig.setValue('libresign', 'groups_request_sign', listOfInputGroupsSelected)
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
