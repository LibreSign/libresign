<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import { get } from 'lodash-es'
import { service as signService } from '../../domains/sign'
import DragResize from 'vue-drag-resize'
import Sidebar from './partials/Sidebar.vue'
import PageNavigation from './partials/PageNavigation.vue'

const emptySignerData = () => ({
	data: {},
	element: {
		page: 0,
		height: 90,
		left: 100,
		top: 100,
		width: 370,
	},
})

export default {
	name: 'SignDetail',
	components: {
		Content,
		DragResize,
		Sidebar,
		PageNavigation,
	},
	data() {
		return {
			document: {
				name: '',
				signers: [],
				pages: [],
				visibleElements: [],
			},
			currentSigner: emptySignerData(),
		}
	},
	computed: {
		uuid() {
			return this.$route.params.uuid || ''
		},
		pageIndex() {
			return this.currentSigner.element.page
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
		hasSignerSelected() {
			return !!this.currentSigner.data.email
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
			this.currentSigner.element = {
				...this.currentSigner.element,
				...newRect,
			}
		},
		onSelectSigner(signer) {
			this.currentSigner = {
				...emptySignerData(),
				data: signer,
			}
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
			<PageNavigation v-model="currentSigner.element.page" v-bind="{ pages }" width="827px" />
			<div class="image-page--main">
				<div
					class="image-page--container"
					:style="{ '--page-img-w': '827px', '--page-img-h': '1169px' }">
					<DragResize
						v-if="hasSignerSelected"
						parent-limitation
						:is-active="true"
						:w="370"
						:h="90"
						@resizing="resize"
						@dragging="resize">
						<div class="image-page--element">
							{{ currentSigner.data.email }}
						</div>
						<div class="image-page--action">
							<button class="primary">
								{{ t('libresign', 'Save') }}
							</button>
						</div>
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
		display: flex;
		position: absolute;
		cursor: grab;
		background: rgba(0, 0, 0, 0.3);
		color: #FFF;
		font-weight: bold;
		justify-content: space-around;
		align-items: center;
		flex-direction: row;
		&:active {
			cursor: grabbing;
		}
	}
	&--action {
		width: 100%;
		position: absolute;
		top: 100%;
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
