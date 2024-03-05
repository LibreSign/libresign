<template>
	<NcSettingsSection :name="name">
		<p>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="makeValidationUrlPrivate"
				@update:checked="toggleSetting('make_validation_url_private', makeValidationUrlPrivate)">
				{{ t('libresign', 'Make validation URL acessible only by authenticated users') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="addFooter"
				@update:checked="toggleSetting('add_footer', addFooter)">
				{{ t('libresign', 'Add visible footer with signature details') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p v-if="addFooter">
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="writeQrcodeOnFooter"
				@update:checked="toggleSetting('write_qrcode_on_footer', writeQrcodeOnFooter)">
				{{ t('libresign', 'Write QR code on footer with validation URL') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p v-if="addFooter">
			{{ t('libresign', 'To validate signature of the documents. Only change this value if you want to replace the default validation URL by other.') }}
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
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
export default {
	name: 'Validation',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			name: t('libresign', 'Validation URL'),
			paternValidadeUrl: 'https://validador.librecode.coop/',
			makeValidationUrlPrivate: '0',
			url: null,
			addFooter: '0',
			writeQrcodeOnFooter: '0',
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
			this.getMakeValidationUrlPrivate()
			this.getAddFooterData()
			this.getWriteQrcodeOnFooter()
			this.getValidationUrlData()
		},
		async getMakeValidationUrlPrivate() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private'),
			)
			this.makeValidationUrlPrivate = response.data.ocs.data.data === '1'
		},
		async getAddFooterData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/add_footer'),
			)
			this.addFooter = response.data.ocs.data.data === '1'
		},
		async getWriteQrcodeOnFooter() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/write_qrcode_on_footer'),
			)
			this.writeQrcodeOnFooter = response.data.ocs.data.data === '1'
		},
		async getValidationUrlData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/validation_site'),
			)
			this.placeHolderValidationUrl(response.data.ocs.data.data)
		},
		saveValidationiUrl() {
			OCP.AppConfig.setValue('libresign', 'validation_site', this.$refs.urlInput.value.trim())
		},
		async toggleSetting(setting, value) {
			OCP.AppConfig.setValue('libresign', setting, value ? 1 : 0)
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
	width: 100%;
}

</style>
