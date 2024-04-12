<template>
	<div>
		<NcLoadingIcon v-if="loading" :size="64" :name="t('libresign', 'Loading file')" />
		<div v-show="isLoaded" class="modal-draw">
			<img v-show="isLoaded" :src="src" @load="onImageLoad">
		</div>
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'PreviewSignature',
	components: {
		NcLoadingIcon,
	},
	props: {
		src: {
			type: String,
			default: () => '',
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			isLoaded: false,
		}
	},
	methods: {
		onImageLoad() {
			this.loading = false
			this.isLoaded = true
			this.$emit('loaded')
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
	min-width: 350px;
	min-height: 95px;
}
</style>
