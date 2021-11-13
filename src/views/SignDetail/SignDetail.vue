<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import DragResize from 'vue-drag-resize'
import { get, pick, find, map } from 'lodash-es'
import Content from '@nextcloud/vue/dist/Components/Content'
import { service as signService } from '../../domains/sign'
import Sidebar from './partials/Sidebar.vue'
import PageNavigation from './partials/PageNavigation.vue'
import { showResponseError } from '../../helpers/errors'

const emptyElement = () => {
	return {
		coordinates: {
			page: 0,
			height: 90,
			left: 100,
			top: 100,
			width: 370,
		},
		elementId: 0,
	}
}

const emptySignerData = () => ({
	signed: null,
	displayName: '',
	fullName: null,
	me: true,
	signatureId: 0,
	email: '',
	element: emptyElement(),
})

const deepCopy = val => JSON.parse(JSON.stringify(val))

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
			signers: [],
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
			return this.currentSigner.element.coordinates.page
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
		pageDimensions() {
			const { w, h } = this.page.resolution
			return {
				height: h,
				width: w,
				css: {
					height: `${Math.ceil(h)}px`,
					width: `${Math.ceil(w)}px`,
				},
			}
		},
		hasSignerSelected() {
			return !!this.currentSigner.email
		},
	},
	async mounted() {
		try {
			this.signers = []
			this.document = await signService.validateByUUID(this.uuid)
			this.$nextTick(() => {
				this.updateSigners()
			})
		} catch (err) {
			console.error(err)
		}

		this.$refs.img.setAttribute('draggable', false)
	},
	methods: {
		updateSigners() {
			const [signers, visibleElements] = deepCopy([this.document.signers, this.document.visibleElements])

			this.signers = map(signers, signer => {
				const element = find(visibleElements, (el) => {
					return el.email === signer.email || el.uid === signer.displayName // TODO!: change to signer.uid
				})

				const row = {
					...signer,
					element: emptyElement(),
				}

				if (element) {
					const coordinates = pick(element.coordinates, ['top', 'left', 'width', 'height', 'page'])

					row.element = {
						coordinates,
						page: coordinates.page - 1,
						elementId: element.elementId,
					}
				}

				return row
			})
		},
		resize(newRect) {
			const { coordinates } = this.currentSigner.element

			this.currentSigner.element.coordinates = {
				...coordinates,
				...newRect,
			}
		},
		onSelectSigner(signer) {
			this.currentSigner = { ...signer }
		},
		publish() {

		},
		async saveElement() {
			const { element, email } = this.currentSigner

			const payload = {
				coordinates: {
					...element.coordinates,
					page: element.coordinates.page + 1,
				},
				type: 'signature',
				email,
			}

			try {
				await signService.addElement(this.uuid, payload)
				showSuccess(t('libresign', 'Element created'))
			} catch (err) {
				if (err.response) {
					return showResponseError(err.response)
				}

				return showError(err.message)
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
				:signers="signers"
				@select:signer="onSelectSigner">
				<button class="primary" @click="publish">
					{{ t('libresign', 'Request sign') }}
				</button>
			</Sidebar>
		</div>
		<div class="image-page">
			<!-- <canvas ref="canvas" :width="page.resolution.w" :height="page.resolution.h" /> -->
			<!-- <div :style="{ width: `${page.resolution.w}px`, height: `${page.resolution.h}px`, background: 'red' }">
				<img :src="page.url">
			</div> -->
			<PageNavigation
				v-model="currentSigner.element.coordinates.page"
				v-bind="{ pages }"
				:width="pageDimensions.css.width" />
			<div class="image-page--main">
				<div
					class="image-page--container"
					:style="{ '--page-img-w': pageDimensions.css.width, '--page-img-h': pageDimensions.css.height }">
					<DragResize
						v-if="hasSignerSelected"
						parent-limitation
						:is-active="true"
						:w="370"
						:h="90"
						@resizing="resize"
						@dragging="resize">
						<div class="image-page--element">
							{{ currentSigner.email }}
						</div>
						<div class="image-page--action">
							<button class="primary" @click="saveElement">
								{{ t('libresign', 'Save') }}
							</button>
						</div>
					</DragResize>
					<img ref="img" :src="page.url">
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
