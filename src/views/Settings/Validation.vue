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
		<div v-if="addFooter" class="footer-settings">
			<p>
				<NcCheckboxRadioSwitch type="switch"
					v-model="writeQrcodeOnFooter"
					@update:model-value="onWriteQrcodeOnFooterChange">
					{{ t('libresign', 'Write QR code on footer with validation URL') }}
				</NcCheckboxRadioSwitch>
			</p>
			<p v-if="writeQrcodeOnFooter">
				{{ t('libresign', 'To validate the signature of the documents. Only change this value if you want to replace the default validation URL with a different one.') }}
				<input id="validation_site"
					ref="urlInput"
					:placeholder="url"
					type="text"
					@input="saveValidationiUrl()"
					@click="fillValidationUrl()"
					@keypress.enter="validationUrlEnter()">
			</p>
			<p>
				<NcCheckboxRadioSwitch type="switch"
					v-model="customizeFooter"
					@update:model-value="onCustomizeFooterChange">
					{{ t('libresign', 'Customize footer template') }}
				</NcCheckboxRadioSwitch>
			</p>
			<FooterTemplateEditor v-if="customizeFooter"
				:initial-is-default="isDefaultFooterTemplate"
				ref="footerTemplateEditor"
				@template-reset="onTemplateReset" />
		</div>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import FooterTemplateEditor from '../../components/FooterTemplateEditor.vue'

type SettingsResponse = {
	data?: {
		ocs?: {
			data?: {
				data?: string | boolean | number
			}
		}
	}
}

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string) => Promise<void> | void
	}
}

type FooterTemplateEditorInstance = {
	resetTemplateToDefault: () => Promise<void> | void
}

defineOptions({
	name: 'Validation',
})

const isDefaultFooterTemplateState = loadState('libresign', 'footer_template_is_default', true)

const paternValidadeUrl = ref('https://validador.librecode.coop/')
const makeValidationUrlPrivate = ref(false)
const url = ref<string | null>(null)
const addFooter = ref(true)
const writeQrcodeOnFooter = ref(true)
const isDefaultFooterTemplate = ref(isDefaultFooterTemplateState)
const customizeFooter = ref(!isDefaultFooterTemplateState)

const urlInput = ref<HTMLInputElement | null>(null)
const footerTemplateEditor = ref<FooterTemplateEditorInstance | null>(null)

function parseBooleanSetting(value: string | boolean | number | undefined) {
	if (value === undefined) {
		return false
	}
	return ['true', true, '1', 1].includes(value)
}

function getOcp() {
	return (globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP
}

function validationUrlEnter() {
	urlInput.value?.blur()
}

async function getData() {
	await Promise.all([
		getMakeValidationUrlPrivate(),
		getAddFooterData(),
		getWriteQrcodeOnFooter(),
		getValidationUrlData(),
		getCustomizeFooterData(),
	])
}

async function getMakeValidationUrlPrivate() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private')) as SettingsResponse
	makeValidationUrlPrivate.value = parseBooleanSetting(response.data?.ocs?.data?.data)
}

async function getAddFooterData() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/add_footer')) as SettingsResponse
	addFooter.value = parseBooleanSetting(response.data?.ocs?.data?.data)
}

async function getWriteQrcodeOnFooter() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/write_qrcode_on_footer')) as SettingsResponse
	writeQrcodeOnFooter.value = parseBooleanSetting(response.data?.ocs?.data?.data)
}

async function getValidationUrlData() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/validation_site')) as SettingsResponse
	placeHolderValidationUrl(String(response.data?.ocs?.data?.data ?? ''))
}

async function getCustomizeFooterData() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/footer_template_is_default')) as SettingsResponse
	isDefaultFooterTemplate.value = parseBooleanSetting(response.data?.ocs?.data?.data)
	customizeFooter.value = !isDefaultFooterTemplate.value
}

function onTemplateReset() {
	customizeFooter.value = false
}

function saveValidationiUrl() {
	getOcp().AppConfig.setValue('libresign', 'validation_site', urlInput.value?.value.trim() || '')
}

async function toggleSetting(setting: string, value: boolean) {
	try {
		await getOcp().AppConfig.setValue('libresign', setting, value ? '1' : '0')
	} catch (error) {
		console.error('Error toggling setting:', setting, error)
	}
}

async function onMakeValidationUrlPrivateChange(value: boolean) {
	await toggleSetting('make_validation_url_private', value)
}

async function onAddFooterChange(value: boolean) {
	await toggleSetting('add_footer', value)
}

async function onWriteQrcodeOnFooterChange(value: boolean) {
	await toggleSetting('write_qrcode_on_footer', value)
}

async function onCustomizeFooterChange(value: boolean) {
	await toggleSetting('footer_template_is_default', !value)
	isDefaultFooterTemplate.value = !value
	if (!value) {
		await footerTemplateEditor.value?.resetTemplateToDefault()
	}
}

function placeHolderValidationUrl(data: string) {
	url.value = data !== '' ? data : paternValidadeUrl.value
}

function fillValidationUrl() {
	if (url.value !== paternValidadeUrl.value && urlInput.value && urlInput.value.value.length === 0) {
		urlInput.value.value = url.value || ''
	}
}

onMounted(() => {
	void getData()
})

defineExpose({
	t,
	paternValidadeUrl,
	makeValidationUrlPrivate,
	url,
	addFooter,
	writeQrcodeOnFooter,
	isDefaultFooterTemplate,
	customizeFooter,
	urlInput,
	footerTemplateEditor,
	parseBooleanSetting,
	validationUrlEnter,
	getData,
	getMakeValidationUrlPrivate,
	getAddFooterData,
	getWriteQrcodeOnFooter,
	getValidationUrlData,
	getCustomizeFooterData,
	onTemplateReset,
	saveValidationiUrl,
	toggleSetting,
	onMakeValidationUrlPrivateChange,
	onAddFooterChange,
	onWriteQrcodeOnFooterChange,
	onCustomizeFooterChange,
	placeHolderValidationUrl,
	fillValidationUrl,
})
</script>
<style lang="scss" scoped>
input {
	width: 100%;
}
</style>
