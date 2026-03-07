<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		:name="title"
		:buttons="dialogButtons">
		<NcNoteCard v-if="localSuccessMessage" type="success">
			{{ localSuccessMessage }}
		</NcNoteCard>
		<NcNoteCard v-if="localErrorMessage" type="error">
			{{ localErrorMessage }}
		</NcNoteCard>
		<NcTextField v-model="localName"
			:label="label"
			:placeholder="placeholder"
			:minlength="ENVELOPE_NAME_MIN_LENGTH"
			:maxlength="ENVELOPE_NAME_MAX_LENGTH"
			:helper-text="`${localName.length} / ${ENVELOPE_NAME_MAX_LENGTH}`"
			@keydown.enter="handleSave" />
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'

import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'

defineOptions({
	name: 'EditNameDialog',
})

const props = withDefaults(defineProps<{
	name?: string | null
	title?: string
	label?: string
	placeholder?: string
}>(), {
	name: '',
	title: 'Edit name',
	label: 'Name',
	placeholder: 'Enter name',
})

const emit = defineEmits<{
	(event: 'close', value: string | null): void
}>()

const localName = ref(props.name || '')
const localSuccessMessage = ref('')
const localErrorMessage = ref('')

const isNameValid = computed(() => {
	const trimmedName = localName.value.trim()
	return trimmedName.length >= ENVELOPE_NAME_MIN_LENGTH && trimmedName.length <= ENVELOPE_NAME_MAX_LENGTH
})

function clearMessages() {
	localSuccessMessage.value = ''
	localErrorMessage.value = ''
}

function showSuccess(message: string) {
	clearMessages()
	localSuccessMessage.value = message
	setTimeout(() => {
		localSuccessMessage.value = ''
	}, 5000)
}

function showError(message: string) {
	clearMessages()
	localErrorMessage.value = message
}

function handleClose() {
	emit('close', null)
}

function handleSave() {
	if (!isNameValid.value) {
		return
	}

	const trimmedName = localName.value.trim()

	if (!trimmedName) {
		showError(t('libresign', 'Name cannot be empty'))
		return
	}

	if (trimmedName.length < ENVELOPE_NAME_MIN_LENGTH) {
		showError(t('libresign', 'Name must be at least {min} characters', { min: ENVELOPE_NAME_MIN_LENGTH }))
		return
	}

	if (trimmedName.length > ENVELOPE_NAME_MAX_LENGTH) {
		showError(t('libresign', 'Name must not exceed {max} characters', { max: ENVELOPE_NAME_MAX_LENGTH }))
		return
	}

	emit('close', trimmedName)
}

const dialogButtons = computed(() => {
	return [
		{
			label: t('libresign', 'Cancel'),
			callback: () => {
				handleClose()
			},
		},
		{
			label: t('libresign', 'Save'),
			variant: 'primary' as const,
			disabled: !isNameValid.value,
			callback: () => {
				handleSave()
			},
		},
	]
})

watch(() => props.name, (newVal) => {
	localName.value = newVal || ''
})

defineExpose({
	localName,
	localSuccessMessage,
	localErrorMessage,
	ENVELOPE_NAME_MIN_LENGTH,
	ENVELOPE_NAME_MAX_LENGTH,
	isNameValid,
	dialogButtons,
	t,
	clearMessages,
	showSuccess,
	showError,
	handleClose,
	handleSave,
})
</script>

<style scoped>
/* NcTextField handles its own styling */
</style>
