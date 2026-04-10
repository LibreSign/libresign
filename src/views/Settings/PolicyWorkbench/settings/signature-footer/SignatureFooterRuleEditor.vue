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

			<div v-if="value.writeQrcodeOnFooter" class="signature-footer-rule-editor__field">
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

			<FooterTemplateEditor
				v-if="value.customizeFooterTemplate"
				ref="footerTemplateEditor"
				@template-reset="onTemplateReset" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import FooterTemplateEditor from '../../../../../components/FooterTemplateEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
	type SignatureFooterPolicyConfig,
} from './model'

defineOptions({
	name: 'SignatureFooterRuleEditor',
})

type FooterTemplateEditorInstance = {
	resetFooterTemplate: () => Promise<void> | void
}

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const footerTemplateEditor = ref<FooterTemplateEditorInstance | null>(null)

const value = computed<SignatureFooterPolicyConfig>(() => {
	return normalizeSignatureFooterPolicyConfig(props.modelValue)
})

function updateValue(partial: Partial<SignatureFooterPolicyConfig>) {
	const nextValue = {
		...value.value,
		...partial,
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

async function onCustomizeFooterTemplateChange(customizeFooterTemplate: boolean) {
	updateValue({ customizeFooterTemplate })
	if (!customizeFooterTemplate) {
		await footerTemplateEditor.value?.resetFooterTemplate()
	}
}

function onTemplateReset() {
	updateValue({ customizeFooterTemplate: false })
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
}
</style>
