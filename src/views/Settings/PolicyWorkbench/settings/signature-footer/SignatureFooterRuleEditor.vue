<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-footer-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="value.enabled"
			@update:modelValue="onEnabledChange">
			<span>{{ t('libresign', 'Add visible footer with signature details') }}</span>
		</NcCheckboxRadioSwitch>

		<div v-if="value.enabled" class="signature-footer-rule-editor__nested">
			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="value.writeQrcodeOnFooter"
				@update:modelValue="onWriteQrcodeChange">
				<span>{{ t('libresign', 'Write QR code on footer with validation URL') }}</span>
			</NcCheckboxRadioSwitch>

			<div v-if="value.writeQrcodeOnFooter && canEditValidationSite" class="signature-footer-rule-editor__field">
				<p class="signature-footer-rule-editor__hint">
					{{ t('libresign', 'To validate the signature of the documents. Only change this value if you want to replace the default validation URL with a different one.') }}
				</p>
				<NcTextField
					:model-value="value.validationSite"
					:label="t('libresign', 'Validation URL')"
					:placeholder="t('libresign', 'Use instance default validation URL')"
					@update:modelValue="onValidationSiteChange" />
			</div>

			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="value.customizeFooterTemplate"
				@update:modelValue="onCustomizeFooterTemplateChange">
				<span>{{ t('libresign', 'Customize footer template') }}</span>
			</NcCheckboxRadioSwitch>

			<div v-if="value.customizeFooterTemplate" class="signature-footer-rule-editor__field">
				<CodeEditor
					:model-value="value.footerTemplate"
					:label="t('libresign', 'Footer template')"
					:placeholder="t('libresign', 'A twig template to be used at footer of PDF. Will be rendered by mPDF.')"
					@update:modelValue="onFooterTemplateChange">
					<template #label-actions>
						<NcButton
							v-if="props.showTemplateResetButton !== false && showResetTemplateButton"
							variant="tertiary"
							:aria-label="t('libresign', 'Reset template to inherited default')"
							@click="onTemplateReset">
							<template #icon>
								<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
							</template>
						</NcButton>
					</template>
				</CodeEditor>

				<div v-if="props.showPreview !== false" class="signature-footer-rule-editor__preview">
					<p class="signature-footer-rule-editor__preview-title">{{ t('libresign', 'Preview') }}</p>
					<div class="signature-footer-rule-editor__preview-controls">
						<NcButton
							variant="tertiary"
							:aria-label="t('libresign', 'Decrease zoom level')"
							@click="changeZoom(-10)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiMagnifyMinusOutline" :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('libresign', 'Increase zoom level')"
							@click="changeZoom(10)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiMagnifyPlusOutline" :size="20" />
							</template>
						</NcButton>
						<div class="signature-footer-rule-editor__zoom-field">
							<NcTextField
								:model-value="String(previewZoom)"
								:label="t('libresign', 'Zoom level')"
								type="number"
								:min="10"
								:step="10"
								@update:modelValue="onPreviewZoomChange" />
						</div>
						<div class="signature-footer-rule-editor__size-field">
							<NcTextField
								:model-value="String(previewWidth)"
								:label="t('libresign', 'Width')"
								type="number"
								:min="100"
								:max="2000"
								@update:modelValue="onPreviewWidthChange" />
						</div>
						<div class="signature-footer-rule-editor__size-field">
							<NcTextField
								:model-value="String(previewHeight)"
								:label="t('libresign', 'Height')"
								type="number"
								:min="10"
								:max="500"
								@update:modelValue="onPreviewHeightChange" />
						</div>
						<NcButton
							v-if="hasCustomPreviewSize"
							variant="tertiary"
							:aria-label="t('libresign', 'Reset dimensions')"
							@click="resetPreviewSize">
							<template #icon>
								<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
							</template>
						</NcButton>
					</div>

					<PDFElements
						v-if="pdfPreviewFile"
						:key="previewRenderKey"
						class="signature-footer-rule-editor__preview-frame"
						:style="{ height: `${previewViewportHeight}px` }"
						:init-files="[pdfPreviewFile]"
						:init-file-names="['footer-preview.pdf']"
						:initial-scale="previewZoom / 100"
						:show-page-footer="false" />
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { mdiMagnifyMinusOutline, mdiMagnifyPlusOutline, mdiUndoVariant } from '@mdi/js'
import PDFElements from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import CodeEditor from '../../../../../components/CodeEditor.vue'
import { ensurePdfWorker } from '../../../../../helpers/pdfWorker'
import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
	type SignatureFooterPolicyConfig,
} from './model'

defineOptions({
	name: 'SignatureFooterRuleEditor',
})

const props = withDefaults(defineProps<{
	modelValue: EffectivePolicyValue
	inheritedTemplate?: string
	editorScope?: 'system' | 'group' | 'user'
	allowValidationSiteOverrideInUserScope?: boolean
	showTemplateResetButton?: boolean
	showPreview?: boolean
}>(), {
	showTemplateResetButton: true,
	showPreview: true,
})

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
	'template-changed': []
}>()

ensurePdfWorker()

const pdfPreviewFile = ref<File | null>(null)
const pdfPreviewKey = ref(0)
let previewRequestId = 0

const DEFAULT_PREVIEW_WIDTH = 595
const DEFAULT_PREVIEW_HEIGHT = 100
const DEFAULT_PREVIEW_ZOOM = 100

const value = computed<SignatureFooterPolicyConfig>(() => {
	const normalized = normalizeSignatureFooterPolicyConfig(props.modelValue)
	if (normalized.customizeFooterTemplate && !normalized.footerTemplate && props.inheritedTemplate) {
		normalized.footerTemplate = props.inheritedTemplate
	}
	return normalized
})

const previewWidth = computed(() => clampNumber(value.value.previewWidth, DEFAULT_PREVIEW_WIDTH, 100, 2000))
const previewHeight = computed(() => clampNumber(value.value.previewHeight, DEFAULT_PREVIEW_HEIGHT, 10, 500))
const previewZoom = computed(() => clampNumber(value.value.previewZoom, DEFAULT_PREVIEW_ZOOM, 10, 300))
const previewViewportHeight = computed(() => {
	const scaledHeight = Math.round((previewHeight.value * previewZoom.value) / 100)
	return Math.max(90, scaledHeight + 28)
})
const hasCustomPreviewSize = computed(() => previewWidth.value !== DEFAULT_PREVIEW_WIDTH || previewHeight.value !== DEFAULT_PREVIEW_HEIGHT)
const previewRenderKey = computed(() => `${pdfPreviewKey.value}-${previewZoom.value}`)

const showResetTemplateButton = computed(() => {
	const raw = normalizeSignatureFooterPolicyConfig(props.modelValue)
	if (!raw.customizeFooterTemplate) {
		return false
	}

	const currentTemplate = raw.footerTemplate.trim()
	if (currentTemplate.length === 0) {
		return false
	}

	const inheritedTemplate = (props.inheritedTemplate ?? '').trim()
	return currentTemplate !== inheritedTemplate
})

const canEditValidationSite = computed(() => {
	if (props.editorScope !== 'user') {
		return true
	}

	return props.allowValidationSiteOverrideInUserScope === true
})

function updateValue(partial: Partial<SignatureFooterPolicyConfig>) {
	const nextValue = {
		...value.value,
		...partial,
	}

	if (!canEditValidationSite.value) {
		nextValue.validationSite = ''
	}

	emit('update:modelValue', serializeSignatureFooterPolicyConfig(nextValue))
}

function onEnabledChange(enabled: boolean) {
	if (!enabled) {
		updateValue({
			enabled,
			writeQrcodeOnFooter: false,
		})
		return
	}

	updateValue({ enabled })
}

function onWriteQrcodeChange(writeQrcodeOnFooter: boolean) {
	updateValue({ writeQrcodeOnFooter })
}

function onValidationSiteChange(validationSite: string | number) {
	updateValue({ validationSite: String(validationSite).trim() })
}

function onCustomizeFooterTemplateChange(customizeFooterTemplate: boolean) {
	if (!customizeFooterTemplate) {
		updateValue({
			customizeFooterTemplate,
			footerTemplate: '',
		})
		onTemplateChanged()
		return
	}

	updateValue({ customizeFooterTemplate })
}

function onFooterTemplateChange(footerTemplate: string | number) {
	updateValue({ footerTemplate: String(footerTemplate) })
	onTemplateChanged()
}

function onTemplateChanged() {
	emit('template-changed')
}

function onTemplateReset(event?: Event) {
	event?.stopPropagation()
	event?.preventDefault()

	updateValue({
		customizeFooterTemplate: true,
		footerTemplate: '',
	})
	onTemplateChanged()
}

function clearPreview() {
	pdfPreviewFile.value = null
}

function clampNumber(value: unknown, fallback: number, min: number, max: number): number {
	const parsed = Number.parseInt(String(value), 10)
	if (!Number.isFinite(parsed)) {
		return fallback
	}

	return Math.max(min, Math.min(max, parsed))
}

function resolvePreviewTemplate(config: SignatureFooterPolicyConfig): string {
	return config.footerTemplate || props.inheritedTemplate || ''
}

function setPreviewBlob(blob: Blob) {
	const timestamp = Date.now()
	pdfPreviewFile.value = new File([blob], `footer-preview-${timestamp}.pdf`, { type: 'application/pdf' })
	pdfPreviewKey.value += 1
}

async function updatePreview(template: string): Promise<void> {
	const requestId = ++previewRequestId
	try {
		const response = await axios.post(
			generateOcsUrl('/apps/libresign/api/v1/admin/footer-template/preview-pdf'),
			{
				template,
				width: previewWidth.value,
				height: previewHeight.value,
				writeQrcodeOnFooter: value.value.writeQrcodeOnFooter,
			},
			{ responseType: 'blob' },
		)

		if (requestId !== previewRequestId) {
			return
		}

		setPreviewBlob(response.data as Blob)
	} catch {
		// Ignore preview failures to avoid breaking editing flow.
	}
}

watch(value, (nextValue) => {
	if (props.showPreview === false) {
		clearPreview()
		return
	}

	if (!nextValue.customizeFooterTemplate) {
		clearPreview()
		return
	}

	void updatePreview(resolvePreviewTemplate(nextValue))
}, { immediate: true })

function onPreviewWidthChange(value: string | number) {
	updateValue({ previewWidth: clampNumber(value, DEFAULT_PREVIEW_WIDTH, 100, 2000) })
	onTemplateChanged()
}

function onPreviewHeightChange(value: string | number) {
	updateValue({ previewHeight: clampNumber(value, DEFAULT_PREVIEW_HEIGHT, 10, 500) })
	onTemplateChanged()
}

function onPreviewZoomChange(value: string | number) {
	updateValue({ previewZoom: clampNumber(value, DEFAULT_PREVIEW_ZOOM, 10, 300) })
	onTemplateChanged()
}

function changeZoom(delta: number) {
	onPreviewZoomChange(previewZoom.value + delta)
}

function resetPreviewSize() {
	updateValue({
		previewWidth: DEFAULT_PREVIEW_WIDTH,
		previewHeight: DEFAULT_PREVIEW_HEIGHT,
	})
	onTemplateChanged()
}
</script>

<style scoped lang="scss">
.signature-footer-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.9rem;

	&__nested {
		display: flex;
		flex-direction: column;
		gap: 0.9rem;
		padding-inline-start: 0.45rem;
		border-inline-start: 2px solid var(--color-border-maxcontrast);
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__hint {
		margin: 0;
		font-size: 0.9rem;
		color: var(--color-text-maxcontrast);
	}

	&__preview {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__preview-controls {
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		gap: 0.4rem;
		align-items: end;
	}

	&__zoom-field {
		width: 120px;
	}

	&__size-field {
		width: 120px;
	}

	&__preview-title {
		margin: 0;
		font-weight: 600;
	}

	&__preview-frame {
		width: 100%;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-element, 8px);
		background: #f7fafc;
	}
}
</style>
