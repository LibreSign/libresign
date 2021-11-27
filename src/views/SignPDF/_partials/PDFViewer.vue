<template>
	<iframe :src="url">
		{{ t("libresign", "This iframe is not supported in your browser.") }}
	</iframe>
</template>

<script>
export default {
	name: 'PDFViewer',

	props: {
		url: {
			type: String,
			required: false,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
iframe {
	overflow: hidden;
	height: 100%;
	width: 100%;
}
</style>
