<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form @submit="onFormSubmit">
		<input v-model="value"
			class="input__input"
			:placeholder="placeholder"
			:type="type"
			:disabled="disabled">
		<button :disabled="disabled" :class="loading ? 'loading' : 'icon-confirm'" @click="onSubmit" />
	</form>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'

defineOptions({
	name: 'InputAction',
})

withDefaults(defineProps<{
	type?: string
	placeholder?: string
	disabled?: boolean
	loading?: boolean
}>(), {
	type: 'text',
	placeholder: '',
	disabled: false,
	loading: false,
})

const emit = defineEmits<{
	(e: 'submit', value: string): void
}>()

const value = ref('')

function clearInput() {
	value.value = ''
}

function onFormSubmit(event: SubmitEvent) {
	event.preventDefault()
}

function onSubmit() {
	emit('submit', value.value)
}

defineExpose({
	value,
	clearInput,
	onFormSubmit,
	onSubmit,
})
</script>

<style lang="scss" scoped>
@import './styles';
</style>
