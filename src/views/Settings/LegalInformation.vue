<template>
	<SettingsSection :title="title" :description="description">
		<div class="legal-information-content">
			<Textarea v-model="legalInformation"
				:placeholder="t('libresign', 'Legal Information')"
				@input="saveLegalInformation" />
		</div>
	</SettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Textarea from '../../Components/Textarea/Textarea.vue'

export default {
	name: 'LegalInformation',
	components: {
		SettingsSection,
		Textarea,
	},
	data() {
		return {
			title: t('libresign', 'Legal information'),
			description: t('libresign', 'This information will appear on the validation page'),
			legalInformation: '',
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/legal_information', {})
			this.legalInformation = response.data.ocs.data.data
		},
		saveLegalInformation() {
			OCP.AppConfig.setValue('libresign', 'legal_information', this.legalInformation)
		},
	},
}
</script>
<style scoped>
.legal-information-content{
	display: flex;
	flex-direction: column;
}
textarea {
	width: 50%;
	height: 150px;
}
</style>
