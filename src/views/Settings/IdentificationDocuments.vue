<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Identification documents')"
		:description="t('libresign', 'The flow of identification documents will make it mandatory for anyone who must sign a file, to send their identification documents, this, in order for them to be approved by some member of the approval group. The user can only create the certificate after these are approved.')">
		<NcCheckboxRadioSwitch type="switch"
			v-model="identificationDocumentsFlowEnabled"
			@update:modelValue="saveIdentificationDocumentsStatus">
			{{ t('libresign', 'Enable identification documents flow') }}
		</NcCheckboxRadioSwitch>
		<p v-if="identificationDocumentsFlowEnabled">
			{{ t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and doesn\'t need to be defined.') }}
			<br>
			<NcSelect :key="idApprovalGroupsKey"
				v-model="approvalGroups"
				label="displayname"
				:no-wrap="false"
				:aria-label-combobox="description"
				:close-on-select="false"
				:disabled="loadingGroups"
				:loading="loadingGroups"
				:multiple="true"
				:options="groups"
				:searchable="true"
				:show-no-options="false"
				@search-change="searchGroup"
				@update:modelValue="saveApprovalGroups" />
		</p>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
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
const identificationDocumentsState = loadState('libresign', 'identification_documents', false) as boolean | string
const identificationDocumentsFlowEnabled = ref(identificationDocumentsState === true || identificationDocumentsState === '1')
const approvalGroupIds = ref<string[]>(Array.isArray(approvalGroupState) ? approvalGroupState : ['admin'])
const approvalGroups = ref<Array<Group | string>>([])
const groups = ref<Group[]>([])
const loadingGroups = ref(false)
const idApprovalGroupsKey = ref(0)

const description = computed(() => t('libresign', 'Search groups'))

function syncApprovalGroupsFromState() {
	approvalGroups.value = groups.value.filter((group) => approvalGroupIds.value.indexOf(group.id) !== -1)
}

function saveIdentificationDocumentsStatus() {
	OCP.AppConfig.setValue('libresign', 'identification_documents', identificationDocumentsFlowEnabled.value ? '1' : '0')
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
.identification-documents-content{
	display: flex;
	flex-direction: column;
}
</style>
