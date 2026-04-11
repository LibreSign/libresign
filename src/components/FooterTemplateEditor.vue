<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="footer-template-section">
		<p v-linkify="{ linkify: true, text: footerDescription }" class="footer-template-description" />
		<div class="footer-template-header">
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Show available variables')"
				@click="showVariablesDialog = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" />
				</template>
				{{ t('libresign', 'Available variables') }}
			</NcButton>
			<NcButton v-if="!isDefaultTemplate"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset template to default')"
				@click="resetFooterTemplate">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>
		</div>
		<CodeEditor
			v-model="footerTemplate"
			:label="t('libresign', 'Footer template')"
			:placeholder="t('libresign', 'A twig template to be used at footer of PDF. Will be rendered by mPDF.')"
			@update:modelValue="debouncedSaveFooterTemplate" />
		<div v-if="pdfPreviewFile" class="footer-preview">
			<h4>{{ t('libresign', 'Preview') }}</h4>
			<div class="footer-preview__controls">
				<div class="footer-preview__zoom-controls">
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
					<NcTextField
						v-model="zoomLevel"
						class="footer-preview__zoom-level"
						:label="t('libresign', 'Zoom level')"
						type="number"
						:min="10"
						:step="10"
						:spellcheck="false"
						@input="onZoomInput" />
				</div>
				<div class="footer-preview__dimension-controls">
					<NcTextField
						v-model="previewWidth"
						:label="t('libresign', 'Width')"
						type="number"
						:min="100"
						:max="2000"
						:spellcheck="false"
						@input="debouncedSaveDimensions" />
					<NcTextField
						v-model="previewHeight"
						:label="t('libresign', 'Height')"
						type="number"
						:min="10"
						:max="500"
						:spellcheck="false"
						@input="debouncedSaveDimensions" />
					<NcButton v-if="showResetDimensions"
						:aria-label="t('libresign', 'Reset dimensions')"
						variant="tertiary"
						@click="resetDimensions">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
			<div ref="pdfContainer" class="footer-preview__pdf" :style="`min-height: ${previewContainerMinHeight}px`">
				<div v-if="loadingPreview" class="footer-preview__loading">
					<NcLoadingIcon :size="64" />
				</div>
				<PDFElements ref="pdfPreview"
					:key="pdfKey"
					:init-files="pdfPreviewFile ? [pdfPreviewFile] : []"
					:init-file-names="['preview.pdf']"
					:initial-scale="zoomLevel / 100"
					:show-page-footer="false"
					@pdf-elements:end-init="onPdfReady" />
			</div>
		</div>

		<NcDialog :name="t('libresign', 'Available template variables')"
			v-model:open="showVariablesDialog"
			size="normal">
			<div class="variables-dialog">
				<p class="variables-dialog__description">
					{{ t('libresign', 'Click on a variable to copy it to clipboard') }}
				</p>
				<div class="variables-list">
					<NcFormBoxButton v-for="(meta, name) in templateVariables"
						:key="name"
						inverted-accent
						@click="copyToClipboard(getVariableText(name))">
						<template #default>
							<span class="hidden-visually">
								{{ t('libresign', 'Copy to clipboard') }}
							</span>
							{{ getVariableText(name) }}
						</template>
					<template #icon>
						<NcIconSvgWrapper v-if="isCopied(name)" :path="mdiCheck" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiContentCopy" :size="20" />
					</template>
						<template #description>
							<p class="variable-description">{{ meta.description }}</p>
							<div class="variable-meta">
								<span class="meta-badge">{{ meta.type }}</span>
								<code v-if="meta.example" class="meta-example">{{ meta.example }}</code>
								<span v-if="meta.default" class="meta-default">{{ t('libresign', 'Default:') }} {{ meta.default }}</span>
							</div>
						</template>
					</NcFormBoxButton>
				</div>
			</div>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { nextTick, onMounted, ref, computed } from 'vue'

import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Linkify from '@nextcloud/vue/directives/Linkify'

import PDFElements from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'

import CodeEditor from './CodeEditor.vue'
import { ensurePdfWorker } from '../helpers/pdfWorker'

import {
	mdiCheck,
	mdiContentCopy,
	mdiHelpCircleOutline,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
	mdiUndoVariant,
} from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineOptions({
	name: 'FooterTemplateEditor',
})

const emit = defineEmits<{
	(event: 'template-reset'): void
}>()

const vLinkify = Linkify

type TemplateVariableMeta = {
	description?: string
	type?: string
	example?: string
	default?: string
}

type PdfPreviewRef = {
	scale: number
}

type AppConfigApi = {
	deleteKey: (app: string, key: string) => void
	setValue: (app: string, key: string, value: string | number) => void
}

const DEFAULT_PREVIEW_WIDTH = 595
const DEFAULT_PREVIEW_HEIGHT = 100

const footerDescription = t('libresign', 'Configure the content displayed at the footer of the PDF. The text template uses Twig syntax: https://twig.symfony.com/')
const footerTemplate = ref('')
const isDefaultTemplate = ref(loadState('libresign', 'footer_template_is_default', true))
const pdfPreviewFile = ref<File | null>(null)
const loadingPreview = ref(false)
const pdfKey = ref(0)
const zoomLevel = ref(loadState('libresign', 'footer_preview_zoom_level', 100))
const previewWidth = ref<number | string>(DEFAULT_PREVIEW_WIDTH)
const previewHeight = ref<number | string>(DEFAULT_PREVIEW_HEIGHT)
const containerHeight = ref<number | null>(null)
const showVariablesDialog = ref(false)
const templateVariables = ref<Record<string, TemplateVariableMeta>>(loadState('libresign', 'footer_template_variables', {}))
const copiedVariable = ref<string | null>(null)

const pdfContainer = ref<HTMLElement | null>(null)
const pdfPreview = ref<PdfPreviewRef | null>(null)

const showResetDimensions = computed(() => Number(previewWidth.value) !== DEFAULT_PREVIEW_WIDTH || Number(previewHeight.value) !== DEFAULT_PREVIEW_HEIGHT)

const previewContainerMinHeight = computed(() => {
	if (containerHeight.value && containerHeight.value > 0) {
		return containerHeight.value
	}
	return estimateContainerHeightForFirstRender(Number(previewHeight.value), Number(zoomLevel.value))
})

function estimateContainerHeightForFirstRender(height: number, zoom: number): number {
	if (!Number.isFinite(height) || height <= 0 || !Number.isFinite(zoom) || zoom <= 0) {
		return 160
	}
	return Math.max(160, Math.round((height * zoom) / 100) + 24)
}

const appConfig = (globalThis as typeof globalThis & { OCP?: { AppConfig: AppConfigApi } }).OCP?.AppConfig

ensurePdfWorker()

function getVariableText(name: string) {
	return `{{ ${name} }}`
}

function isCopied(name: string) {
	return copiedVariable.value === getVariableText(name)
}

function copyToClipboard(text: string) {
	if (copiedVariable.value === text) {
		return
	}

	try {
		navigator.clipboard.writeText(text)
	} catch {
		prompt('', text)
	}

	copiedVariable.value = text
	setTimeout(() => {
		copiedVariable.value = null
	}, 2000)
}

async function resetFooterTemplate() {
	loadingPreview.value = true
	try {
		await axios.post(
			generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'),
			{
				template: '',
				width: Number(previewWidth.value),
				height: Number(previewHeight.value),
			},
			{ responseType: 'blob' },
		).then(response => {
			footerTemplate.value = ''
			isDefaultTemplate.value = true
			setPdfPreview(response.data)
			emit('template-reset')
		})
	} catch (error) {
		console.error('Error resetting footer template:', error)
		loadingPreview.value = false
	}
}

function resetDimensions() {
	previewWidth.value = DEFAULT_PREVIEW_WIDTH
	previewHeight.value = DEFAULT_PREVIEW_HEIGHT
	appConfig?.deleteKey('libresign', 'footer_preview_width')
	appConfig?.deleteKey('libresign', 'footer_preview_height')
}

function saveDimensions() {
	if (Number(previewWidth.value) === DEFAULT_PREVIEW_WIDTH && Number(previewHeight.value) === DEFAULT_PREVIEW_HEIGHT) {
		appConfig?.deleteKey('libresign', 'footer_preview_width')
		appConfig?.deleteKey('libresign', 'footer_preview_height')
	} else {
		appConfig?.setValue('libresign', 'footer_preview_width', previewWidth.value)
		appConfig?.setValue('libresign', 'footer_preview_height', previewHeight.value)
	}
	saveFooterTemplate()
}

function saveFooterTemplate() {
	axios.post(
		generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'),
		{
			template: footerTemplate.value,
			width: Number(previewWidth.value),
			height: Number(previewHeight.value),
		},
		{ responseType: 'blob' },
	).then(response => {
		setPdfPreview(response.data)
	}).catch(error => {
		console.error('Error saving footer template:', error)
	})
}

function setPdfPreview(blob: Blob) {
	loadingPreview.value = true

	if (pdfContainer.value) {
		containerHeight.value = pdfContainer.value.offsetHeight
	}

	nextTick(() => {
		const timestamp = Date.now()
		pdfPreviewFile.value = new File([blob], `footer-preview-${timestamp}.pdf`, { type: 'application/pdf' })
		pdfKey.value += 1
	})
}

function onPdfReady() {
	loadingPreview.value = false
	containerHeight.value = null
}

function changeZoomLevel(delta: number) {
	zoomLevel.value = Number(zoomLevel.value) + delta
	updateScale()
}

function onZoomInput() {
	debouncedUpdateScale()
}

function updateScale() {
	if (pdfPreview.value) {
		pdfPreview.value.scale = Number(zoomLevel.value) / 100
	}
}

const debouncedSaveFooterTemplate = debounce(saveFooterTemplate, 500)
const debouncedUpdateScale = debounce(updateScale, 300)
const debouncedSaveDimensions = debounce(saveDimensions, 500)

onMounted(() => {
	axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'))
		.then(response => {
			footerTemplate.value = response.data.ocs.data.template
			isDefaultTemplate.value = response.data.ocs.data.isDefault ?? true
			previewHeight.value = response.data.ocs.data.preview_height
			previewWidth.value = response.data.ocs.data.preview_width
			saveFooterTemplate()
		})
})

defineExpose({
	DEFAULT_PREVIEW_WIDTH,
	DEFAULT_PREVIEW_HEIGHT,
	footerDescription,
	footerTemplate,
	pdfPreviewFile,
	loadingPreview,
	pdfKey,
	zoomLevel,
	previewWidth,
	previewHeight,
	containerHeight,
	showVariablesDialog,
	templateVariables,
	copiedVariable,
	showResetDimensions,
	previewContainerMinHeight,
	getVariableText,
	isCopied,
	copyToClipboard,
	resetFooterTemplate,
	resetDimensions,
	saveDimensions,
	saveFooterTemplate,
	setPdfPreview,
	onPdfReady,
	changeZoomLevel,
	onZoomInput,
	updateScale,
	debouncedSaveFooterTemplate,
	debouncedUpdateScale,
	debouncedSaveDimensions,
	pdfPreview,
})
</script>

<style lang="scss" scoped>
.footer-template-section {
	display: flex;
	flex-direction: column;
	gap: 16px;

	.footer-template-description {
		color: var(--color-text-lighter);
	}

	.footer-template-header {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
		margin-bottom: 4px;
	}
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
	margin: 0 0 8px 0;
}

.variable-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	font-size: 12px;
	margin-top: 8px;
}

.meta-badge {
	padding: 2px 6px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	border-radius: var(--border-radius);
	font-weight: 500;
	text-transform: uppercase;
	font-size: 10px;
}

.meta-example {
	color: var(--color-text-maxcontrast);
	font-family: monospace;
}

.meta-default {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.hidden-visually {
	position: absolute;
	inset-inline-start: -10000px;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
}

.footer-preview {
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);

	h4 {
		margin: 0 0 8px 0;
		font-weight: bold;
		color: var(--color-text-maxcontrast);
		font-size: 14px;
	}

	&__controls {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		margin-bottom: 10px;
	}

	&__zoom-controls {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	&__dimension-controls {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	&__zoom-level {
		width: 100px;
	}

	&__pdf {
		width: 100%;
		max-width: 100%;
		overflow: hidden;
		position: relative;

		:deep(.py-12) {
			padding: unset;
		}

		:deep(.pdf-editor),
		:deep(.pdf-wrapper) {
			overflow-x: hidden;
		}

		:deep(.vue-pdf-embed__page) {
			margin: 0;
			padding: 0;
		}
	}

	&__loading {
		position: absolute;
		top: 0;
		inset-inline: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: rgba(255, 255, 255, 0.8);
		z-index: 100;
	}
}
</style>
