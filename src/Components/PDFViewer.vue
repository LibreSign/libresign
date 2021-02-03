<template>
	<iframe :src="url" />
</template>

<script>
export default {
	name: 'PDFViewer',

	props: {
		url: {
			type: String,
			required: true,
		},
	},
}
</script>

<style scoped>
	iframe {
		overflow: hidden;
		height: 100%;
		width: 100%;
	}
</style>
