<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Signature stamp')"
		:description="t('libresign', 'Configure the content displayed with the signature. The text template uses Twig syntax: https://twig.symfony.com/')">
		<fieldset class="settings-section__row">
			<legend>{{ t('libresign', 'Display signature mode') }}</legend>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="DESCRIPTION_ONLY"
				name="render_mode"
				type="radio"
				:aria-label="t('libresign', 'Description only')"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Description only') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="GRAPHIC_AND_DESCRIPTION"
				name="render_mode"
				type="radio"
				:aria-label="t('libresign', 'Signature and description')"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Signature and description') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="SIGNAME_AND_DESCRIPTION"
				name="render_mode"
				type="radio"
				:aria-label="t('libresign', 'Signer name and description')"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Signer name and description') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="GRAPHIC_ONLY"
				name="render_mode"
				type="radio"
				:aria-label="t('libresign', 'Signature only')"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Signature only') }}
			</NcCheckboxRadioSwitch>
			<NcButton v-if="displayResetRenderMode"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="resetRenderMode">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>
		</fieldset>
		<div v-if="renderMode !== 'GRAPHIC_ONLY'">
			<div class="settings-section__row">
				<NcButton variant="tertiary"
					:aria-label="t('libresign', 'Show available variables')"
					@click="showVariablesDialog = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" />
					</template>
					{{ t('libresign', 'Available variables') }}
				</NcButton>
			</div>
			<div class="settings-section__row">
				<CodeEditor
					v-model="inputValue"
					:label="t('libresign', 'Signature text template')"
					:placeholder="t('libresign', 'Signature text template')" />
				<NcButton v-if="displayResetTemplate"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetTemplate">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="settings-section__row">
				<div v-if="renderMode === 'SIGNAME_AND_DESCRIPTION'" class="settings-section__row_signature">
					<NcTextField v-model="signatureFontSize"
						:label="t('libresign', 'Signature font size')"
						:placeholder="t('libresign', 'Signature font size')"
						type="number"
						:min="0.1"
						:max="30"
						:step="0.01"
						:spellcheck="false"
						:success="dislaySuccessTemplate"
						@keydown.enter="saveTemplate"
						@blur="saveTemplate" />
					<NcButton v-if="dislayResetSignatureFontSize"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetSignatureFontSize">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
				<div :class="{
					'settings-section__row_template': renderMode === 'SIGNAME_AND_DESCRIPTION',
					'settings-section__row_template-only': renderMode !== 'SIGNAME_AND_DESCRIPTION',
				}">
					<NcTextField v-model="templateFontSize"
						:label="t('libresign', 'Template font size')"
						:placeholder="t('libresign', 'Template font size')"
						type="number"
						:min="0.1"
						:max="30"
						:step="0.01"
						:spellcheck="false"
						:success="dislaySuccessTemplate"
						@keydown.enter="saveTemplate"
						@blur="saveTemplate" />
					<NcButton v-if="displayResetTemplateFontSize"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetTemplateFontSize">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
		<div v-for="(error, key) in errorMessageTemplate"
			:key="key"
			class="settings-section__row">
			<NcNoteCard type="error"
				:show-alert="true">
				<p>{{ error }}</p>
			</NcNoteCard>
		</div>
		<div v-if="displayPreview" class="settings-section__row">
			<div class="settings-section__row_dimension">
				<NcTextField v-model="signatureWidth"
					:label="t('libresign', 'Default signature width')"
					:placeholder="t('libresign', 'Default signature width')"
					type="number"
					:min="1"
					:max="800"
					:step="0.01"
					:spellcheck="false"
					:success="dislaySuccessTemplate"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate" />
				<NcButton v-if="displayResetSignatureWidth"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetSignatureWidth">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="settings-section__row_dimension">
				<NcTextField v-model="signatureHeight"
					:label="t('libresign', 'Default signature height')"
					:placeholder="t('libresign', 'Default signature height')"
					type="number"
					:min="1"
					:max="800"
					:step="0.01"
					:spellcheck="false"
					:success="dislaySuccessTemplate"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate" />
				<NcButton v-if="displayResetSignatureHeight"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetSignatureHeight">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<fieldset class="settings-section__row settings-section__row_bar">
			<legend>{{ t('libresign', 'Background image') }}</legend>
			<NcButton id="signature-background"
				variant="secondary"
				:aria-label="t('libresign', 'Upload new background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUpload" :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton v-if="displayResetBackground"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="undoBackground">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="displayRemoveBackground"
				variant="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="removeBackground">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>
			<NcLoadingIcon v-if="showLoadingBackground"
				class="settings-section__loading-icon"
				:size="20" />
			<input ref="input"
				:accept="acceptMime"
				type="file"
				@change="onChangeBackground">
			<div v-if="displayPreview" class="settings-section__zoom">
				<NcButton :aria-label="t('libresign', 'Decrease zoom level')"
					@click="changeZoomLevel(-10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyMinusOutline" :size="20" />
					</template>
				</NcButton>
				<NcButton :aria-label="t('libresign', 'Increase zoom level')"
					@click="changeZoomLevel(+10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyPlusOutline" :size="20" />
					</template>
				</NcButton>
			</div>
			<NcTextField v-if="displayPreview"
				v-model="zoomLevel"
				class="settings-section__zoom_level"
				:label="t('libresign', 'Zoom level')"
				type="number"
				:min="10"
				:step="10"
				:spellcheck="false"
				@keydown.enter="saveZoomLevel"
				@blur="saveZoomLevel" />
		</fieldset>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessageBackground"
				type="error"
				:show-alert="true">
				<p>{{ errorMessageBackground }}</p>
			</NcNoteCard>
		</div>
		<div class="settings-section__row_preview">
			<div v-if="displayPreview && !previewLoaded"
				class="settings-section__preview"
				:style="{
					width: ((signatureWidth * zoomLevel) / 100) + 'px',
					height: ((signatureHeight * zoomLevel) / 100) + 'px',
				}">
				<NcLoadingIcon class="settings-section__preview__loading" :size="20" />
			</div>
			<div v-if="displayPreview"
				class="settings-section__preview"
				:style="{
					width: ((signatureWidth * zoomLevel) / 100) + 'px',
					height: ((signatureHeight * zoomLevel) / 100) + 'px',
					'background-image': 'url(' + backgroundUrl + ')',
					'border-color': isOverflowing ? 'var(--color-error) !important': '',
					visibility: previewLoaded ? 'visible' : 'hidden',
					position: previewLoaded ? '' : 'absolute',
				}">
				<div class="left-column" :style="{display: renderMode === 'DESCRIPTION_ONLY' ? 'none' : ''}">
					<div class="left-column-content"
						:style="{
							'border': renderMode === 'SIGNAME_AND_DESCRIPTION' ? 'unset' : '',
							width: ((previewSignatureImageWidth * zoomLevel) / 100) + 'px',
							height: ((previewSignatureImageHeight * zoomLevel) / 100) + 'px',
						}">
						<img :src="signatureImageUrl"
							:width="((previewSignatureImageWidth * zoomLevel) / 100) + 'px'"
							:height="((previewSignatureImageHeight * zoomLevel) / 100) + 'px'"
							@load="isSignatureImageLoaded = true">
					</div>
				</div>
				<!-- eslint-disable vue/no-v-html -->
				<div ref="rightColumn"
					class="right-column"
					:style="{
						'font-size': ((templateFontSize * 1.1 * zoomLevel) / 100) + 'px',
						display: renderMode === 'GRAPHIC_ONLY' ? 'none' : '',
						margin: (0.019 * zoomLevel) + 'px',
					}"
					@resize="checkPreviewOverflow"
					v-html="parsedWithLineBreak" />
				<!-- eslint-enable vue/no-v-html -->
			</div>
			<NcNoteCard v-else
				type="info"
				:show-alert="true">
				<p>{{ t('libresign', 'If no background image or signature template is provided, no visible signature will be added to the document.') }}</p>
			</NcNoteCard>
		</div>

		<NcDialog :name="t('libresign', 'Available template variables')"
			v-model:open="showVariablesDialog"
			size="normal">
			<div class="variables-dialog">
				<p class="variables-dialog__description">
					{{ t('libresign', 'Click on a variable to copy it to clipboard') }}
				</p>
				<div class="variables-list">
					<NcFormBoxButton v-for="(availableDescription, availableName) in availableVariables"
						:key="availableName"
						inverted-accent
						@click="copyToClipboard(getVariableText(availableName))">
						<template #default>
							<span class="hidden-visually">
								{{ t('libresign', 'Copy to clipboard') }}
							</span>
							{{ getVariableText(availableName) }}
						</template>
						<template #icon>
							<NcIconSvgWrapper v-if="isCopied(availableName)" :path="mdiCheck" :size="20" />
							<NcIconSvgWrapper v-else :path="mdiContentCopy" :size="20" />
						</template>
						<template #description>
							<p class="variable-description">{{ availableDescription }}</p>
						</template>
					</NcFormBoxButton>
				</div>
			</div>
		</NcDialog>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import debounce from 'debounce'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import {
	mdiCheck,
	mdiContentCopy,
	mdiDelete,
	mdiHelpCircleOutline,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
	mdiUndoVariant,
	mdiUpload,
} from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { isRTL, t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'

import CodeEditor from '../../components/CodeEditor.vue'
import { useSignatureTextPolicy, type SignatureTextUiDefaults } from './PolicyWorkbench/settings/signature-text/useSignatureTextPolicy'

defineOptions({
	name: 'SignatureStamp',
})

type RenderMode = 'DESCRIPTION_ONLY' | 'GRAPHIC_AND_DESCRIPTION' | 'SIGNAME_AND_DESCRIPTION' | 'GRAPHIC_ONLY'

const isDarkTheme = useIsDarkTheme()
const { values: signatureTextValues } = useSignatureTextPolicy()
const SIGNATURE_TEXT_DEFAULTS: SignatureTextUiDefaults = {
	template: signatureTextValues.value.template,
	templateFontSize: signatureTextValues.value.templateFontSize,
	signatureFontSize: signatureTextValues.value.signatureFontSize,
	signatureWidth: signatureTextValues.value.signatureWidth,
	signatureHeight: signatureTextValues.value.signatureHeight,
	renderMode: signatureTextValues.value.renderMode === 'default'
		? 'GRAPHIC_AND_DESCRIPTION'
		: signatureTextValues.value.renderMode,
}

const initialBackgroundType = loadState<string>('libresign', 'signature_background_type', '')

const input = ref<HTMLInputElement | null>(null)
const rightColumn = ref<HTMLElement | null>(null)
const textareaEditor = ref<any>(null)

const showLoadingBackground = ref(false)
const backgroundType = ref(initialBackgroundType)
const acceptMime = ['image/png']
const mimeName = ref<string | undefined>(undefined)
const errorMessageBackground = ref('')
const backgroundUrl = ref(backgroundType.value !== 'deleted'
	? generateOcsUrl('/apps/libresign/api/v1/admin/signature-background')
	: '')
const signatureTextTemplate = ref(signatureTextValues.value.template)
const signatureWidth = ref<number>(signatureTextValues.value.signatureWidth)
const signatureHeight = ref<number>(signatureTextValues.value.signatureHeight)
const signatureFontSize = ref<number>(signatureTextValues.value.signatureFontSize)
const templateFontSize = ref<number>(signatureTextValues.value.templateFontSize)
const isSignatureImageLoaded = ref(false)
const templateSaved = ref(true)
const zoomLevel = ref<number>(loadState<number>('libresign', 'signature_preview_zoom_level', 100))
const renderMode = ref<RenderMode>(
	(signatureTextValues.value.renderMode === 'default'
		? SIGNATURE_TEXT_DEFAULTS.renderMode
		: signatureTextValues.value.renderMode) as RenderMode,
)
const dislaySuccessTemplate = ref(false)
const errorMessageTemplate = ref<string[]>(signatureTextValues.value.templateError ? [signatureTextValues.value.templateError] : [])
const parsed = ref(signatureTextValues.value.parsed)
const isRTLDirection = isRTL()
const availableVariables = ref<Record<string, string>>(loadState<Record<string, string>>('libresign', 'signature_available_variables', {}))
const isOverflowing = ref(false)
const showVariablesDialog = ref(false)
const copiedVariable = ref<string | null>(null)

const displayResetBackground = computed(() => backgroundType.value === 'custom' || backgroundType.value === 'deleted')
const displayRemoveBackground = computed(() => backgroundType.value === 'custom' || backgroundType.value === 'default')
const displayPreview = computed(() => {
	if (backgroundType.value !== 'deleted') {
		return true
	}
	if (renderMode.value === 'DESCRIPTION_ONLY' && !parsed.value) {
		return false
	}
	return true
})
const inputValue = computed<string>({
	get: () => signatureTextTemplate.value,
	set: (value: string) => {
		signatureTextTemplate.value = value
		debouncePropertyChange()
	},
})
const displayResetRenderMode = computed(() => renderMode.value !== 'GRAPHIC_AND_DESCRIPTION')
const displayResetTemplate = computed(() => signatureTextTemplate.value !== SIGNATURE_TEXT_DEFAULTS.template)
const displayResetTemplateFontSize = computed(() => templateFontSize.value !== SIGNATURE_TEXT_DEFAULTS.templateFontSize)
const dislayResetSignatureFontSize = computed(() => signatureFontSize.value !== SIGNATURE_TEXT_DEFAULTS.signatureFontSize)
const displayResetSignatureWidth = computed(() => signatureWidth.value !== SIGNATURE_TEXT_DEFAULTS.signatureWidth)
const displayResetSignatureHeight = computed(() => signatureHeight.value !== SIGNATURE_TEXT_DEFAULTS.signatureHeight)
const parsedWithLineBreak = computed(() => String(parsed.value ?? '').replace(/\n/g, '<br>'))
const previewSignatureImageWidth = computed(() => (renderMode.value === 'GRAPHIC_ONLY' || !parsedWithLineBreak.value)
	? signatureWidth.value
: Math.floor(signatureWidth.value / 2))
const previewSignatureImageHeight = computed(() => signatureHeight.value)
const signatureImageUrl = computed(() => {
	const text = renderMode.value === 'SIGNAME_AND_DESCRIPTION'
		? getCurrentUser()?.displayName ?? 'John Doe'
		: t('libresign', 'Signature image here')
	const align = renderMode.value === 'GRAPHIC_AND_DESCRIPTION' ? 'right' : 'center'
	const darkTheme = isDarkTheme ? 1 : 0

	return generateOcsUrl('/apps/libresign/api/v1/admin/signer-name')
		+ `?width=${previewSignatureImageWidth.value}`
		+ `&height=${previewSignatureImageHeight.value}`
		+ `&text=${encodeURIComponent(text)}`
		+ `&fontSize=${signatureFontSize.value}`
		+ `&isDarkTheme=${darkTheme}`
		+ `&align=${align}`
})
const previewLoaded = computed(() => isSignatureImageLoaded.value && !showLoadingBackground.value && templateSaved.value)

function getVariableText(name: string) {
	return name
}

function isCopied(name: string) {
	return copiedVariable.value === getVariableText(name)
}

function copyToClipboard(text: string) {
	if (copiedVariable.value === text) {
		return
	}

	const value = text
	try {
		navigator.clipboard.writeText(value)
	} catch {
		prompt('', value)
	}

	copiedVariable.value = text
	setTimeout(() => {
		copiedVariable.value = null
	}, 2000)
}

function reset() {
	dislaySuccessTemplate.value = false
	errorMessageBackground.value = ''
	errorMessageTemplate.value = []
}

async function refreshAfterChangeCollectMetadata() {
	await axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/signature-settings'))
		.then(({ data }) => {
			availableVariables.value = data.ocs.data.signature_available_variables
		})
}

const handleCollectMetadataChanged = () => {
	void refreshAfterChangeCollectMetadata()
}

function activateLocalFilePicker() {
	reset()
	if (!input.value) {
		return
	}
	input.value.value = ''
	input.value.click()
}

function changeZoomLevel(zoom: number) {
	zoomLevel.value = Number(zoomLevel.value) + zoom
	saveZoomLevel()
}

async function saveZoomLevel() {
	OCP.AppConfig.setValue('libresign', 'signature_preview_zoom_level', zoomLevel.value)
}

async function onChangeBackground(event: Event) {
	const file = (event.target as HTMLInputElement)?.files?.[0]
	if (!file) {
		return
	}

	const formData = new FormData()
	formData.append('image', file)

	showLoadingBackground.value = true
	await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
		.then(() => {
			showLoadingBackground.value = false
			backgroundType.value = 'custom'
			backgroundUrl.value = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
		})
		.catch(({ response }) => {
			showLoadingBackground.value = false
			errorMessageBackground.value = response.data.ocs.data?.message
		})
}

async function undoBackground() {
	reset()
	showLoadingBackground.value = true
	await axios.patch(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
		setting: mimeName.value,
	})
		.then(() => {
			showLoadingBackground.value = false
			backgroundType.value = 'default'
			backgroundUrl.value = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
		})
		.catch(({ response }) => {
			showLoadingBackground.value = false
			errorMessageBackground.value = response.data.ocs.data?.message
		})
}

async function removeBackground() {
	reset()
	await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
		data: {
			setting: mimeName.value,
			value: 'backgroundColor',
		},
	})
		.then(() => {
			backgroundType.value = 'deleted'
			backgroundUrl.value = ''
		})
		.catch(({ response }) => {
			errorMessageBackground.value = response.data.ocs.data?.message
		})
}

function checkPreviewOverflow() {
	if (!rightColumn.value) {
		return
	}

	isOverflowing.value = rightColumn.value.scrollHeight > rightColumn.value.clientHeight
	const overflowMessage = t('libresign', 'Signature template content is overflowing. Reduce the text.')
	if (isOverflowing.value && !errorMessageTemplate.value.includes(overflowMessage)) {
		errorMessageTemplate.value.push(overflowMessage)
	}
}

const resizeHeight = debounce(() => {
	const wrapper = textareaEditor.value
	if (!wrapper) {
		return
	}

	const mainWrapper = wrapper.$el.querySelector('.textarea__main-wrapper')
	const textarea = wrapper.$el.querySelector('textarea')

	if (mainWrapper && textarea) {
		mainWrapper.style.height = 'auto'
		mainWrapper.style.height = `${textarea.scrollHeight + 4}px`
	}

	checkPreviewOverflow()
}, 100)

async function saveTemplate() {
	reset()
	templateSaved.value = false
	resizeHeight()
	await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), {
		template: signatureTextTemplate.value,
		templateFontSize: templateFontSize.value,
		signatureFontSize: signatureFontSize.value,
		signatureWidth: signatureWidth.value,
		signatureHeight: signatureHeight.value,
		renderMode: renderMode.value,
	})
		.then(({ data }) => {
			parsed.value = data.ocs.data.parsed
			checkPreviewOverflow()
			if (data.ocs.data.templateFontSize !== templateFontSize.value) {
				templateFontSize.value = data.ocs.data.templateFontSize
			}
			if (data.ocs.data.signatureFontSize !== signatureFontSize.value) {
				signatureFontSize.value = data.ocs.data.signatureFontSize
			}
			dislaySuccessTemplate.value = true
			templateSaved.value = true
			setTimeout(() => { dislaySuccessTemplate.value = false }, 2000)
		})
		.catch(({ response }) => {
			errorMessageTemplate.value.push(response.data.ocs.data.error)
			parsed.value = ''
			checkPreviewOverflow()
		})
}

const debouncePropertyChange = debounce(async () => {
	await saveTemplate()
}, 1000)

const debouncedSaveTemplate = debounce(saveTemplate, 500)

async function resetRenderMode() {
	renderMode.value = 'GRAPHIC_AND_DESCRIPTION'
	await saveTemplate()
}

async function resetTemplate() {
	signatureTextTemplate.value = SIGNATURE_TEXT_DEFAULTS.template
	await saveTemplate()
}

async function resetTemplateFontSize() {
	templateFontSize.value = SIGNATURE_TEXT_DEFAULTS.templateFontSize
	await saveTemplate()
}

async function resetSignatureFontSize() {
	signatureFontSize.value = SIGNATURE_TEXT_DEFAULTS.signatureFontSize
	await saveTemplate()
}

async function resetSignatureWidth() {
	signatureWidth.value = SIGNATURE_TEXT_DEFAULTS.signatureWidth
	await saveTemplate()
}

async function resetSignatureHeight() {
	signatureHeight.value = SIGNATURE_TEXT_DEFAULTS.signatureHeight
	await saveTemplate()
}

watch(signatureImageUrl, () => {
	isSignatureImageLoaded.value = false
})

onMounted(() => {
	subscribe('collect-metadata:changed', handleCollectMetadataChanged)
})

onBeforeUnmount(() => {
	unsubscribe('collect-metadata:changed', handleCollectMetadataChanged)
})

defineExpose({
	t,
	isRTL,
	isDarkTheme,
	mdiCheck,
	mdiContentCopy,
	mdiDelete,
	mdiHelpCircleOutline,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
	mdiUndoVariant,
	mdiUpload,
	input,
	rightColumn,
	textareaEditor,
	showLoadingBackground,
	backgroundType,
	acceptMime,
	mimeName,
	errorMessageBackground,
	backgroundUrl,
	signatureTextTemplate,
	signatureWidth,
	signatureHeight,
	signatureFontSize,
	templateFontSize,
	isSignatureImageLoaded,
	templateSaved,
	zoomLevel,
	renderMode,
	dislaySuccessTemplate,
	errorMessageTemplate,
	parsed,
	isRTLDirection,
	availableVariables,
	isOverflowing,
	showVariablesDialog,
	copiedVariable,
	displayResetBackground,
	displayRemoveBackground,
	displayPreview,
	inputValue,
	displayResetRenderMode,
	displayResetTemplate,
	displayResetTemplateFontSize,
	dislayResetSignatureFontSize,
	displayResetSignatureWidth,
	displayResetSignatureHeight,
	previewSignatureImageWidth,
	previewSignatureImageHeight,
	signatureImageUrl,
	previewLoaded,
	debouncePropertyChange,
	debouncedSaveTemplate,
	parsedWithLineBreak,
	getVariableText,
	isCopied,
	copyToClipboard,
	reset,
	refreshAfterChangeCollectMetadata,
	activateLocalFilePicker,
	changeZoomLevel,
	saveZoomLevel,
	onChangeBackground,
	undoBackground,
	removeBackground,
	checkPreviewOverflow,
	resizeHeight,
	resetRenderMode,
	resetTemplate,
	resetTemplateFontSize,
	resetSignatureFontSize,
	resetSignatureWidth,
	resetSignatureHeight,
	saveTemplate,
})
</script>

<style lang="scss" scoped>
.settings-section{
	display: flex;
	flex-direction: column;
	&__description {
		color: var(--color-text-lighter);
		margin-bottom: 8px;
	}
	&:deep(.settings-section__name) {
		justify-content: unset;
	}
	&__row {
		display: flex;
		gap: 0 4px;
		align-items: flex-start;
		:deep(.code-editor) {
			flex: 1;
			.CodeMirror {
				height: auto;
				min-height: 80px;
			}
		}
		:deep(.textarea) {
			flex: 1;
			textarea {
				height: 100%;
			}
		}
		&_template-only {
			width: 100%;
			display: flex;
		}
		&_signature, &_template, &_dimension {
			width: 50%;
			display: flex;
		}
		&_bar {
			max-width: unset;
		}
	}
	&__loading-icon {
		width: 44px;
		height: 44px;
	}
	&__zoom {
		display: flex;
		justify-content: center;
		gap: 4px;
	}
	&__zoom_level {
		display: flex;
		width: unset !important;
	}
	&__preview {
		background-size: contain;
		background-position: center;
		background-repeat: no-repeat;
		justify-content: space-between;
		display: flex;
		text-align: center;
		margin-top: 10px;
		border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
		position: relative;
		&__loading {
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
		}
		.left-column {
			display: flex;
			align-items: center;
			.left-column-content {
				border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
				border-radius: 10px;
				display: flex;
				align-items: center;
			}
		}
		.right-column {
			flex: 1;
			text-align: left;
			line-height: 1;
			word-wrap: anywhere;
			overflow: hidden;
			font-family: sans-serif;
			margin-left: 0;
		}
	}
	input[type="file"] {
		display: none;
	}
	.rtl {
		direction: rtl;
		text-align: right;
	}
}

.hidden-visually {
	position: absolute;
	left: -10000px;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
}

.variables-dialog {
	padding: 16px;

	&__description {
		margin-bottom: 16px;
		color: var(--color-text-lighter);
		font-size: 14px;
	}
}

.variables-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: 60vh;
	overflow-y: auto;
}

.variable-description {
	margin: 0;
}
</style>
