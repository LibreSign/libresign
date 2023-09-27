<template>
	<NcSettingsSection :title="title" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="identificationDocumentsFlowEnabled"
			@update:checked="saveIdentificationDocumentsStatus()">
			{{ t('libresign', 'Enable identification documents flow') }}
		</NcCheckboxRadioSwitch>
		<p v-if="identificationDocumentsFlowEnabled">
			{{ t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and don\'t need to be defined.') }}
			<br>
			<NcMultiselect :key="idApprovalGroupsKey"
				v-model="approvalGroups"
				:options="groups"
				:close-on-select="false"
				:multiple="true"
				@input="saveApprovalGroups" />
		</p>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'IdentificationDocuments',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcMultiselect,
	},
	data() {
		return {
			title: t('libresign', 'Identification documents'),
			description: t('libresign', 'The flow of identification documents will make it mandatory for anyone who must sign a file, send identification documents to be approved by some member of the approval group. The user can only create the certificate after approval of the identification documents.'),
			identificationDocumentsFlowEnabled: false,
			approvalGroups: [],
			groups: [],
			idApprovalGroupsKey: 0,
		}
	},
	created() {
		this.getGroups()
		this.getData()
	},
	methods: {
		async getData() {
			const responseIdentificationDocuments = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/identification_documents', {})
			this.identificationDocumentsFlowEnabled = !!responseIdentificationDocuments.data.ocs.data.data

			const responseApprovalGroups = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/approval_group', {},
			)
			if (responseApprovalGroups.data.ocs.data.data !== '') {
				this.approvalGroups = JSON.parse(responseApprovalGroups.data.ocs.data.data)
			}
		},
		saveIdentificationDocumentsStatus() {
			OCP.AppConfig.setValue('libresign', 'identification_documents', this.identificationDocumentsFlowEnabled ? 1 : 0)
		},

		saveApprovalGroups() {
			const listOfInputGroupsSelected = JSON.stringify(this.approvalGroups)
			OCP.AppConfig.setValue('libresign', 'approval_group', listOfInputGroupsSelected)
			this.idApprovalGroupsKey += 1
		},
		async getGroups() {
			const response = await axios.get(generateOcsUrl('cloud/groups?', 3))
			this.groups = response.data.ocs.data.groups
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
