<template>
	<NcSettingsSection :name="name">
		<p>
			<NcTextArea label="Footer template"
				placeholder="A twig template to be used at footer of PDF. Will be rendered by mPDF."
				:value.sync="footerTemplate"
				@update:value="saveFooterTemplate">
			</NcTextArea>
		</p>
	</NcSettingsSection>
</template>
<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
export default {
	name: 'ExtraSettings',
	components: {
		NcSettingsSection,
		NcTextArea,
	},
	data() {
		return {
			name: 'Extra settings',
			footerTemplate: '',
		}
	},
	created() {
		this.getData()
	},
	methods: {
		validationUrlEnter() {
			this.$refs.urlInput.blur()
		},
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/footer_template'))
			this.footerTemplate = response.data.ocs.data.data
		},
		saveFooterTemplate() {
			OCP.AppConfig.setValue('libresign', 'footer_template', this.footerTemplate)
		},
	},
}
</script>
