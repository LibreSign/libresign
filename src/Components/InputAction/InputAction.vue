<template>
	<form @submit="e => e.preventDefault()">
		<input
			v-model="value"
			class="input__input"
			:placeholder="placeholder"
			:type="type"
			:disabled="disabled">
		<button class="icon-confirm" @click="onSubmit" />
	</form>
</template>

<script>
export default {
	name: 'InputAction',
	props: {
		type: {
			type: String,
			require: false,
			default: 'text',
		},
		placeholder: {
			type: String,
			require: false,
			default: '',
		},
		disabled: {
			type: Boolean,
			require: false,
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
