<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste">
		<section class="ste__metadata-policy">
			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="collectMetadataEnabled"
				@update:modelValue="onCollectMetadataToggle">
				<div>
					<strong>{{ t('libresign', 'Collect signer metadata') }}</strong>
				</div>
			</NcCheckboxRadioSwitch>
		</section>

		<SignatureTextTemplateSection
			:id="id"
			:render-mode="config.renderMode"
			:template="config.template"
			:display-mode-options="displayModeOptions"
			:available-variables="availableVariables"
			:show-reset-render-mode-button="showResetRenderModeButton"
			:show-reset-template-button="showResetTemplateButton"
			@update:render-mode="setDisplayMode"
			@update:template="(value) => { config.template = value }"
			@reset-render-mode="resetRenderModeToDefault"
			@reset-template="resetTemplateToDefault" />

		<SignatureTextDimensionsSection
			:id="id"
			:render-mode="config.renderMode"
			:template-font-size="config.templateFontSize"
			:signature-font-size="config.signatureFontSize"
			:signature-width="config.signatureWidth"
			:signature-height="config.signatureHeight"
			:show-reset-template-font-size-button="showResetTemplateFontSizeButton"
			:show-reset-signature-font-size-button="showResetSignatureFontSizeButton"
			:show-reset-width-button="showResetWidthButton"
			:show-reset-height-button="showResetHeightButton"
			@update:template-font-size="(value) => { config.templateFontSize = value }"
			@update:signature-font-size="(value) => { config.signatureFontSize = value }"
			@update:signature-width="(value) => { config.signatureWidth = value }"
			@update:signature-height="(value) => { config.signatureHeight = value }"
			@reset-template-font-size="resetTemplateFontSizeToDefault"
			@reset-signature-font-size="resetSignatureFontSizeToDefault"
			@reset-width="resetWidthToDefault"
			@reset-height="resetHeightToDefault" />

		<SignatureTextBackgroundSection
			:background-type="config.backgroundType"
			:background-options="backgroundOptions"
			:show-loading="showLoading"
			:error-message="errorMessage"
			@select-background="setBackgroundType"
			@reset-background="resetBackgroundToDefault"
			@remove-background="() => setBackgroundType('deleted')"
			@file-selected="onBackgroundFileSelected" />

		<SignatureTextPreviewSection
			:id="id"
			:signature-width="config.signatureWidth"
			:signature-height="config.signatureHeight"
			:preview-zoom-input="previewZoomInput"
			:preview-frame-style="previewFrameStyle"
			:preview-scale="previewScale"
			:pdf-preview-file="pdfPreviewFile"
			:preview-loading="previewLoading"
			:preview-error="previewError"
			:preview-render-key="previewPdfRenderKey"
			:show-reset-defaults-button="showResetDefaultsButton"
			@reset-defaults="resetToDefaults"
			@change-zoom="changeZoom"
			@zoom-input="onZoomInput"
			@commit-zoom-input="commitZoomInput" />
	</div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref, watch } from 'vue'

import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import SignatureTextBackgroundSection from './SignatureTextBackgroundSection.vue'
import SignatureTextDimensionsSection from './SignatureTextDimensionsSection.vue'
import SignatureTextPreviewSection from './SignatureTextPreviewSection.vue'
import SignatureTextTemplateSection from './SignatureTextTemplateSection.vue'
import { ensurePdfWorker } from '../../../../../helpers/pdfWorker'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureStampDraftValue,
	normalizeSignatureTextPolicyConfig,
	resolveCollectMetadataValue,
	serializeSignatureTextPolicyConfig,
	toRuntimeRenderMode,
} from './model'

type BackgroundType = 'default' | 'custom' | 'deleted'
type DisplayMode = 'default' | 'graphic' | 'text' | 'description_only'

const STAMP_PREVIEW_PATH = '/apps/libresign/api/v1/signature-stamp/preview-pdf'
const STAMP_PREVIEW_ZOOM_STORAGE_KEY = 'libresign.policy.signatureStamp.previewZoom'

const props = defineProps({
	modelValue: {
		type: [String, Number, Boolean, Object, Array],
		default: '',
	},
	inheritedValue: {
		type: [String, Number, Boolean, Object, Array],
		default: null,
	},
	collectMetadataEnabled: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['update:modelValue'])

ensurePdfWorker()

const backgroundOptions: Array<{ value: BackgroundType; label: string; description: string }> = [
	{
		value: 'default',
		label: t('libresign', 'Default'),
		description: t('libresign', 'Use the default LibreSign background image.'),
	},
	{
		value: 'custom',
		label: t('libresign', 'Custom'),
		description: t('libresign', 'Use a custom image uploaded by an administrator.'),
	},
	{
		value: 'deleted',
		label: t('libresign', 'None'),
		description: t('libresign', 'Do not apply any background image to signatures.'),
	},
]

const displayModeOptions: Array<{ value: DisplayMode; label: string; description: string }> = [
	{
		value: 'description_only',
		label: t('libresign', 'Description only'),
		description: t('libresign', 'Shows only the stamp description text at full width. No signature graphic or signer name.'),
	},
	{
		value: 'default',
		label: t('libresign', 'Signature and description'),
		description: t('libresign', 'Displays the visual signature area on the left together with stamp text on the right.'),
	},
	{
		value: 'text',
		label: t('libresign', 'Signer name and description'),
		description: t('libresign', 'Shows the signer name as large text on the left half and description on the right.'),
	},
	{
		value: 'graphic',
		label: t('libresign', 'Signature only'),
		description: t('libresign', 'Displays only the visual signature without any description text.'),
	},
]

const availableVariables = [
	{ value: '{{DocumentUUID}}', description: t('libresign', 'Unique identifier of the signed document') },
	{ value: '{{IssuerCommonName}}', description: t('libresign', 'Name of the certificate issuer used for the signature.') },
	{ value: '{{LocalSignerSignatureDateOnly}}', description: t('libresign', 'Date when the signer sent the request to sign (without time, in their local time zone).') },
	{ value: '{{LocalSignerSignatureDateTime}}', description: t('libresign', 'Date and time when the signer sent the request to sign (in their local time zone).') },
	{ value: '{{LocalSignerTimezone}}', description: t('libresign', 'Time zone of signer when sent the request to sign (in their local time zone).') },
	{ value: '{{ServerSignatureDate}}', description: t('libresign', 'Date and time when the signature was applied on the server (ISO 8601 format). Can be formatted using the Twig date filter.') },
	{ value: '{{SignerCommonName}}', description: t('libresign', 'Common Name (CN) used to identify the document signer.') },
	{ value: '{{SignerEmail}}', description: t('libresign', 'The signer\'s email is optional and can be left blank.') },
	{ value: '{{SignerIdentifier}}', description: t('libresign', 'Unique information used to identify the signer (such as email, phone number, or username).') },
	{ value: '{{SignerIP}}', description: t('libresign', 'IP address of the person who signed the document.') },
	{ value: '{{SignerUserAgent}}', description: t('libresign', 'Browser and device information of the person who signed the document.') },
]

const id = Math.random().toString(36).substring(7)
const initialDraftValue = normalizeSignatureStampDraftValue(
	props.modelValue,
	resolveCollectMetadataValue(props.collectMetadataEnabled, false),
)
const normalized = normalizeSignatureTextPolicyConfig(initialDraftValue.signatureStampValue)
const defaultConfig = getDefaultSignatureTextPolicyConfig()
const inheritedConfig = computed(() => props.inheritedValue === null
	|| props.inheritedValue === undefined
	? normalized
	: normalizeSignatureTextPolicyConfig(props.inheritedValue))

const showLoading = ref(false)
const errorMessage = ref('')
const previewZoom = ref(readStoredZoom())
const previewZoomInput = ref(String(previewZoom.value))

const pdfPreviewFile = ref<File | null>(null)
const previewLoading = ref(false)
const previewError = ref('')
const previewRenderKey = ref(0)
let previewTimer: ReturnType<typeof setTimeout> | null = null
let previewAbortController: AbortController | null = null

const config = reactive({
	template: normalized.template,
	templateFontSize: normalized.templateFontSize,
	signatureFontSize: normalized.signatureFontSize,
	signatureWidth: normalized.signatureWidth,
	signatureHeight: normalized.signatureHeight,
	backgroundType: normalized.backgroundType as BackgroundType,
	renderMode: normalized.renderMode as DisplayMode,
})

const collectMetadataEnabled = ref(initialDraftValue.collectMetadataEnabled)
const signerIpTemplateLine = t('libresign', 'IP: {{SignerIP}}')
const signerUserAgentTemplateLine = t('libresign', 'User agent: {{SignerUserAgent}}')

function syncTemplateWithCollectMetadata(template: string, enabled: boolean): string {
	const normalized = String(template ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
	const lines = normalized.split('\n')

	if (!enabled) {
		const filtered = lines.filter((line) => !line.includes('{{SignerIP}}') && !line.includes('{{SignerUserAgent}}'))
		return filtered.join('\n')
	}

	const hasSignerIp = lines.some((line) => line.includes('{{SignerIP}}'))
	const hasSignerUserAgent = lines.some((line) => line.includes('{{SignerUserAgent}}'))

	if (!hasSignerIp) {
		lines.push(signerIpTemplateLine)
	}

	if (!hasSignerUserAgent) {
		lines.push(signerUserAgentTemplateLine)
	}

	return lines.join('\n')
}

function applyCollectMetadataEnabled(nextValue: boolean): void {
	collectMetadataEnabled.value = nextValue
	const syncedTemplate = syncTemplateWithCollectMetadata(config.template, nextValue)
	if (syncedTemplate !== config.template) {
		config.template = syncedTemplate
	}
}

function applyNormalizedConfig(nextConfig: ReturnType<typeof normalizeSignatureTextPolicyConfig>): void {
	config.template = nextConfig.template
	config.templateFontSize = nextConfig.templateFontSize
	config.signatureFontSize = nextConfig.signatureFontSize
	config.signatureWidth = nextConfig.signatureWidth
	config.signatureHeight = nextConfig.signatureHeight
	config.backgroundType = nextConfig.backgroundType as BackgroundType
	config.renderMode = nextConfig.renderMode as DisplayMode
}

const previewScale = computed(() => Math.max(0.25, Math.min(5, previewZoom.value / 100)))

const previewFrameStyle = computed(() => {
	const safeWidth = Math.max(20, Number(config.signatureWidth) || defaultConfig.signatureWidth)
	const safeHeight = Math.max(10, Number(config.signatureHeight) || defaultConfig.signatureHeight)
	return {
		width: `${Math.round(safeWidth * previewScale.value)}px`,
		height: `${Math.round(safeHeight * previewScale.value)}px`,
	}
})

const previewPdfRenderKey = computed(() => `${previewRenderKey.value}-${previewZoom.value}`)
const showResetRenderModeButton = computed(() => config.renderMode !== inheritedConfig.value.renderMode)
const showResetTemplateButton = computed(() => config.template !== inheritedConfig.value.template)
const showResetTemplateFontSizeButton = computed(() => config.templateFontSize !== inheritedConfig.value.templateFontSize)
const showResetSignatureFontSizeButton = computed(() => config.signatureFontSize !== inheritedConfig.value.signatureFontSize)
const showResetWidthButton = computed(() => config.signatureWidth !== inheritedConfig.value.signatureWidth)
const showResetHeightButton = computed(() => config.signatureHeight !== inheritedConfig.value.signatureHeight)
const showResetDefaultsButton = computed(() => (
	serializeSignatureTextPolicyConfig(config) !== serializeSignatureTextPolicyConfig(inheritedConfig.value)
	|| previewZoom.value !== 100
))

const emitUpdate = () => {
	emit('update:modelValue', {
		signatureStampValue: serializeSignatureTextPolicyConfig(config),
		collectMetadataEnabled: collectMetadataEnabled.value,
	})
}

watch(() => config.template, emitUpdate)
watch(() => config.templateFontSize, emitUpdate)
watch(() => config.signatureFontSize, emitUpdate)
watch(() => config.signatureWidth, emitUpdate)
watch(() => config.signatureHeight, emitUpdate)
watch(() => config.backgroundType, emitUpdate)
watch(() => config.renderMode, emitUpdate)

watch(() => props.modelValue, (nextValue) => {
	const normalizedDraftValue = normalizeSignatureStampDraftValue(
		nextValue,
		collectMetadataEnabled.value,
	)
	const nextConfig = normalizeSignatureTextPolicyConfig(normalizedDraftValue.signatureStampValue)
	if (serializeSignatureTextPolicyConfig(config) === serializeSignatureTextPolicyConfig(nextConfig)) {
		collectMetadataEnabled.value = normalizedDraftValue.collectMetadataEnabled
		return
	}
	collectMetadataEnabled.value = normalizedDraftValue.collectMetadataEnabled
	applyNormalizedConfig(nextConfig)
})

watch(() => props.collectMetadataEnabled, (nextValue) => {
	const normalizedValue = resolveCollectMetadataValue(nextValue, collectMetadataEnabled.value)
	if (collectMetadataEnabled.value === normalizedValue) {
		return
	}
	applyCollectMetadataEnabled(normalizedValue)
})

watch(collectMetadataEnabled, emitUpdate)

async function fetchPreview(): Promise<void> {
	if (previewAbortController) {
		previewAbortController.abort()
		previewAbortController = null
	}
	previewLoading.value = true
	previewError.value = ''
	const controller = new AbortController()
	previewAbortController = controller
	try {
		const response = await axios.post(
			generateOcsUrl(STAMP_PREVIEW_PATH),
			{
				template: config.template,
				templateFontSize: config.templateFontSize,
				signatureFontSize: config.signatureFontSize,
				signatureWidth: config.signatureWidth,
				signatureHeight: config.signatureHeight,
				renderMode: toRuntimeRenderMode(config.renderMode),
				backgroundType: config.backgroundType,
			},
			{ responseType: 'blob', signal: controller.signal },
		)
		pdfPreviewFile.value = new File([response.data as Blob], 'stamp-preview.pdf', { type: 'application/pdf' })
		previewRenderKey.value += 1
	} catch (e: unknown) {
		const name = e && typeof e === 'object' && 'name' in e ? (e as { name: string }).name : ''
		if (name === 'CanceledError' || name === 'AbortError') {
			return
		}
		pdfPreviewFile.value = null
		previewError.value = t('libresign', 'Unable to load preview. Please check the template and try again.')
	} finally {
		if (previewAbortController === controller) {
			previewLoading.value = false
		}
	}
}

function schedulePreview(): void {
	if (previewTimer) {
		clearTimeout(previewTimer)
	}
	previewTimer = setTimeout(() => {
		fetchPreview()
	}, 250)
}

watch(
	[
		() => config.template,
		() => config.templateFontSize,
		() => config.signatureFontSize,
		() => config.signatureWidth,
		() => config.signatureHeight,
		() => config.renderMode,
		() => config.backgroundType,
	],
	schedulePreview,
	{ immediate: true },
)

onUnmounted(() => {
	if (previewTimer) {
		clearTimeout(previewTimer)
	}
	if (previewAbortController) {
		previewAbortController.abort()
	}
})

function clampZoom(value: number): number {
	if (!Number.isFinite(value)) return 100
	return Math.max(25, Math.min(400, Math.round(value)))
}

function readStoredZoom(): number {
	if (typeof window === 'undefined') {
		return 100
	}
	const storedValue = window.localStorage.getItem(STAMP_PREVIEW_ZOOM_STORAGE_KEY)
	if (storedValue === null) {
		return 100
	}
	return clampZoom(Number(storedValue))
}

function persistZoom(value: number): void {
	if (typeof window === 'undefined') {
		return
	}
	window.localStorage.setItem(STAMP_PREVIEW_ZOOM_STORAGE_KEY, String(value))
}

function onZoomInput(event: Event): void {
	const target = event.target
	previewZoomInput.value = target instanceof HTMLInputElement ? target.value : previewZoomInput.value
}

function commitZoomInput(): void {
	previewZoom.value = clampZoom(Number(previewZoomInput.value))
}

function changeZoom(delta: number): void {
	previewZoom.value = clampZoom(previewZoom.value + delta)
}

watch(previewZoom, (value) => {
	const clamped = clampZoom(value)
	if (clamped !== value) {
		previewZoom.value = clamped
		return
	}
	previewZoomInput.value = String(clamped)
	persistZoom(clamped)
})

function setDisplayMode(value: DisplayMode): void {
	config.renderMode = value
}

function setBackgroundType(value: BackgroundType): void {
	errorMessage.value = ''
	config.backgroundType = value
}

function onCollectMetadataToggle(value: boolean | unknown): void {
	applyCollectMetadataEnabled(resolveCollectMetadataValue(value, collectMetadataEnabled.value))
}

function resetToDefaults(): void {
	applyNormalizedConfig(inheritedConfig.value)
	previewZoom.value = 100
	errorMessage.value = ''
}

function resetTemplateToDefault(): void {
	config.template = inheritedConfig.value.template
}

function resetRenderModeToDefault(): void {
	config.renderMode = inheritedConfig.value.renderMode as DisplayMode
}

function resetTemplateFontSizeToDefault(): void {
	config.templateFontSize = inheritedConfig.value.templateFontSize
}

function resetSignatureFontSizeToDefault(): void {
	config.signatureFontSize = inheritedConfig.value.signatureFontSize
}

function resetWidthToDefault(): void {
	config.signatureWidth = inheritedConfig.value.signatureWidth
}

function resetHeightToDefault(): void {
	config.signatureHeight = inheritedConfig.value.signatureHeight
}

function resetBackgroundToDefault(): void {
	config.backgroundType = inheritedConfig.value.backgroundType as BackgroundType
	errorMessage.value = ''
}

async function onBackgroundFileSelected(file: File): Promise<void> {
	const formData = new FormData()
	formData.append('image', file)

	showLoading.value = true
	errorMessage.value = ''
	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
		setBackgroundType('custom')
	} catch (error: unknown) {
		const response = error && typeof error === 'object' && 'response' in error
			? (error as { response?: { data?: { ocs?: { data?: { message?: string } } } } }).response
			: undefined
		errorMessage.value = response?.data?.ocs?.data?.message || t('libresign', 'Upload failed')
	} finally {
		showLoading.value = false
	}
}
</script>

<style scoped>
.ste {
	display: flex;
	flex-direction: column;
	gap: 0.9rem;
}
</style>
