<template>
	<div class="modal-draw">
		<img :src="src">
	</div>
</template>

<script>
export default {
	name: 'PreviewSignature',
	props: {
		src: {
			type: String,
			default: () => '',
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.modal-draw{
	background-color: #cecece;
	border-radius: 10px;
	margin-top: 10px;
	margin-bottom: 10px;
}
</style>
