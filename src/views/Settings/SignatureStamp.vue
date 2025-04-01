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
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Descriptin only') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="GRAPHIC_AND_DESCRIPTION"
				name="render_mode"
				type="radio"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Signature and description') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="renderMode"
				value="GRAPHIC_ONLY"
				name="render_mode"
				type="radio"
				@update:modelValue="saveTemplate">
				{{ t('libresign', 'Signature only') }}
			</NcCheckboxRadioSwitch>
			<NcButton v-if="showResetRenderMode"
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
					:success="showSuccessTemplate"
					resize="vertical"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate"
					@mousemove="resizeHeight"
					@keypress="resizeHeight" />
				<NcButton v-if="showResetTemplate"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetTemplate">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="settings-section__row">
				<NcTextField :value.sync="fontSize"
					:label="t('libresign', 'Font size')"
					:placeholder="t('libresign', 'Font size')"
					type="number"
					:min="0.1"
					:max="30"
					:step="0.01"
					:spellcheck="false"
					:success="showSuccessTemplate"
					@keydown.enter="saveTemplate"
					@blur="saveTemplate" />
				<NcButton v-if="showResetFontSize"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetFontSize">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessageTemplate"
				type="error"
				:show-alert="true">
				<p>{{ errorMessageTemplate }}</p>
			</NcNoteCard>
		</div>
		<fieldset class="settings-section__row">
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
			<NcButton v-if="showResetBackground"
				type="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="undoBackground">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="showRemoveBackground"
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
		</fieldset>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessageBackground"
				type="error"
				:show-alert="true">
				<p>{{ errorMessageBackground }}</p>
			</NcNoteCard>
			<NcNoteCard v-if="wasScalled"
				type="info"
				:show-alert="true">
				<p>{{ t('libresign', 'The signature background image was resized to fit within 350×100 pixels.') }}</p>
			</NcNoteCard>
		</div>
		<div class="settings-section__row">
			<div class="settings-section__preview"
				:style="{
					'background-image': 'url(' + backgroundUrl + ')',
					'border-color': isOverflowing ? 'var(--color-error) !important': '',
				}">
				<div class="left-column" :style="{display: renderMode === 'DESCRIPTION_ONLY' ? 'none' : ''}">
					<div class="left-column-content"
						:style="{
							width: (renderMode === 'GRAPHIC_ONLY' || !parsedWithLineBreak ? '350' : '175') + 'px',
							height: (renderMode === 'GRAPHIC_ONLY' || !parsedWithLineBreak ? '100' : '50') + 'px',
							'justify-content': renderMode === 'GRAPHIC_ONLY' || !parsedWithLineBreak? 'center': 'right',
						}">
						<!-- TRANSLATORS Placeholder to indicate signature location in preview -->
						{{ t('libresign', 'Signature here') }}
					</div>
				</div>
				<!-- eslint-disable vue/no-v-html -->
				<div class="right-column"
					ref="rightColumn"
					@resize="checkPreviewOverflow"
					:style="{
						'font-size': (fontSize + 1) + 'pt',
						display: renderMode === 'GRAPHIC_ONLY' ? 'none' : '',
					}"
					v-html="parsedWithLineBreak" />
				<!-- eslint-enable vue/no-v-html -->
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import debounce from 'debounce'

import Delete from 'vue-material-design-icons/Delete.vue'
import Undo from 'vue-material-design-icons/UndoVariant.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
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

export default {
	name: 'SignatureStamp',
	components: {
		Delete,
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
	data() {
		return {
			name: t('libresign', 'Signature stamp'),
			description: t('libresign', 'The signature stamp is the element '),
			showLoadingBackground: false,
			wasScalled: false,
			backgroundType: loadState('libresign', 'signature_background_type'),
			acceptMime: ['image/png'],
			errorMessageBackground: '',
			backgroundUrl: this.backgroundType !== 'default'
				? generateOcsUrl('/apps/libresign/api/v1/admin/signature-background')
				: '',
			defaultSignatureTextTemplate: loadState('libresign', 'default_signature_text_template'),
			defaultSignatureFontSize: loadState('libresign', 'default_signature_font_size'),
			signatureTextTemplate: loadState('libresign', 'signature_text_template'),
			fontSize: loadState('libresign', 'signature_font_size'),
			renderMode: loadState('libresign', 'signature_render_mode'),
			showSuccessTemplate: false,
			errorMessageTemplate: '',
			parsed: loadState('libresign', 'signature_text_parsed'),
			isRTLDirection: isRTL(),
			availableVariables: loadState('libresign', 'signature_available_variables'),
			isOverflowing: false,
		}
	},
	computed: {
		showResetBackground() {
			return this.backgroundType === 'custom' || this.backgroundType === 'deleted'
		},
		showRemoveBackground() {
			return this.backgroundType === 'custom' || this.backgroundType === 'default'
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
		showResetRenderMode() {
			return this.renderMode !== 'GRAPHIC_AND_DESCRIPTION'
		},
		showResetTemplate() {
			return this.signatureTextTemplate !== this.defaultSignatureTextTemplate
		},
		showResetFontSize() {
			return this.fontSize !== this.defaultSignatureFontSize
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
	mounted() {
		this.resizeHeight()
		this.checkPreviewOverflow()
	},
	methods: {
		reset() {
			this.showSuccess = false
			this.errorMessageBackground = ''
			this.wasScalled = false
		},
		handleSuccessBackground() {
			this.showSuccess = true
			setTimeout(() => { this.showSuccess = false }, 2000)
		},
		activateLocalFilePicker() {
			this.reset()
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
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
					this.wasScalled = data.ocs.data.wasScalled
					this.handleSuccessBackground()
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
					this.handleSuccessBackground()
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
					this.handleSuccessBackground()
				})
				.catch(({ response }) => {
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		checkPreviewOverflow() {
			const rightColumn = this.$refs.rightColumn;
			this.isOverflowing = rightColumn.scrollHeight > rightColumn.clientHeight
		},
		resizeHeight: debounce(function() {
			const wrapper = this.$refs.textareaEditor
			if (!wrapper) return

			const textarea = wrapper.$el.querySelector('textarea')

			textarea.style.height = 'auto'
			textarea.style.height = `${textarea.scrollHeight + 4}px`
		}, 100),
		async resetRenderMode() {
			this.renderMode = 'GRAPHIC_AND_DESCRIPTION'
			this.saveTemplate()
		},
		async resetTemplate() {
			this.signatureTextTemplate = this.defaultSignatureTextTemplate
			this.saveTemplate()
		},
		async resetFontSize() {
			this.fontSize = this.defaultSignatureFontSize
			this.saveTemplate()
		},
		async saveTemplate() {
			this.showSuccessTemplate = false
			this.errorMessage = ''
			this.resizeHeight()
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), {
				template: this.signatureTextTemplate,
				fontSize: this.fontSize,
				renderMode: this.renderMode,
			})
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
					this.checkPreviewOverflow()
					if (data.ocs.data.fontSize !== this.fontSize) {
						this.fontSize = data.ocs.data.fontSize
					}
					this.showSuccessTemplate = true
					setTimeout(() => { this.showSuccessTemplate = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data.error
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
	}
	&__loading-icon {
		width: 44px;
		height: 44px;
	}
	&__preview {
		width: 350px;
		height: 100px;
		background-size: initial;
		background-position: center;
		background-repeat: no-repeat;
		justify-content: space-between;
		// align-items: center;
		display: flex;
		text-align: center;
		margin-top: 10px;
		border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
		.left-column {
			display: flex;
			align-items: center;
			.left-column-content {
				border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: right;
			}
		}
		.right-column {
			flex: 1;
			text-align: left;
			line-height: 1;
			word-wrap: anywhere;
			overflow: hidden;
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
