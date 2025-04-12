<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
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
				type="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="resetRenderMode">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
		</fieldset>
		<div v-if="renderMode !== 'GRAPHIC_ONLY'">
			<div class="settings-section__row">
				<ul class="available-variables">
					<li v-for="(availableDescription, availableName) in availableVariables"
						:key="availableName"
						:class="{rtl: isRTLDirection}">
						<strong :class="{rtl: isRTLDirection}">{{ availableName }}:</strong>
						<span>{{ availableDescription }}</span>
					</li>
				</ul>
			</div>
			<div class="settings-section__row">
				<NcTextArea ref="textareaEditor"
					:value.sync="inputValue"
					:label="t('libresign', 'Signature text template')"
					:placeholder="t('libresign', 'Signature text template')"
					:spellcheck="false"
					:success="dislaySuccessTemplate"
					resize="vertical"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate"
					@mousemove="resizeHeight"
					@keypress="resizeHeight" />
				<NcButton v-if="displayResetTemplate"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetTemplate">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="settings-section__row">
				<div v-if="renderMode === 'SIGNAME_AND_DESCRIPTION'" class="settings-section__row_signature">
					<NcTextField :value.sync="signatureFontSize"
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
						type="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetSignatureFontSize">
						<template #icon>
							<Undo :size="20" />
						</template>
					</NcButton>
				</div>
				<div :class="{
					'settings-section__row_template': renderMode === 'SIGNAME_AND_DESCRIPTION',
					'settings-section__row_template-only': renderMode !== 'SIGNAME_AND_DESCRIPTION',
				}">
					<NcTextField :value.sync="templateFontSize"
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
						type="tertiary"
						:aria-label="t('libresign', 'Reset to default')"
						@click="resetTemplateFontSize">
						<template #icon>
							<Undo :size="20" />
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
				<NcTextField :value.sync="signatureWidth"
					:label="t('libresign', 'Default signature width')"
					:placeholder="t('libresign', 'Default signature width')"
					type="number"
					:min="0.1"
					:max="800"
					:step="0.01"
					:spellcheck="false"
					:success="dislaySuccessTemplate"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate" />
				<NcButton v-if="displayResetSignatureWidth"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetSignatureWidth">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="settings-section__row_dimension">
				<NcTextField :value.sync="signatureHeight"
					:label="t('libresign', 'Default signature height')"
					:placeholder="t('libresign', 'Default signature height')"
					type="number"
					:min="0.1"
					:max="800"
					:step="0.01"
					:spellcheck="false"
					:success="dislaySuccessTemplate"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate" />
				<NcButton v-if="displayResetSignatureHeight"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetSignatureHeight">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<fieldset class="settings-section__row settings-section__row_bar">
			<legend>{{ t('libresign', 'Background image') }}</legend>
			<NcButton id="signature-background"
				type="secondary"
				:aria-label="t('libresign', 'Upload new background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<Upload :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton v-if="displayResetBackground"
				type="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="undoBackground">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="displayRemoveBackground"
				type="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="removeBackground">
				<template #icon>
					<Delete :size="20" />
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
				<NcButton @click="changeZoomLevel(-10)">
					<template #icon>
						<MagnifyMinusOutline :size="20" />
					</template>
				</NcButton>
				<NcButton @click="changeZoomLevel(+10)">
					<template #icon>
						<MagnifyPlusOutline :size="20" />
					</template>
				</NcButton>
			</div>
			<NcTextField v-if="displayPreview"
				:value.sync="zoomLevel"
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
	</NcSettingsSection>
</template>
<script>
import debounce from 'debounce'

import Delete from 'vue-material-design-icons/Delete.vue'
import MagnifyMinusOutline from 'vue-material-design-icons/MagnifyMinusOutline.vue'
import MagnifyPlusOutline from 'vue-material-design-icons/MagnifyPlusOutline.vue'
import Undo from 'vue-material-design-icons/UndoVariant.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { translate as t, isRTL } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'

export default {
	name: 'SignatureStamp',
	components: {
		Delete,
		MagnifyMinusOutline,
		MagnifyPlusOutline,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcNoteCard,
		NcSettingsSection,
		NcTextArea,
		NcTextField,
		Undo,
		Upload,
	},
	setup() {
		const isDarkTheme = useIsDarkTheme()
		return {
			isDarkTheme,
		}
	},
	data() {
		const templateError = loadState('libresign', 'signature_text_template_error', '')
		const backgroundType = loadState('libresign', 'signature_background_type')
		return {
			name: t('libresign', 'Signature stamp'),
			description: t('libresign', 'Configure the content displayed with the signature. The text template uses Twig syntax.'),
			showLoadingBackground: false,
			backgroundType,
			acceptMime: ['image/png'],
			errorMessageBackground: '',
			backgroundUrl: backgroundType !== 'deleted'
				? generateOcsUrl('/apps/libresign/api/v1/admin/signature-background')
				: '',
			defaultSignatureTextTemplate: loadState('libresign', 'default_signature_text_template'),
			defaultTemplateFontSize: loadState('libresign', 'default_template_font_size'),
			defaultSignatureFontSize: loadState('libresign', 'default_signature_font_size'),
			defaultSignatureWidth: loadState('libresign', 'default_signature_width'),
			defaultSignatureHeight: loadState('libresign', 'default_signature_height'),
			signatureTextTemplate: loadState('libresign', 'signature_text_template'),
			signatureWidth: loadState('libresign', 'signature_width'),
			signatureHeight: loadState('libresign', 'signature_height'),
			signatureFontSize: loadState('libresign', 'signature_font_size'),
			templateFontSize: loadState('libresign', 'template_font_size'),
			isSignatureImageLoaded: false,
			templateSaved: true,
			zoomLevel: loadState('libresign', 'signature_preview_zoom_level'),
			renderMode: loadState('libresign', 'signature_render_mode'),
			dislaySuccessTemplate: false,
			errorMessageTemplate: templateError ? [templateError] : [],
			parsed: loadState('libresign', 'signature_text_parsed'),
			isRTLDirection: isRTL(),
			availableVariables: loadState('libresign', 'signature_available_variables'),
			isOverflowing: false,
		}
	},
	computed: {
		displayResetBackground() {
			return this.backgroundType === 'custom' || this.backgroundType === 'deleted'
		},
		displayRemoveBackground() {
			return this.backgroundType === 'custom' || this.backgroundType === 'default'
		},
		displayPreview() {
			if (this.backgroundType !== 'deleted') {
				return true
			}
			if (this.renderMode === 'DESCRIPTION_ONLY' && !this.parsed) {
				return false
			}
			return true
		},
		inputValue: {
			get() {
				return this.signatureTextTemplate
			},
			set(value) {
				this.signatureTextTemplate = value
				this.debouncePropertyChange()
			},
		},
		displayResetRenderMode() {
			return this.renderMode !== 'GRAPHIC_AND_DESCRIPTION'
		},
		displayResetTemplate() {
			return this.signatureTextTemplate !== this.defaultSignatureTextTemplate
		},
		displayResetTemplateFontSize() {
			return this.templateFontSize !== this.defaultTemplateFontSize
		},
		dislayResetSignatureFontSize() {
			return this.signatureFontSize !== this.defaultSignatureFontSize
		},
		displayResetSignatureWidth() {
			return this.signatureWidth !== this.defaultSignatureWidth
		},
		displayResetSignatureHeight() {
			return this.signatureHeight !== this.defaultSignatureHeight
		},
		previewSignatureImageWidth() {
			return (this.renderMode === 'GRAPHIC_ONLY' || !this.parsedWithLineBreak)
				? this.signatureWidth
				: Math.floor(this.signatureWidth / 2)
		},
		previewSignatureImageHeight() {
			return this.signatureHeight
		},
		signatureImageUrl() {
			const text = this.renderMode === 'SIGNAME_AND_DESCRIPTION'
				? getCurrentUser()?.displayName ?? 'John Doe'
				: t('libresign', 'Signature image here')
			const align = this.renderMode === 'GRAPHIC_AND_DESCRIPTION' ? 'right' : 'center'
			const isDarkTheme = this.isDarkTheme ? 1 : 0

			return generateOcsUrl('/apps/libresign/api/v1/admin/signer-name')
				+ `?width=${this.previewSignatureImageWidth}`
				+ `&height=${this.previewSignatureImageHeight}`
				+ `&text=${encodeURIComponent(text)}`
				+ `&fontSize=${this.signatureFontSize}`
				+ `&isDarkTheme=${isDarkTheme}`
				+ `&align=${align}`
		},
		previewLoaded() {
			return this.isSignatureImageLoaded && !this.showLoadingBackground && this.templateSaved
		},
		debouncePropertyChange() {
			return debounce(async function() {
				await this.saveTemplate()
			}, 1000)
		},
		parsedWithLineBreak() {
			return this.parsed.replace(/\n/g, '<br>')
		},
	},
	watch: {
		signatureImageUrl() {
			this.isSignatureImageLoaded = false
		},
	},
	mounted() {
		this.resizeHeight()
		subscribe('collect-metadata:changed', this.refreshAfterChangeCollectMetadata)
	},
	beforeUnmount() {
		unsubscribe('collect-metadata:changed')
	},
	methods: {
		reset() {
			this.dislaySuccessTemplate = false
			this.errorMessageBackground = ''
			this.errorMessageTemplate = []
		},
		async refreshAfterChangeCollectMetadata() {
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/signature-settings'))
				.then(({ data }) => {
					this.availableVariables = data.ocs.data.signature_available_variables
					this.defaultSignatureTextTemplate = data.ocs.data.default_signature_text_template
				})
		},
		activateLocalFilePicker() {
			this.reset()
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},
		changeZoomLevel(zoom) {
			this.zoomLevel += zoom
			this.saveZoomLevel()
		},
		async saveZoomLevel() {
			OCP.AppConfig.setValue('libresign', 'signature_preview_zoom_level', this.zoomLevel)
		},
		async onChangeBackground(e) {
			const file = e.target.files[0]

			const formData = new FormData()
			formData.append('image', file)

			this.showLoadingBackground = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
				.then(({ data }) => {
					this.showLoadingBackground = false
					this.backgroundType = 'custom'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
				})
				.catch(({ response }) => {
					this.showLoadingBackground = false
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		async undoBackground() {
			this.reset()
			this.showLoadingBackground = true
			await axios.patch(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
			})
				.then(() => {
					this.showLoadingBackground = false
					this.backgroundType = 'default'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
				})
				.catch(({ response }) => {
					this.showLoadingBackground = false
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		async removeBackground() {
			this.reset()
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
				value: 'backgroundColor',
			})
				.then(() => {
					this.backgroundType = 'deleted'
					this.backgroundUrl = ''
				})
				.catch(({ response }) => {
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		checkPreviewOverflow() {
			const rightColumn = this.$refs.rightColumn
			if (!rightColumn) {
				return
			}
			this.isOverflowing = rightColumn.scrollHeight > rightColumn.clientHeight
			const overflowMessage = t('libresign', 'Signature template content is overflowing. Reduce the text.')
			if (this.isOverflowing && !this.errorMessageTemplate.includes(overflowMessage)) {
				this.errorMessageTemplate.push(overflowMessage)
			}
		},
		resizeHeight: debounce(function() {
			const wrapper = this.$refs.textareaEditor
			if (!wrapper) {
				return
			}
			const textarea = wrapper.$el.querySelector('textarea')
			textarea.style.height = 'auto'
			textarea.style.height = `${textarea.scrollHeight + 4}px`
			this.checkPreviewOverflow()
		}, 100),
		async resetRenderMode() {
			this.renderMode = 'GRAPHIC_AND_DESCRIPTION'
			await this.saveTemplate()
		},
		async resetTemplate() {
			this.signatureTextTemplate = this.defaultSignatureTextTemplate
			await this.saveTemplate()
		},
		async resetTemplateFontSize() {
			this.templateFontSize = this.defaultTemplateFontSize
			await this.saveTemplate()
		},
		async resetSignatureFontSize() {
			this.signatureFontSize = this.defaultSignatureFontSize
			await this.saveTemplate()
		},
		async resetSignatureWidth() {
			this.signatureWidth = this.defaultSignatureWidth
			await this.saveTemplate()
		},
		async resetSignatureHeight() {
			this.signatureHeight = this.defaultSignatureHeight
			await this.saveTemplate()
		},
		async saveTemplate() {
			this.reset()
			this.templateSaved = false
			this.resizeHeight()
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), {
				template: this.signatureTextTemplate,
				templateFontSize: this.templateFontSize,
				signatureFontSize: this.signatureFontSize,
				signatureWidth: this.signatureWidth,
				signatureHeight: this.signatureHeight,
				renderMode: this.renderMode,
			})
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
					this.checkPreviewOverflow()
					if (data.ocs.data.templateFontSize !== this.templateFontSize) {
						this.templateFontSize = data.ocs.data.templateFontSize
					}
					if (data.ocs.data.signatureFontSize !== this.signatureFontSize) {
						this.signatureFontSize = data.ocs.data.signatureFontSize
					}
					this.dislaySuccessTemplate = true
					this.templateSaved = true
					setTimeout(() => { this.dislaySuccessTemplate = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessageTemplate.push(response.data.ocs.data.error)
					this.parsed = ''
					this.checkPreviewOverflow()
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.settings-section{
	display: flex;
	flex-direction: column;
	&:deep(.settings-section__name) {
		justify-content: unset;
	}
	&__row {
		display: flex;
		gap: 0 4px;
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
	.available-variables {
		margin-bottom: 1em;
	}
	.rtl {
		direction: rtl;
		text-align: right;
	}
}
</style>
