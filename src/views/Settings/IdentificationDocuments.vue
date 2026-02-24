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
<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from '../../logger.js'

export default {
	name: 'IdentificationDocuments',
	components: {
		NcSettingsSection,
		NcSelect,
		NcCheckboxRadioSwitch,
	},
	computed: {
		description() {
			return t('libresign', 'Search groups')
		},
	},
	data() {
		const approvalGroupState = loadState('libresign', 'approval_group', ['admin'])
		return {
			identificationDocumentsFlowEnabled: loadState('libresign', 'identification_documents', false) === true,
			approvalGroupIds: Array.isArray(approvalGroupState) ? approvalGroupState : ['admin'],
			approvalGroups: [],
			groups: [],
			loadingGroups: false,
			idApprovalGroupsKey: 0,
		}
	},
	async created() {
		await this.searchGroup('')
		this.syncApprovalGroupsFromState()
	},
	methods: {
		t,
		syncApprovalGroupsFromState() {
			this.approvalGroups = this.groups.filter(group => {
				return this.approvalGroupIds.indexOf(group.id) !== -1
			})
		},
		saveIdentificationDocumentsStatus() {
			OCP.AppConfig.setValue('libresign', 'identification_documents', this.identificationDocumentsFlowEnabled ? '1' : '0')
		},

		saveApprovalGroups() {
			const listOfInputGroupsSelected = JSON.stringify(this.approvalGroups.map((g) => {
				if (typeof g === 'object') {
					return g.id
				}
				return g
			}))
			this.approvalGroupIds = JSON.parse(listOfInputGroupsSelected)
			OCP.AppConfig.setValue('libresign', 'approval_group', listOfInputGroupsSelected)
			this.idApprovalGroupsKey += 1
		},
		async searchGroup(query) {
			this.loadingGroups = true
			await axios.get(generateOcsUrl('cloud/groups/details'), {
				search: query,
				limit: 20,
				offset: 0,
			})
				.then(({ data }) => {
					this.groups = data.ocs.data.groups.sort(function(a, b) {
						return a.displayname.localeCompare(b.displayname)
					})
				})
				.catch((error) => logger.debug('Could not search by groups', { error }))
			this.loadingGroups = false
		},
	},
}
</script>
<style scoped>
.identification-documents-content{
	display: flex;
	flex-direction: column;
}
</style>
