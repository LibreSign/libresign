<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="identificationDocumentsFlowEnabled"
			@update:checked="saveIdentificationDocumentsStatus()">
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
				@input="saveApprovalGroups" />
		</p>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from '../../logger.js'

export default {
	name: 'IdentificationDocuments',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcSelect,
	},
	data() {
		return {
			name: t('libresign', 'Identification documents'),
			description: t('libresign', 'The flow of identification documents will make it mandatory for anyone who must sign a file, to send their identification documents, this, in order for them to be approved by some member of the approval group. The user can only create the certificate after these are approved.'),
			identificationDocumentsFlowEnabled: false,
			approvalGroups: [],
			groups: [],
			loadingGroups: false,
			idApprovalGroupsKey: 0,
		}
	},
	async created() {
		await this.searchGroup('')
		await this.getData()
	},
	methods: {
		async getData() {
			await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/identification_documents'))
				.then(({ data }) => {
					this.identificationDocumentsFlowEnabled = ['true', true, '1', 1].includes(data.ocs.data.data)
				})

			await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/approval_group'),
			)
				.then(({ data }) => {
					const approvalGroups = JSON.parse(data.ocs.data.data)
					if (!approvalGroups) {
						return
					}
					this.approvalGroups = this.groups.filter(group => {
						return approvalGroups.indexOf(group.id) !== -1
					})
				})
				.catch((error) => logger.debug('Could not fetch groups_request_sign', { error }))
		},
		saveIdentificationDocumentsStatus() {
			OCP.AppConfig.setValue('libresign', 'identification_documents', this.identificationDocumentsFlowEnabled ? 1 : 0)
		},

		saveApprovalGroups() {
			const listOfInputGroupsSelected = JSON.stringify(this.approvalGroups.map((g) => {
				if (typeof g === 'object') {
					return g.id
				}
				return g
			}))
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
