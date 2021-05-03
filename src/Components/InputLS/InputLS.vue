<template>
	<form @submit="e => e.preventDefault()">
		<input
			v-model="value"
			class="input__input"
			:placeholder="placeholder"
			:type="type">
		<button class="icon-confirm" @click="onSubmit" />
	</form>
</template>

<script>
export default {
	name: 'InputLS',
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
	},

	data() {
		return {
			value: '',
		}
	},

	methods: {
		onSubmit(event) {
			this.$emit('submit', this.value)
		},
	},
}
</script>

<style lang="scss" scoped>
form{
	display: flex;
	flex-direction: row !important;
	border: 1px solid #cecece;
	align-items: center;

	input{
		border: none;
		width: 100%;
		padding-left: 15px
	}

	button{
		width: 24px;
		height: 24px;
		border: none;
		background-color: transparent;
	}
}
</style>>
