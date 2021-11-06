<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { get } from 'lodash-es'
import { service as signService } from '../../domains/sign'
import DragResize from 'vue-drag-resize'

export default {
	name: 'SignDetail',
	components: {
		Content,
		DragResize,
	},
	data() {
		return {
			pageIndex: 0,
			document: {
				name: '',
				pages: [],
			},
		}
	},
	computed: {
		uuid() {
			return this.$route.params.uuid || ''
		},
		pages() {
			return get(this.document, 'pages', [])
		},
		page() {
			return this.pages[this.pageIndex] || {
				url: '',
				resolution: {
					h: 0,
					w: 0,
				},
			}
		},
	},
	async mounted() {
		try {
			this.document = await signService.validateByUUID(this.uuid)
		} catch (err) {
			console.error(err)
		}
	},
	methods: {
		resize(newRect) {
			console.log(newRect)
		},
	},
}
</script>

<template>
	<Content app-name="libresign">
		<div class="image-page">
			<h2>{{ document.name }}</h2>
			<!-- <canvas ref="canvas" :width="page.resolution.w" :height="page.resolution.h" /> -->
			<!-- <div :style="{ width: `${page.resolution.w}px`, height: `${page.resolution.h}px`, background: 'red' }">
				<img :src="page.url">
			</div> -->
			<div class="image-page--main">
				<div
					class="image-page--container"
					:style="{ '--page-img-w': '827px', '--page-img-h': '1169px' }">
					<DragResize
						parent-limitation
						:is-active="true"
						:w="370"
						:h="90"
						@resizing="resize"
						@dragging="resize">
						<div class="image-page--element" />
					</DragResize>
					<img :src="page.url">
				</div>
			</div>
		</div>
	</Content>
</template>

<style lang="scss" scoped>
.image-page {
	&--main {
		position: relative;
	}
	&--element {
		width: 100%;
		height: 100%;
		display: inline-block;
		position: absolute;
		background: rgba(0, 0, 0, 0.300);
	}
	&--container {
		position: absolute;
		width: var(--page-img-w);
		height: var(--page-img-h);
		padding-left: 1em;
		left: 0;
		top: 0;
	}
}
</style>
