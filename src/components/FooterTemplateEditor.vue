<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="footer-template-section">
		<div class="footer-template-header">
			<NcButton type="tertiary"
				:aria-label="t('libresign', 'Show available variables')"
				@click="showVariablesDialog = true">
				<template #icon>
					<HelpCircleOutline :size="20" />
				</template>
				{{ t('libresign', 'Available variables') }}
			</NcButton>
		</div>
		<CodeEditor
			v-model="footerTemplate"
			:label="t('libresign', 'Footer template')"
			:placeholder="t('libresign', 'A twig template to be used at footer of PDF. Will be rendered by mPDF.')"
			@input="debouncedSaveFooterTemplate" />
		<div v-if="pdfPreviewFile" class="footer-preview">
			<h4>{{ t('libresign', 'Preview') }}</h4>
			<div class="footer-preview__controls">
				<div class="footer-preview__zoom-controls">
					<NcButton :aria-label="t('libresign', 'Decrease zoom level')"
						@click="changeZoomLevel(-10)">
						<template #icon>
							<MagnifyMinusOutline :size="20" />
						</template>
					</NcButton>
					<NcButton :aria-label="t('libresign', 'Increase zoom level')"
						@click="changeZoomLevel(+10)">
						<template #icon>
							<MagnifyPlusOutline :size="20" />
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
						type="tertiary"
						@click="resetDimensions">
						<template #icon>
							<Undo :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
			<div ref="pdfContainer" class="footer-preview__pdf" :style="containerHeight ? `min-height: ${containerHeight}px` : ''">
				<div v-if="loadingPreview" class="footer-preview__loading">
					<NcLoadingIcon :size="64" />
				</div>
				<VuePdfEditor ref="pdfPreview"
					:key="pdfKey"
					:show-choose-file-btn="false"
					:show-customize-editor="false"
					:show-line-size-select="false"
					:show-font-size-select="false"
					:show-font-select="false"
					:show-rename="false"
					:show-save-btn="false"
					:save-to-upload="false"
					:init-file="pdfPreviewFile"
					:initial-scale="zoomLevel / 100"
					@scale-changed="onScaleChanged"
					@pdf-editor:ready="onPdfReady" />
			</div>
		</div>

		<NcDialog :name="t('libresign', 'Available template variables')"
			:open.sync="showVariablesDialog"
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
						<Check v-if="isCopied(name)" :size="20" />
						<ContentCopy v-else :size="20" />
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

<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
// eslint-disable-next-line import/default
import VuePdfEditor from '@libresign/vue-pdf-editor'

import CodeEditor from './CodeEditor.vue'

import Check from 'vue-material-design-icons/Check.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import HelpCircleOutline from 'vue-material-design-icons/HelpCircleOutline.vue'
import MagnifyMinusOutline from 'vue-material-design-icons/MagnifyMinusOutline.vue'
import MagnifyPlusOutline from 'vue-material-design-icons/MagnifyPlusOutline.vue'
import Undo from 'vue-material-design-icons/UndoVariant.vue'

export default {
	name: 'FooterTemplateEditor',
	components: {
		Check,
		CodeEditor,
		ContentCopy,
		HelpCircleOutline,
		NcButton,
		NcDialog,
		NcFormBoxButton,
		NcLoadingIcon,
		NcTextField,
		VuePdfEditor,
		MagnifyMinusOutline,
		MagnifyPlusOutline,
		Undo,
	},
	data() {
		const DEFAULT_PREVIEW_WIDTH = 595
		const DEFAULT_PREVIEW_HEIGHT = 100
		return {
			DEFAULT_PREVIEW_WIDTH,
			DEFAULT_PREVIEW_HEIGHT,
			footerTemplate: '',
			pdfPreviewFile: null,
			loadingPreview: false,
			pdfKey: 0,
			zoomLevel: loadState('libresign', 'footer_preview_zoom_level', 100),
			previewWidth: DEFAULT_PREVIEW_WIDTH,
			previewHeight: DEFAULT_PREVIEW_HEIGHT,
			containerHeight: null,
			showVariablesDialog: false,
			templateVariables: loadState('libresign', 'footer_template_variables', {}),
			copiedVariable: null,
		}
	},
	computed: {
		showResetDimensions() {
			return Number(this.previewWidth) !== this.DEFAULT_PREVIEW_WIDTH || Number(this.previewHeight) !== this.DEFAULT_PREVIEW_HEIGHT
		},
	},
	created() {
		this.debouncedSaveFooterTemplate = debounce(this.saveFooterTemplate, 500)
		this.debouncedUpdateScale = debounce(this.updateScale, 300)
		this.debouncedSaveDimensions = debounce(this.saveDimensions, 500)
	},
	mounted() {
		axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'))
			.then(response => {
				this.footerTemplate = response.data.ocs.data.template
				this.previewHeight = response.data.ocs.data.preview_height
				this.previewWidth = response.data.ocs.data.preview_width
			})
	},
	methods: {
		getVariableText(name) {
			return `{{ ${name} }}`
		},
		isCopied(name) {
			return this.copiedVariable === this.getVariableText(name)
		},
		copyToClipboard(text) {
			if (this.copiedVariable === text) {
				return
			}

			const value = text
			try {
				navigator.clipboard.writeText(value)
			} catch {
				// Fallback for a case when clipboard API is not available or permission denied
				// eslint-disable-next-line no-alert
				prompt('', value)
			}

			this.copiedVariable = text
			setTimeout(() => {
				this.copiedVariable = null
			}, 2000)
		},
		async resetFooterTemplate() {
			this.$emit('template-reset')
			this.resetDimensions()
			axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'))
		},
		resetDimensions() {
			this.previewWidth = this.DEFAULT_PREVIEW_WIDTH
			this.previewHeight = this.DEFAULT_PREVIEW_HEIGHT
			OCP.AppConfig.deleteKey('libresign', 'footer_preview_width')
			OCP.AppConfig.deleteKey('libresign', 'footer_preview_height')
		},
		saveDimensions() {
			if (Number(this.previewWidth) === this.DEFAULT_PREVIEW_WIDTH && Number(this.previewHeight) === this.DEFAULT_PREVIEW_HEIGHT) {
				OCP.AppConfig.deleteKey('libresign', 'footer_preview_width')
				OCP.AppConfig.deleteKey('libresign', 'footer_preview_height')
			} else {
				OCP.AppConfig.setValue('libresign', 'footer_preview_width', this.previewWidth)
				OCP.AppConfig.setValue('libresign', 'footer_preview_height', this.previewHeight)
			}
			this.saveFooterTemplate()
		},
		saveFooterTemplate() {
			axios.post(
				generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'),
				{
					template: this.footerTemplate,
					width: Number(this.previewWidth),
					height: Number(this.previewHeight),
				},
				{ responseType: 'blob' }
			).then(response => {
				this.setPdfPreview(response.data)
			}).catch(error => {
				console.error('Error saving footer template:', error)
			})
		},
		setPdfPreview(blob) {
			this.loadingPreview = true

			if (this.$refs.pdfContainer) {
				this.containerHeight = this.$refs.pdfContainer.offsetHeight
			}

			this.$nextTick(() => {
				const timestamp = Date.now()
				const pdfFile = new File([blob], `footer-preview-${timestamp}.pdf`, { type: 'application/pdf' })
				this.pdfPreviewFile = pdfFile
				this.pdfKey++
			})
		},
		onPdfReady() {
			this.loadingPreview = false
			this.containerHeight = null
		},
		changeZoomLevel(delta) {
			this.zoomLevel = Number(this.zoomLevel) + delta
			this.updateScale()
		},
		onZoomInput() {
			this.debouncedUpdateScale()
		},
		updateScale() {
			if (this.$refs.pdfPreview) {
				this.$refs.pdfPreview.scale = this.zoomLevel / 100
			}
		},
		onScaleChanged(newScale) {
			this.zoomLevel = Math.round(newScale * 100)
			OCP.AppConfig.setValue('libresign', 'footer_preview_zoom_level', this.zoomLevel)
		},
	},
}
</script>

<style lang="scss" scoped>
.footer-template-section {
	display: flex;
	flex-direction: column;
	gap: 16px;

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
		left: 0;
		right: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: rgba(255, 255, 255, 0.8);
		z-index: 100;
	}
}
</style>

<style>
/** @todo remove this, only necessary because VuePdfEditor use Tailwind and the Tailwind have a global CSS that affect this */
audio, canvas, embed, iframe, img, object, svg, video {
	display: unset;
}

canvas {
	border-bottom: 2px solid #eee;
}
</style>
