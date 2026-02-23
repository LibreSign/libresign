<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Validation URL')">
		<p>
			<NcCheckboxRadioSwitch type="switch"
				v-model="makeValidationUrlPrivate"
				@update:model-value="onMakeValidationUrlPrivateChange">
				{{ t('libresign', 'Make validation URL acessible only by authenticated users') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p>
			<NcCheckboxRadioSwitch type="switch"
				v-model="addFooter"
				@update:model-value="onAddFooterChange">
				{{ t('libresign', 'Add visible footer with signature details') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p v-if="addFooter">
			<NcCheckboxRadioSwitch type="switch"
				v-model="writeQrcodeOnFooter"
				@update:model-value="onWriteQrcodeOnFooterChange">
				{{ t('libresign', 'Write QR code on footer with validation URL') }}
			</NcCheckboxRadioSwitch>
		</p>
		<p v-if="addFooter && writeQrcodeOnFooter">
			{{ t('libresign', 'To validate the signature of the documents. Only change this value if you want to replace the default validation URL with a different one.') }}
			<input id="validation_site"
				ref="urlInput"
				:placeholder="url"
				type="text"
				@input="saveValidationiUrl()"
				@click="fillValidationUrl()"
				@keypress.enter="validationUrlEnter()">
		</p>
		<p v-if="addFooter">
			<NcCheckboxRadioSwitch type="switch"
				v-model="customizeFooter"
				@update:model-value="onCustomizeFooterChange">
				{{ t('libresign', 'Customize footer template') }}
			</NcCheckboxRadioSwitch>
		</p>
		<FooterTemplateEditor v-if="addFooter && customizeFooter"
			:initial-is-default="isDefaultFooterTemplate"
			ref="footerTemplateEditor"
			@template-reset="onTemplateReset" />
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import FooterTemplateEditor from '../../components/FooterTemplateEditor.vue'

export default {
	name: 'Validation',
	components: {
		NcSettingsSection,
		FooterTemplateEditor,
	},
	data() {
		const isDefaultFooterTemplate = loadState('libresign', 'footer_template_is_default', true)
		return {
			paternValidadeUrl: 'https://validador.librecode.coop/',
			makeValidationUrlPrivate: false,
			url: null,
			addFooter: true,
			writeQrcodeOnFooter: true,
			isDefaultFooterTemplate,
			customizeFooter: !isDefaultFooterTemplate,
		}
	},
	created() {
		this.getData()
	},
	methods: {
		t,
		validationUrlEnter() {
			this.$refs.urlInput.blur()
		},
		async getData() {
			this.getMakeValidationUrlPrivate()
			this.getAddFooterData()
			this.getWriteQrcodeOnFooter()
			this.getValidationUrlData()
			this.getCustomizeFooterData()
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
		async getCustomizeFooterData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/footer_template_is_default'),
			)
			const value = response?.data?.ocs?.data.data
			this.isDefaultFooterTemplate = ['true', true, '1', 1].includes(value)
			this.customizeFooter = !this.isDefaultFooterTemplate
		},
		async onTemplateReset() {
			this.customizeFooter = false
		},
		saveValidationiUrl() {
			OCP.AppConfig.setValue('libresign', 'validation_site', this.$refs.urlInput.value.trim())
		},
		async toggleSetting(setting, value) {
			try {
				await OCP.AppConfig.setValue('libresign', setting, value ? '1' : '0')
			} catch (error) {
				console.error('Error toggling setting:', setting, error)
			}
		},
		async onMakeValidationUrlPrivateChange(value) {
			await this.toggleSetting('make_validation_url_private', value)
		},
		async onAddFooterChange(value) {
			await this.toggleSetting('add_footer', value)
		},
		async onWriteQrcodeOnFooterChange(value) {
			await this.toggleSetting('write_qrcode_on_footer', value)
		},
		async onCustomizeFooterChange(value) {
			await this.toggleSetting('footer_template_is_default', !value)
			this.isDefaultFooterTemplate = !value
			if (!value && this.$refs.footerTemplateEditor) {
				this.$refs.footerTemplateEditor.resetFooterTemplate()
			}
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
<style lang="scss" scoped>
input {
	width: 100%;
}
</style>
