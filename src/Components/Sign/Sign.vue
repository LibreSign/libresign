<template>
	<div>
		<h1>Assinar documento</h1>

		<label for="password">Senha do documento</label>

		<form @submit="e => e.preventDefault()">
			<input id="password" v-model="password" type="password">
			<button type="button" class="primary" @click="sign">
				Assinar
			</button>
		</form>
		<slot name="actions" />
	</div>
</template>

<script>
export default {
	name: 'Sign',
	data() {
		return {
			password: '',
		}
	},
	methods: {
		sign() {
			this.$emit('sign:pdf', { password: this.password })
		},
	},
}
</script>
