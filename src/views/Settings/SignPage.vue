<template>
	<NcSettingsSection :title="title">
		<p>
			<NcCheckboxRadioSwitch
				type="switch"
				:checked.sync="addFooter"
				@update:checked="saveAddFooter">
				{{ t('libresign', 'Add visible footer with signature details') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p v-if="addFooter">
			{{validationUrlDescription}}
			<input id="validation_site"
				ref="urlInput"
				:placeholder="url"
				type="text"
				@input="saveValidationiUrl()"
				@click="fillValidationUrl()"
				@keypress.enter="validationUrlEnter()">
		</p>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
export default {
	name: 'UrlValidation',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			title: t('libresign', 'Validation URL'),
			paternValidadeUrl: 'https://validador.librecode.coop/',
			url: null,
			validationUrlDescription: t('libresign', 'To validate signature of the documents'),
			addFooter: false,
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
			this.getAddFooterData()
			this.getValidationUrlData()
		},
		async getAddFooterData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps' + '/' + 'libresign' + '/' + 'add_footer', {}
			)
			this.addFooter = response.data.ocs.data.data ? true : false
		},
		async getValidationUrlData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps' + '/' + 'libresign' + '/' + 'validation_site', {}
			)
			this.placeHolderValidationUrl(response.data.ocs.data.data)
		},
		saveValidationiUrl() {
			OCP.AppConfig.setValue('libresign', 'validation_site', this.$refs.urlInput.value.trim())
		},
		async saveAddFooter() {
			OCP.AppConfig.setValue('libresign', 'add_footer', this.addFooter ? 1 : 0)
		},
		placeHolderValidationUrl(data) {
			if (data !== '') {
				this.url = data
			} else {
				this.url = this.paternValidadeUrl
			}
		},
		fillValidationUrl() {
			if (this.url !== this.paternValidadeUrl) {
				if (this.$refs.urlInput.value.length === 0) {
					this.$refs.urlInput.value = this.url
				}
			}
		},
	},
}
</script>
<style scoped>

input{
	width: 60%;
}

@media screen and (max-width: 500px){
	input{
		width: 100%;
	}
}
</style>
