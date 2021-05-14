<template>
	<div :class="[hasError ? 'input-item error': 'input-item']">
		<label for="input" :class="icon" />
		<input id="input"
			:type="type"
			:value="value"
			@input="$emit('input', $event.target.value)">
	</div>
</template>

<script>
export default {
	name: 'Input',
	props: {
		icon: {
			type: String,
			required: false,
			default: 'icon-category-security',
		},
		type: {
			type: String,
			required: false,
			default: 'text',
		},
		value: {
			type: String,
			required: true,
			default: '',
		},
		hasError: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	methods: {
		onInput(event) {
			this.$emit('input', event.target.value)
		},
	},

}
</script>

<style lang="scss" scoped>
	.input-item{
		display: flex;
		flex-direction: row;
		border: 1px solid #cecece;
		border-radius: 10px;
		width: 100%;
		max-width: 380px;
		label{
			padding: 0 20px;
			border-radius: 10px 0 0 10px;
			background-color: #cecece;
		}
		input{
			border: none;
			width: 100%;
		}
		&:focus-within{
			border: thin solid #0082c9;
			box-shadow: inset 0 0 1em rgba(0, 130, 201, .5);
		}
	}

	.error{
		border: 1px solid #b40c0c !important;
	}
</style>
