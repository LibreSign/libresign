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
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import FooterTemplateEditor from '../../components/FooterTemplateEditor.vue'
import { usePoliciesStore } from '../../store/policies'
import {
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from './PolicyWorkbench/settings/signature-footer/model'

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

const paternValidadeUrl = ref('https://validador.librecode.coop/')
const makeValidationUrlPrivate = ref(false)
const url = ref<string | null>(null)
const addFooter = ref(true)
const writeQrcodeOnFooter = ref(true)
const isDefaultFooterTemplate = ref(true)
const customizeFooter = ref(false)
const policiesStore = usePoliciesStore()

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
	])
}

async function getMakeValidationUrlPrivate() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private')) as SettingsResponse
	makeValidationUrlPrivate.value = parseBooleanSetting(response.data?.ocs?.data?.data)
}

async function getAddFooterData() {
	await policiesStore.fetchEffectivePolicies()
	const effectiveValue = policiesStore.getEffectiveValue('add_footer')
	const config = normalizeSignatureFooterPolicyConfig(effectiveValue)
	addFooter.value = config.enabled
	writeQrcodeOnFooter.value = config.writeQrcodeOnFooter
	isDefaultFooterTemplate.value = !config.customizeFooterTemplate
	customizeFooter.value = config.customizeFooterTemplate
	placeHolderValidationUrl(config.validationSite)
}

async function getWriteQrcodeOnFooter() {
	await getAddFooterData()
}

async function getValidationUrlData() {
	await getAddFooterData()
}

async function getCustomizeFooterData() {
	await getAddFooterData()
}

function onTemplateReset() {
	customizeFooter.value = false
}

function saveValidationiUrl() {
	void saveFooterPolicy({
		validationSite: urlInput.value?.value.trim() || '',
	})
}

async function toggleSetting(setting: string, value: boolean) {
	try {
		if (setting === 'add_footer') {
			await saveFooterPolicy({ enabled: value })
			return
		}
		if (setting === 'write_qrcode_on_footer') {
			await saveFooterPolicy({ writeQrcodeOnFooter: value })
			return
		}
		if (setting === 'footer_template_is_default') {
			await saveFooterPolicy({ customizeFooterTemplate: !value })
			return
		}

		await getOcp().AppConfig.setValue('libresign', setting, value ? '1' : '0')
	} catch (error) {
		console.error('Error toggling setting:', setting, error)
	}
}

async function saveFooterPolicy(partial: {
	enabled?: boolean
	writeQrcodeOnFooter?: boolean
	validationSite?: string
	customizeFooterTemplate?: boolean
}) {
	const currentConfig = normalizeSignatureFooterPolicyConfig(policiesStore.getEffectiveValue('add_footer'))
	const nextConfig = {
		...currentConfig,
		...partial,
	}
	const serialized = serializeSignatureFooterPolicyConfig(nextConfig)
	const saved = await policiesStore.saveSystemPolicy('add_footer', serialized, false)
	if (!saved) {
		return
	}
	addFooter.value = nextConfig.enabled
	writeQrcodeOnFooter.value = nextConfig.writeQrcodeOnFooter
	customizeFooter.value = nextConfig.customizeFooterTemplate
	isDefaultFooterTemplate.value = !nextConfig.customizeFooterTemplate
	placeHolderValidationUrl(nextConfig.validationSite)
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
