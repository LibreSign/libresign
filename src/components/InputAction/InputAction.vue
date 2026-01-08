<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form @submit="e => e.preventDefault()">
		<input v-model="value"
			class="input__input"
			:placeholder="placeholder"
			:type="type"
			:disabled="disabled">
		<button :disabled="disabled" :class="loading ? 'loading' : 'icon-confirm'" @click="onSubmit" />
	</form>
</template>

<script>
export default {
	name: 'InputAction',
	props: {
		type: {
			type: String,
			required: false,
			default: 'text',
		},
		placeholder: {
			type: String,
			required: false,
			default: '',
		},
		disabled: {
			type: Boolean,
			required: false,
			default: false,
		},
		loading: {
			type: Boolean,
			required: false,
			default: false,
		},
	},

	data() {
		return {
			value: '',
		}
	},

	methods: {
		clearInput() {
			this.value = ''
		},
		onSubmit(event) {
			this.$emit('submit', this.value)
		},
	},
}
</script>

<style lang="scss" scoped>
@import './styles';
</style>
