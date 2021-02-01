<template>
	<Content app-name="libresign">
		<h1>Home</h1>
		<button @click="nextPage">
			NextPage
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
		nextPage() { this.$router.push({ name: 'CreateUser' }) },
	},
}
</script>

<style scoped>
	button {
		width: 150px;
		height: 50px;
	}
</style>
