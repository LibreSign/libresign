<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
			{{ t('libresign', 'To validate the signature of the documents. Only change this value if you want to replace the default validation URL with a different one.') }}
			<input id="validation_site"
				ref="urlInput"
				:placeholder="url"
				type="text"
				@input="saveValidationiUrl()"
				@click="fillValidationUrl()"
				@keypress.enter="validationUrlEnter()">
		</p>
		<p v-if="addFooter && isExtraSettingsEnabled">
			<NcTextArea v-model="footerTemplate"
				label="Footer template"
				placeholder="A twig template to be used at footer of PDF. Will be rendered by mPDF."
				@update:value="saveFooterTemplate" />
		</p>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

export default {
	name: 'Validation',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcTextArea,
	},
	data() {
		return {
			name: t('libresign', 'Validation URL'),
			paternValidadeUrl: 'https://validador.librecode.coop/',
			makeValidationUrlPrivate: false,
			url: null,
			addFooter: true,
			writeQrcodeOnFooter: true,
			isExtraSettingsEnabled: false,
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
			this.getMakeValidationUrlPrivate()
			this.getAddFooterData()
			this.getWriteQrcodeOnFooter()
			this.getValidationUrlData()
			this.getExtraSettingsEnabled()
		},
		async getExtraSettingsEnabled() {
			const isExtraSettingsEnabled = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/extra_settings'))
			this.isExtraSettingsEnabled = !!isExtraSettingsEnabled.data.ocs.data.data
			if (this.isExtraSettingsEnabled) {
				this.getFooterTemplate()
			}
		},
		async getMakeValidationUrlPrivate() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private'),
			)
			const value = response?.data?.ocs?.data.data
			this.makeValidationUrlPrivate = ['true', true, '1', 1].includes(value)
		},
		async getAddFooterData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/add_footer'),
			)
			const value = response?.data?.ocs?.data.data
			this.addFooter = ['true', true, '1', 1].includes(value)
		},
		async getWriteQrcodeOnFooter() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/write_qrcode_on_footer'),
			)
			const value = response?.data?.ocs?.data.data
			this.writeQrcodeOnFooter = ['true', true, '1', 1].includes(value)
		},
		async getValidationUrlData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/validation_site'),
			)
			this.placeHolderValidationUrl(response.data.ocs.data.data)
		},
		async getFooterTemplate() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/footer_template',
				))
			this.footerTemplate = response.data.ocs.data.data
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
		saveFooterTemplate() {
			OCP.AppConfig.setValue('libresign', 'footer_template', this.footerTemplate)
		},
	},
}
</script>
<style scoped>

input{
	width: 100%;
}

</style>
