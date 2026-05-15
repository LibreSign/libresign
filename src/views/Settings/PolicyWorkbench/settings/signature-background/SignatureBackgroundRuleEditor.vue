<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-background-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			class="signature-background-editor__option"
			type="radio"
			:model-value="normalizedValue === option.value"
			name="signature-background-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="signature-background-editor__copy">
				<strong>{{ option.label }}</strong>
				<p>{{ option.description }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<div class="signature-background-editor__actions">
			<NcButton
				variant="secondary"
				:aria-label="t('libresign', 'Upload new background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUpload" :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>

			<NcButton
				v-if="normalizedValue !== 'default'"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="() => setValue('default')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
				</template>
			</NcButton>

			<NcButton
				v-if="normalizedValue !== 'deleted'"
				variant="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="() => setValue('deleted')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>

			<NcLoadingIcon v-if="showLoading" :size="20" />

			<input
				ref="input"
				type="file"
				accept="image/png"
				@change="onChangeBackground">
		</div>

		<NcNoteCard v-if="errorMessage" type="error" :show-alert="true">
			<p>{{ errorMessage }}</p>
		</NcNoteCard>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { mdiDelete, mdiUndoVariant, mdiUpload } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import type { EffectivePolicyValue } from '../../../../../types/index'

defineOptions({
	name: 'SignatureBackgroundRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options: Array<{ value: 'default' | 'custom' | 'deleted'; label: string; description: string }> = [
	{
		value: 'default',
		label: t('libresign', 'Default background'),
		description: t('libresign', 'Use the default LibreSign background image.'),
	},
	{
		value: 'custom',
		label: t('libresign', 'Custom background'),
		description: t('libresign', 'Use a custom image uploaded by an administrator.'),
	},
	{
		value: 'deleted',
		label: t('libresign', 'No background'),
		description: t('libresign', 'Do not apply any background image to signatures.'),
	},
]

const input = ref<HTMLInputElement | null>(null)
const showLoading = ref(false)
const errorMessage = ref('')

const normalizedValue = computed<'default' | 'custom' | 'deleted'>(() => {
	if (props.modelValue === 'custom' || props.modelValue === 'deleted') {
		return props.modelValue
	}

	return 'default'
})

function setValue(value: 'default' | 'custom' | 'deleted') {
	errorMessage.value = ''
	emit('update:modelValue', value)
}

function activateLocalFilePicker() {
	errorMessage.value = ''
	if (!input.value) {
		return
	}

	input.value.value = ''
	input.value.click()
}

async function onChangeBackground(event: Event) {
	const file = (event.target as HTMLInputElement)?.files?.[0]
	if (!file) {
		return
	}

	const formData = new FormData()
	formData.append('image', file)

	showLoading.value = true
	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
		setValue('custom')
	} catch ({ response }: any) {
		errorMessage.value = response?.data?.ocs?.data?.message || 'Upload failed'
	} finally {
		showLoading.value = false
	}
}

function onChange(value: 'default' | 'custom' | 'deleted', selected?: unknown) {
	if (selected === false) {
		return
	}

	if (value === 'default') {
		setValue('default')
		return
	}

	if (value === 'deleted') {
		setValue('deleted')
		return
	}

	activateLocalFilePicker()
}
</script>

<style scoped lang="scss">
.signature-background-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
	}

	:deep(.signature-background-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signature-background-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signature-background-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}

input[type='file'] {
	display: none;
}
</style>
