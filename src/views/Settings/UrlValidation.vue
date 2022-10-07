<template>
	<SettingsSection :title="title" :description="description">
		<input id="validation_site"
			ref="urlInput"
			:placeholder="url"
			type="text"
			@input="saveUrl()"
			@click="fillUrlInput()"
			@keypress.enter="validationUrlEnter()">
	</SettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
export default {
	name: 'UrlValidation',
	components: {
		SettingsSection,
	},
	data() {
		return {
			url: null,
			paternValidadeUrl: 'https://validador.librecode.coop/',
			title: t('libresign', 'Validation URL'),
			description: t('libresign', 'To validate signature of the documents'),
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
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps' + '/' + 'libresign' + '/' + 'validation_site', {}
			)
			this.placeHolderUrl(response.data.ocs.data.data)
		},
		saveUrl() {
			OCP.AppConfig.setValue('libresign', 'validation_site', this.$refs.urlInput.value.trim())
		},
		placeHolderUrl(data) {
			if (data !== '') {
				this.url = data
			} else {
				this.url = this.paternValidadeUrl
			}
		},
		fillUrlInput() {
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
