<template>
	<Content app-name="libresign">
		<h1>Home</h1>
		<button @click="nextPage('CreateUser')">
			Cadastrar Usuarios
		</button>
		<button @click="routeExternal">
			Page 1
		</button>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'

export default {
	name: 'Home',
	components: {
		Content,
	},
	methods: {
		nextPage(name) { this.$router.push({ name }) },
		routeExternal() {
			const url = window.location.href
			// eslint-disable-next-line
			console.log(url)
			window.location.href = url + 'external'
		},
	},
}
</script>

<style scoped>
	button {
		width: 150px;
		height: 50px;
	}
</style>
