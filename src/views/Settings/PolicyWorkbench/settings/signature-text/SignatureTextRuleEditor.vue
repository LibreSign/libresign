<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste">
		<!-- RENDER MODE -->
		<div class="ste__group">
			<div class="ste__label-row">
				<label class="ste__label">{{ t('libresign', 'Render mode') }}</label>
				<NcButton
					v-if="config.renderMode !== stampDefaults.renderMode"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetRenderModeToDefault">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="ste__seg ste__seg--modes" role="radiogroup" :aria-label="t('libresign', 'Render mode')">
				<button
					v-for="opt in displayModeOptions"
					:key="opt.value"
					type="button"
					class="ste__seg-btn"
					:class="{ 'ste__seg-btn--active': config.renderMode === opt.value }"
					:aria-pressed="config.renderMode === opt.value"
					:title="opt.description"
					@click="setDisplayMode(opt.value)">
					{{ opt.label }}
				</button>
			</div>
		</div>

		<!-- TEMPLATE EDITOR (hidden for Signature-only mode) -->
		<div v-if="config.renderMode !== 'graphic'" class="ste__group">
			<div class="ste__label-row">
				<label :for="`ste-tpl-${id}`" class="ste__label">{{ t('libresign', 'Signature text template') }}</label>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Show available variables')"
					@click="showVariablesDialog = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" />
					</template>
					{{ t('libresign', 'Available variables') }}
				</NcButton>
				<NcButton
					v-if="showResetTemplateButton"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetTemplateToDefault">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
					{{ t('libresign', 'Reset to default') }}
				</NcButton>
			</div>
			<textarea
				:id="`ste-tpl-${id}`"
				v-model="config.template"
				class="ste__textarea"
				:placeholder="t('libresign', 'Enter signature text template…')"
				spellcheck="false" />
		</div>

		<!-- DIMENSIONS -->
		<div class="ste__group ste__dims">
			<!-- Template font size: shown for all modes except Signature-only -->
			<div v-if="config.renderMode !== 'graphic'" class="ste__field">
				<div class="ste__label-row">
					<label :for="`ste-tfs-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Text font') }}</label>
					<NcButton
						v-if="config.templateFontSize !== stampDefaults.templateFontSize"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetTemplateFontSizeToDefault">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="ste__input-unit">
					<input :id="`ste-tfs-${id}`" v-model.number="config.templateFontSize" type="number" :min="0.1" :max="30" :step="0.1" class="ste__num-input">
					<span class="ste__unit">pt</span>
				</div>
			</div>
			<!-- Signature font size: shown only for Signer name + description mode -->
			<div v-if="config.renderMode === 'text'" class="ste__field">
				<div class="ste__label-row">
					<label :for="`ste-sfs-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Sig font') }}</label>
					<NcButton
						v-if="config.signatureFontSize !== stampDefaults.signatureFontSize"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetSignatureFontSizeToDefault">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="ste__input-unit">
					<input :id="`ste-sfs-${id}`" v-model.number="config.signatureFontSize" type="number" :min="0.1" :max="30" :step="0.1" class="ste__num-input">
					<span class="ste__unit">pt</span>
				</div>
			</div>
			<div class="ste__field">
				<div class="ste__label-row">
					<label :for="`ste-w-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Width') }}</label>
					<NcButton
						v-if="config.signatureWidth !== stampDefaults.signatureWidth"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetWidthToDefault">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="ste__input-unit">
					<input :id="`ste-w-${id}`" v-model.number="config.signatureWidth" type="number" :min="1" :max="800" class="ste__num-input">
					<span class="ste__unit">px</span>
				</div>
			</div>
			<div class="ste__field">
				<div class="ste__label-row">
					<label :for="`ste-h-${id}`" class="ste__label ste__label--sm">{{ t('libresign', 'Height') }}</label>
					<NcButton
						v-if="config.signatureHeight !== stampDefaults.signatureHeight"
						variant="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetHeightToDefault">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="ste__input-unit">
					<input :id="`ste-h-${id}`" v-model.number="config.signatureHeight" type="number" :min="1" :max="800" class="ste__num-input">
					<span class="ste__unit">px</span>
				</div>
			</div>
		</div>

		<!-- BACKGROUND -->
		<div class="ste__group ste__bg-row">
			<span class="ste__label">{{ t('libresign', 'Background') }}</span>
			<div class="ste__seg ste__seg--background" role="radiogroup" :aria-label="t('libresign', 'Background source')">
				<button
					v-for="opt in backgroundOptions"
					:key="opt.value"
					type="button"
					class="ste__seg-btn"
					:class="{ 'ste__seg-btn--active': config.backgroundType === opt.value }"
					:aria-pressed="config.backgroundType === opt.value"
					:title="opt.description"
					@click="selectBackground(opt.value)">
					{{ opt.label }}
				</button>
			</div>
			<NcButton
				variant="secondary"
				:aria-label="t('libresign', 'Upload background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUpload" :size="16" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton
				v-if="config.backgroundType !== 'default'"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset background to default')"
				@click="() => setBackgroundType('default')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>
			<NcButton
				v-if="config.backgroundType !== 'deleted'"
				variant="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="() => setBackgroundType('deleted')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>
			<NcLoadingIcon v-if="showLoading" :size="20" />
			<input ref="input" type="file" accept="image/png" class="ste__file-input" @change="onChangeBackground">
		</div>

		<NcNoteCard v-if="errorMessage" type="error" :show-alert="true">
			<p>{{ errorMessage }}</p>
		</NcNoteCard>

		<!-- PREVIEW -->
		<div class="ste__preview-section">
			<div class="ste__preview-header">
				<span class="ste__label">{{ t('libresign', 'Preview') }}</span>
				<NcButton
					variant="tertiary"
					:aria-label="t('libresign', 'Reset signature stamp settings to defaults')"
					@click="resetToDefaults">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="16" />
					</template>
					{{ t('libresign', 'Reset defaults') }}
				</NcButton>
				<span class="ste__preview-meta">{{ config.signatureWidth }} × {{ config.signatureHeight }}</span>
				<div class="ste__zoom">
					<NcButton
						variant="tertiary"
						class="ste__zoom-btn"
						:aria-label="t('libresign', 'Decrease zoom')"
						@click="changeZoom(-10)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiMagnifyMinus" :size="16" />
						</template>
					</NcButton>
					<span class="ste__zoom-val">{{ previewZoom }}%</span>
					<NcButton
						variant="tertiary"
						class="ste__zoom-btn"
						:aria-label="t('libresign', 'Increase zoom')"
						@click="changeZoom(10)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiMagnifyPlus" :size="16" />
						</template>
					</NcButton>
				</div>
			</div>
			<div class="ste__preview-stage">
				<div class="ste__preview-frame" :style="previewFrameStyle">
					<PDFElements
						v-if="pdfPreviewFile"
						:key="previewRenderKey"
						class="ste__preview-pdf"
						:style="{ width: '100%', height: '100%' }"
						:init-files="[pdfPreviewFile]"
						:init-file-names="['stamp-preview.pdf']"
						:initial-scale="1"
						:show-page-footer="false" />
					<div v-else-if="previewLoading" class="ste__preview-placeholder">
						<NcLoadingIcon :size="32" />
					</div>
					<div v-else class="ste__preview-placeholder ste__preview-placeholder--empty">
						{{ t('libresign', 'Preview will appear here') }}
					</div>
				</div>
			</div>
		</div>

		<NcDialog
			:name="t('libresign', 'Available template variables')"
			v-model:open="showVariablesDialog"
			size="normal">
			<div class="ste__vars-dialog">
				<p class="ste__vars-description">
					{{ t('libresign', 'Click on a variable to copy it to clipboard') }}
				</p>
				<div class="ste__vars-list">
					<NcFormBoxButton
						v-for="variable in availableVariables"
						:key="variable.value"
						inverted-accent
						@click="copyVariableToClipboard(variable.value)">
						<template #default>
							<span class="hidden-visually">{{ t('libresign', 'Copy to clipboard') }}</span>
							{{ variable.value }}
						</template>
						<template #icon>
							<NcIconSvgWrapper v-if="copiedVariable === variable.value" :path="mdiCheck" :size="20" />
							<NcIconSvgWrapper v-else :path="mdiContentCopy" :size="20" />
						</template>
						<template #description>
							<p class="ste__variable-description">{{ variable.description }}</p>
						</template>
					</NcFormBoxButton>
				</div>
			</div>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { mdiCheck, mdiContentCopy, mdiDelete, mdiHelpCircleOutline, mdiMagnifyMinus, mdiMagnifyPlus, mdiUndoVariant, mdiUpload } from '@mdi/js'
import { computed, onUnmounted, reactive, ref, watch } from 'vue'

import axios from '@nextcloud/axios'
import PDFElements from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureTextPolicyConfig,
	serializeSignatureTextPolicyConfig,
} from './model'

type BackgroundType = 'default' | 'custom' | 'deleted'
type DisplayMode = 'default' | 'graphic' | 'text' | 'description_only'

const STAMP_PREVIEW_PATH = '/apps/libresign/api/v1/admin/signature-stamp/preview-pdf'

const props = defineProps({
	modelValue: {
		type: [String, Number, Boolean, Object, Array],
		default: '',
	},
})

const emit = defineEmits(['update:modelValue'])

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
	{
		value: '{{DocumentUUID}}',
		description: t('libresign', 'Unique identifier of the signed document'),
	},
	{
		value: '{{IssuerCommonName}}',
		description: t('libresign', 'Name of the certificate issuer used for the signature.'),
	},
	{
		value: '{{LocalSignerSignatureDateOnly}}',
		description: t('libresign', 'Date when the signer sent the request to sign (without time, in their local time zone).'),
	},
	{
		value: '{{LocalSignerSignatureDateTime}}',
		description: t('libresign', 'Date and time when the signer sent the request to sign (in their local time zone).'),
	},
	{
		value: '{{LocalSignerTimezone}}',
		description: t('libresign', 'Time zone of signer when sent the request to sign (in their local time zone).'),
	},
	{
		value: '{{ServerSignatureDate}}',
		description: t('libresign', 'Date and time when the signature was applied on the server (ISO 8601 format). Can be formatted using the Twig date filter.'),
	},
	{
		value: '{{SignerCommonName}}',
		description: t('libresign', 'Common Name (CN) used to identify the document signer.'),
	},
	{
		value: '{{SignerEmail}}',
		description: t('libresign', 'The signer\'s email is optional and can be left blank.'),
	},
	{
		value: '{{SignerIdentifier}}',
		description: t('libresign', 'Unique information used to identify the signer (such as email, phone number, or username).'),
	},
	{
		value: '{{SignerIP}}',
		description: t('libresign', 'IP address of the person who signed the document.'),
	},
	{
		value: '{{SignerUserAgent}}',
		description: t('libresign', 'Browser and device information of the person who signed the document.'),
	},
]

const id = Math.random().toString(36).substring(7)
const normalized = normalizeSignatureTextPolicyConfig(props.modelValue)
const stampDefaults = getDefaultSignatureTextPolicyConfig()
const input = ref<HTMLInputElement | null>(null)
const showLoading = ref(false)
const errorMessage = ref('')
const previewZoom = ref(100)
const showVariablesDialog = ref(false)
const copiedVariable = ref<string | null>(null)

// Preview state
const pdfPreviewFile = ref<File | null>(null)
const previewLoading = ref(false)
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

const previewFrameStyle = computed(() => {
	const safeWidth = Math.max(20, Number(config.signatureWidth) || 90)
	const safeHeight = Math.max(10, Number(config.signatureHeight) || 60)
	const scale = Math.max(0.25, Math.min(5, previewZoom.value / 100))
	return {
		width: `${Math.round(safeWidth * scale)}px`,
		height: `${Math.round(safeHeight * scale)}px`,
	}
})

const showResetTemplateButton = computed(() => config.template.trim().length > 0)

const emitUpdate = () => {
	emit('update:modelValue', serializeSignatureTextPolicyConfig(config))
}

watch(() => config.template, emitUpdate)
watch(() => config.templateFontSize, emitUpdate)
watch(() => config.signatureFontSize, emitUpdate)
watch(() => config.signatureWidth, emitUpdate)
watch(() => config.signatureHeight, emitUpdate)
watch(() => config.backgroundType, emitUpdate)
watch(() => config.renderMode, emitUpdate)

async function fetchPreview(): Promise<void> {
	if (previewAbortController) {
		previewAbortController.abort()
		previewAbortController = null
	}
	previewLoading.value = true
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
				renderMode: config.renderMode,
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
		// Non-abort errors: leave existing preview in place
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

// Watch all config fields for preview
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

function changeZoom(delta: number): void {
	previewZoom.value = clampZoom(previewZoom.value + delta)
}

watch(previewZoom, (value) => {
	const clamped = clampZoom(value)
	if (clamped !== value) {
		previewZoom.value = clamped
		return
	}
	previewRenderKey.value += 1
})

function setDisplayMode(value: DisplayMode): void {
	config.renderMode = value
}

function setBackgroundType(value: BackgroundType): void {
	errorMessage.value = ''
	config.backgroundType = value
}

function resetToDefaults(): void {
	config.template = stampDefaults.template
	config.templateFontSize = stampDefaults.templateFontSize
	config.signatureFontSize = stampDefaults.signatureFontSize
	config.signatureWidth = stampDefaults.signatureWidth
	config.signatureHeight = stampDefaults.signatureHeight
	config.backgroundType = stampDefaults.backgroundType as BackgroundType
	config.renderMode = stampDefaults.renderMode as DisplayMode
	previewZoom.value = 100
	errorMessage.value = ''
}

function resetRenderModeToDefault(): void {
	config.renderMode = stampDefaults.renderMode as DisplayMode
}

function resetTemplateToDefault(): void {
	config.template = stampDefaults.template
}

function resetTemplateFontSizeToDefault(): void {
	config.templateFontSize = stampDefaults.templateFontSize
}

function resetSignatureFontSizeToDefault(): void {
	config.signatureFontSize = stampDefaults.signatureFontSize
}

function resetWidthToDefault(): void {
	config.signatureWidth = stampDefaults.signatureWidth
}

function resetHeightToDefault(): void {
	config.signatureHeight = stampDefaults.signatureHeight
}

function selectBackground(value: BackgroundType): void {
	if (value === 'custom') {
		activateLocalFilePicker()
		return
	}
	setBackgroundType(value)
}

function activateLocalFilePicker(): void {
	errorMessage.value = ''
	if (!input.value) return
	input.value.value = ''
	input.value.click()
}

function copyVariableToClipboard(value: string): void {
	if (copiedVariable.value === value) {
		return
	}

	try {
		navigator.clipboard.writeText(value)
	} catch {
		prompt('', value)
	}

	copiedVariable.value = value
	setTimeout(() => {
		if (copiedVariable.value === value) {
			copiedVariable.value = null
		}
	}, 2000)
}

async function onChangeBackground(event: Event): Promise<void> {
	const target = event.target
	const file = target instanceof HTMLInputElement ? target.files?.[0] : undefined
	if (!file) return

	const formData = new FormData()
	formData.append('image', file)

	showLoading.value = true
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

.ste__group {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
}

.ste__label-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.35rem;
}

.ste__label {
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.ste__label--sm {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
}

/* Segmented control */
.ste__seg {
	display: inline-flex;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-background-dark);
}

.ste__seg--modes {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	width: 100%;
}

.ste__seg--background {
	display: inline-flex;
}

.ste__seg-btn {
	flex: 1;
	padding: 0.35rem 0.75rem;
	border: none;
	background: transparent;
	font-size: 0.84rem;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	transition: background 100ms, color 100ms;
}

.ste__seg--modes .ste__seg-btn {
	padding: 0.42rem 0.45rem;
	font-size: 0.76rem;
	line-height: 1.15;
	min-height: 2.15rem;
	text-align: center;
	white-space: normal;
	overflow-wrap: anywhere;
}

.ste__seg-btn + .ste__seg-btn {
	border-left: 1px solid var(--color-border);
}

.ste__seg--modes .ste__seg-btn + .ste__seg-btn {
	border-left: 1px solid var(--color-border);
}

.ste__seg-btn--active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.ste__seg-btn:not(.ste__seg-btn--active):hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

/* Textarea */
.ste__textarea {
	width: 100%;
	min-height: 9rem;
	resize: vertical;
	padding: 0.65rem 0.8rem;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: 0.88rem;
	line-height: 1.55;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.ste__textarea:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

/* Variables dialog */
.ste__vars-dialog {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.ste__vars-description {
	margin: 0;
	font-size: 0.84rem;
	color: var(--color-text-maxcontrast);
}

.ste__vars-list {
	display: flex;
	flex-direction: column;
	gap: 0.45rem;
}

.ste__variable-description {
	margin: 0;
	font-size: 0.8rem;
	line-height: 1.35;
	color: var(--color-text-maxcontrast);
}

/* Dimensions row */
.ste__dims {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 0.6rem;
	flex-direction: unset;
}

.ste__field {
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
}

.ste__input-unit {
	display: flex;
	align-items: center;
	gap: 0.3rem;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-main-background);
}

.ste__num-input {
	flex: 1;
	min-width: 0;
	width: 100%;
	padding: 0.45rem 0.5rem;
	border: none;
	background: transparent;
	font-size: 0.88rem;
	color: var(--color-main-text);
}

.ste__num-input:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

.ste__unit {
	padding: 0 0.45rem 0 0;
	font-size: 0.76rem;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

/* Background row */
.ste__bg-row {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
}

.ste__file-input {
	display: none;
}

/* Preview section */
.ste__preview-section {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	margin-top: 0.4rem;
}

.ste__preview-header {
	display: flex;
	align-items: center;
	gap: 0.75rem;
}

.ste__preview-meta {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
	margin-left: auto;
}

.ste__zoom {
	display: flex;
	align-items: center;
	gap: 0.3rem;
	font-size: 0.82rem;
}

.ste__zoom-btn {
	min-width: 2rem;
	height: 2rem;
}

.ste__zoom-val {
	min-width: 3rem;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.ste__preview-stage {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 120px;
	padding: 1rem;
	overflow: auto;
	border-radius: 12px;
	background: repeating-conic-gradient(
		color-mix(in srgb, var(--color-border) 50%, transparent) 0% 25%,
		var(--color-main-background) 0% 50%
	) 0 0 / 16px 16px;
	border: 1px solid var(--color-border);
}

.ste__preview-frame {
	overflow: hidden;
	border: none;
	border-radius: 12px;
	background: transparent;
	box-shadow: none;
	transition: width 200ms ease, height 200ms ease;
}

.ste__preview-pdf {
	display: block;
}

.ste__preview-pdf :deep(.pdf-elements-root) {
	overflow: hidden;
	background: transparent;
}

.ste__preview-pdf :deep(.pages-container) {
	padding: 0;
	background: transparent;
}

.ste__preview-pdf :deep(.page-canvas) {
	box-shadow: none;
}

.ste__preview-placeholder {
	width: 100%;
	height: 100%;
	min-width: 120px;
	min-height: 60px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.ste__preview-placeholder--empty {
	font-size: 0.82rem;
	color: var(--color-text-maxcontrast);
	padding: 1rem;
}

@media (max-width: 640px) {
	.ste__seg--modes {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.ste__dims {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}
</style>
