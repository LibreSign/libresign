<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { get } from 'lodash-es'
import { service as signService } from '../../domains/sign'
import DragResize from 'vue-drag-resize'
import Sidebar from './partials/Sidebar.vue'

export default {
	name: 'SignDetail',
	components: {
		Content,
		DragResize,
		Sidebar,
	},
	data() {
		return {
			pageIndex: 0,
			document: {
				name: '',
				signers: [],
				pages: [],
			},
			currentSigner: {
				data: {},
				element: {},
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
		onSelectSigner(signer) {
			console.log({ signer })
		},
	},
}
</script>

<template>
	<Content class="view-sign-detail" app-name="libresign">
		<div>
			<h2>{{ document.name }}</h2>
			<Sidebar class="view-sign-detail--sidebar"
				:signers="document.signers"
				@select:signer="onSelectSigner" />
		</div>
		<div class="image-page">
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
.view-sign-detail {
	&--sidebar {
		width: 300px;
	}
}

.image-page {
	width: 100%;
	margin: 0.5em;
	&--main {
		position: relative;
	}
	&--element {
		width: 100%;
		height: 100%;
		display: inline-block;
		position: absolute;
		cursor: grab;
		background: rgba(0, 0, 0, 0.300);
		&:active {
			cursor: grabbing;
		}
	}
	&--container {
		border-color: #000;
		border-style: solid;
		border-width: thin;
		width: var(--page-img-w);
		height: var(--page-img-h);
		left: 0;
		top: 0;
		&, img {
			user-select: none;
			outline: 0;
		}
		img {
			max-width: 100%;
		}
	}
}
</style>
