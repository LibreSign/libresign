<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="identificationDocumentsFlowEnabled"
			@update:checked="saveIdentificationDocumentsStatus()">
			{{ t('libresign', 'Enable identification documents flow') }}
		</NcCheckboxRadioSwitch>
		<p v-if="identificationDocumentsFlowEnabled">
			{{ t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and don\'t need to be defined.') }}
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

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'

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
			description: t('libresign', 'The flow of identification documents will make it mandatory for anyone who must sign a file, send identification documents to be approved by some member of the approval group. The user can only create the certificate after approval of the identification documents.'),
			identificationDocumentsFlowEnabled: false,
			approvalGroups: [],
			groups: [],
			loadingGroups: false,
			idApprovalGroupsKey: 0,
		}
	},
	created() {
		this.searchGroup('')
		this.getData()
	},
	methods: {
		async getData() {
			const responseIdentificationDocuments = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/identification_documents'))
			this.identificationDocumentsFlowEnabled = !!responseIdentificationDocuments.data.ocs.data.data

			const responseApprovalGroups = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/approval_group'),
			)
			if (responseApprovalGroups.data.ocs.data.data !== '') {
				this.approvalGroups = JSON.parse(responseApprovalGroups.data.ocs.data.data)
			}
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
			try {
				const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
					search: query,
					limit: 20,
					offset: 0,
				})
				this.groups = response.data.ocs.data.groups.sort(function(a, b) {
					return a.displayname.localeCompare(b.displayname)
				})
			} catch (err) {
				console.error('Could not fetch groups', err)
			} finally {
				this.loadingGroups = false
			}
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
