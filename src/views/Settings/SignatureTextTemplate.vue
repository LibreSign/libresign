<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		{{ t('libresign', 'You can use the following variables in your signature text:') }}
		<ul class="available-variables">
			<li v-for="(availableDescription, availableName) in availableVariables"
				:key="availableName"
				:class="{rtl: isRTLDirection}">
				<strong :class="{rtl: isRTLDirection}">{{ availableName }}:</strong>
				<span>{{ availableDescription }}</span>
			</li>
		</ul>
		<div class="content">
			<div class="content__row">
				<TextEditor id="template"
					v-model="inputValue" />
				<!-- <NcTextArea ref="textareaEditor"
					:value.sync="inputValue"
					:label="t('libresign', 'Signature text template')"
					:placeholder="t('libresign', 'Signature text template')"
					:spellcheck="false"
					:success="showSuccess"
					resize="vertical"
					@keydown.enter="save"
					@blur="save"
					@mousemove="resizeHeight"
					@keypress="resizeHeight" /> -->
				<NcButton v-if="showResetTemplate"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetTemplate">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
			<div class="content__row">
				<NcTextField :value.sync="fontSize"
					:label="t('libresign', 'Font size')"
					:placeholder="t('libresign', 'Font size')"
					type="number"
					:min="0.1"
					:max="30"
					:step="0.01"
					:spellcheck="false"
					:success="showSuccess"
					@keydown.enter="save"
					@blur="save" />
				<NcButton v-if="showResetFontSize"
					type="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="resetFontSize">
					<template #icon>
						<Undo :size="20" />
					</template>
				</NcButton>
			</div>
			<NcNoteCard v-if="errorMessage"
				type="error"
				:show-alert="true">
				<p>{{ errorMessage }}</p>
			</NcNoteCard>
			<div class="text-pre-line">
				{{ parsed }}
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import TextEditor from '../../Components/TextEditor/TextEditor.vue'
import debounce from 'debounce'

import Undo from 'vue-material-design-icons/UndoVariant.vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { translate as t, isRTL } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'SignatureTextTemplate',
	components: {
		TextEditor,
		NcButton,
		NcNoteCard,
		NcSettingsSection,
		NcTextArea,
		NcTextField,
		Undo,
	},
	data() {
		return {
			name: t('libresign', 'Signature text template'),
			description: t('libresign', 'This template will be mixed to signature.'),
			defaultSignatureTextTemplate: '<p>' + loadState('libresign', 'default_signature_text_template').replace(/\n/g, '<br>') + '</p>',
			defaultSignatureFontSize: loadState('libresign', 'default_signature_font_size'),
			signatureTextTemplate: loadState('libresign', 'signature_text_template'),
			fontSize: loadState('libresign', 'signature_font_size'),
			showSuccess: false,
			errorMessage: '',
			parsed: loadState('libresign', 'signature_text_parsed'),
			isRTLDirection: isRTL(),
			availableVariables: loadState('libresign', 'signature_available_variables'),
		}
	},
	computed: {
		inputValue: {
			get() {
				return this.signatureTextTemplate
			},
			set(value) {
				this.signatureTextTemplate = value
				this.debouncePropertyChange()
			},
		},
		showResetTemplate() {
			return this.signatureTextTemplate !== this.defaultSignatureTextTemplate
		},
		showResetFontSize() {
			return this.fontSize !== this.defaultSignatureFontSize
		},
		debouncePropertyChange() {
			return debounce(async function() {
				await this.save()
			}, 1000)
		},
	},
	mounted() {
		this.resizeHeight()
	},
	methods: {
		resizeHeight: debounce(function() {
			const wrapper = this.$refs.textareaEditor
			if (!wrapper) return

			const textarea = wrapper.$el.querySelector('textarea')

			textarea.style.height = 'auto'
			textarea.style.height = `${textarea.scrollHeight + 4}px`
		}, 100),
		async resetTemplate() {
			this.signatureTextTemplate = this.defaultSignatureTextTemplate
			this.save()
		},
		async resetFontSize() {
			this.fontSize = this.defaultSignatureFontSize
			this.save()
		},
		async save() {
			this.showSuccess = false
			this.errorMessage = ''
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), {
				template: this.signatureTextTemplate,
				fontSize: this.fontSize,
			})
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
					if (data.ocs.data.fontSize !== this.fontSize) {
						this.fontSize = data.ocs.data.fontSize
					}
					this.showSuccess = true
					setTimeout(() => { this.showSuccess = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data.error
					this.parsed = ''
				})
		},
	},
}
</script>
<style lang="scss" scoped>
.content{
	display: flex;
	flex-direction: column;
	&__row {
		display: flex;
		gap: 0 4px;
	}

	#template {
		width: 100%;
		min-height: 100px;
		border: 1px solid var(--color-border);

		&:active,
		&:focus,
		&:hover {
			border-color: var(--color-primary-element) !important;
		}
	}
}
.text-pre-line {
	white-space: pre-line;
}

.available-variables {
	margin-bottom: 1em;
}

.rtl {
	direction: rtl;
	text-align: right;
}
</style>
