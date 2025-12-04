<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="footer-template-section">
		<div class="footer-template-header">
			<label>{{ t('libresign', 'Footer template') }}</label>
			<NcButton v-if="!isDefaultFooterTemplate"
				type="tertiary"
				@click="resetFooterTemplate">
				{{ t('libresign', 'Reset to default') }}
			</NcButton>
		</div>
		<NcTextArea ref="textareaEditor"
			v-model="footerTemplate"
			label=""
			placeholder="A twig template to be used at footer of PDF. Will be rendered by mPDF."
			resize="vertical"
			@input="debouncedSaveFooterTemplate"
			@mousemove="resizeHeight"
			@keypress="resizeHeight" />
		<div v-if="pdfPreviewFile" class="footer-preview">
			<h4>{{ t('libresign', 'Preview') }}</h4>
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
			<div ref="pdfContainer" class="footer-preview__pdf" :style="previewHeight ? `min-height: ${previewHeight}px` : ''">
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
	</div>
</template>

<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
// eslint-disable-next-line import/default
import VuePdfEditor from '@libresign/vue-pdf-editor'

import MagnifyMinusOutline from 'vue-material-design-icons/MagnifyMinusOutline.vue'
import MagnifyPlusOutline from 'vue-material-design-icons/MagnifyPlusOutline.vue'

export default {
	name: 'FooterTemplateEditor',
	components: {
		NcButton,
		NcLoadingIcon,
		NcTextArea,
		NcTextField,
		VuePdfEditor,
		MagnifyMinusOutline,
		MagnifyPlusOutline,
	},
	props: {
		initialTemplate: {
			type: String,
			default: '',
		},
		initialIsDefault: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			footerTemplate: this.initialTemplate,
			isDefaultFooterTemplate: this.initialIsDefault,
			pdfPreviewFile: null,
			loadingPreview: false,
			pdfKey: 0,
			zoomLevel: loadState('libresign', 'footer_preview_zoom_level', 100),
			previewHeight: null,
		}
	},
	watch: {
		initialTemplate(newValue) {
			this.footerTemplate = newValue
			if (newValue) {
				this.saveFooterTemplate()
			}
		},
		initialIsDefault(newValue) {
			this.isDefaultFooterTemplate = newValue
		},
	},
	created() {
		this.debouncedSaveFooterTemplate = debounce(this.saveFooterTemplate, 500)
		this.debouncedUpdateScale = debounce(this.updateScale, 300)
	},
	mounted() {
		this.resizeHeight()
		this.saveFooterTemplate()
	},
	methods: {
		async resetFooterTemplate() {
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'))
			this.$emit('template-reset')
		},
		saveFooterTemplate() {
			axios.post(
				generateOcsUrl('/apps/libresign/api/v1/admin/footer-template'),
				{ template: this.footerTemplate, width: 595, height: 80 },
				{ responseType: 'blob' }
			).then(response => {
				this.isDefaultFooterTemplate = false
				this.setPdfPreview(response.data)
				this.$emit('template-saved', this.footerTemplate)
			}).catch(error => {
				console.error('Error saving footer template:', error)
			})
		},
		setPdfPreview(blob) {
			this.loadingPreview = true

			if (this.$refs.pdfContainer) {
				this.previewHeight = this.$refs.pdfContainer.offsetHeight
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
			this.previewHeight = null
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

		resizeHeight: debounce(function() {
			const wrapper = this.$refs.textareaEditor
			if (!wrapper || !wrapper.$el) {
				return
			}
			const mainWrapper = wrapper.$el.querySelector('.textarea__main-wrapper')
			const textarea = wrapper.$el.querySelector('textarea')
			if (mainWrapper && textarea) {
				mainWrapper.style.height = 'auto'
				mainWrapper.style.height = `${textarea.scrollHeight + 4}px`
			}
		}, 100),
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
		justify-content: space-between;
		align-items: center;

		label {
			font-weight: bold;
			font-size: 14px;
		}
	}

	:deep(.textarea) {
		textarea {
			height: 100%;
		}
	}
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

	&__zoom-controls {
		display: flex;
		gap: 8px;
		align-items: center;
		margin-bottom: 10px;
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
