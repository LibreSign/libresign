<template>
    <div class="modal-draw">
        <img :src="src">
    </div>
</template>

<script>

import { SignatureImageDimensions } from '../Draw/options.js'

export default {
	name: 'PreviewSignature',
	props: {
		src: {
			type: String,
			default: () => "",
			required: true,
		}
	},
	components: {
	},

	data: () => ({
		canvasWidth: SignatureImageDimensions.width,
		canvasHeight: SignatureImageDimensions.height,
		canvas: null,
		isDrawing: false,
		color: '#000000',
	}),

	mounted() {

	},

	beforeDestroy() {
	},

	methods: {
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
