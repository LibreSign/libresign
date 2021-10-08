<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<div class="container-viewer">
		<header v-show="isMobile" class="info">
			<span>{{ t('libresign', 'Click to open options') }}</span>
		</header>
		<div v-show="enableTools" class="container-tools">
			<div ref="tools" class="tools">
				<img class="tool" :src="zoomInIcon" alt="Zoom In">
				<img class="tool" :src="zoomOutIcon" alt="Zoom Out">
			</div>
			<div class="thumbnails" style="display: none;">
				<div class="icon-toggle-pictures" />
			</div>
		</div>

		<div class="document">
			<div v-for="image in myPdf.images" :key="image.id" class="page">
				<div class="header">
					<span>{{ myPdf.name }}</span>
					<span>{{ t('libresign', '{pageNumber} of {totalPage}', { pageNumber: image.id, totalPage: totalPages }) }}</span>
				</div>
				<img :src="image.src" @click="showMyCoordinates">
			</div>
			<div v-show="enableButtons"
				v-if="isMobile"
				id="containerTools"
				ref="containerTools"
				class="container-tools">
				<header v-show="isMobile">
					<h1>{{ myPdf.name }}</h1>
				</header>
				<div v-show="!signSelected" class="content-actions-tools">
					<button v-show="isMobile" class="primary" @click="signSelected = true">
						{{ t('libresign', 'Sign') }}
					</button>
					<button>
						{{ t('libresign', 'Insert Signature and/or Initials') }}
					</button>
				</div>
				<div v-if="signSelected" class="content-tools-sign">
					<Sign :pfx="getHasPfx" @sign:document="signDocument">
						<template #actions>
							<button class="" @click="signSelected = false">
								{{ t('libresign', 'Return') }}
							</button>
						</template>
					</Sign>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import MyImage1 from '../../assets/images/image.jpg'
import ZoomIn from '../../assets/images/zoom_in.png'
import ZoomOut from '../../assets/images/zoom_out.png'
import Sign from '../Sign'
import { getCurrentUser } from '@nextcloud/auth'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'

export default {
	name: 'PDFViewer',

	components: {
		Sign,
	},
	mixins: [isMobile],

	props: {
		url: {
			type: String,
			required: false,
			default: '',
		},
		enableTools: {
			type: Boolean,
			required: false,
			default: false,
		},
	},

	data() {
		return {
			zoomInIcon: ZoomIn,
			zoomOutIcon: ZoomOut,
			pdf: this.url,
			myPdf: {
				id: 123,
				name: 'Profile.pdf',
				url: this.url,
				images: [{ id: 1, src: MyImage1 }, { id: 2, src: MyImage1 }, { id: 3, src: MyImage1 }, { id: 4, src: MyImage1 }, { id: 5, src: MyImage1 }, { id: 6, src: MyImage1 }, { id: 7, src: MyImage1 }],
				uuid: 'aisdjaiosdjaso',
			},
			signSelected: false,
			startSelection: false,
			coordinates: {
				startX: 0,
				startY: 0,
				relativeStartX: 0,
				relativeStartY: 0,
				endX: 0,
				endY: 0,
			},
			enableButtons: false,
		}
	},

	computed: {
		...mapGetters({
			getHasPfx: 'getHasPfx',
		}),
		totalPages() {
			return this.myPdf.images.length
		},
	},
	watch: {
		enableButtons(newVal, oldVal) {
			console.info(`OLD: ${oldVal}, newVal: ${newVal}`)
			if (newVal === false) {
				this.signSelected = false
			}
		},
	},

	created() {
		console.info('Current User: ', getCurrentUser())
	},

	methods: {
		...mapActions({
			signDocument: 'sign/SIGN_DOCUMENT',
		}),
		getCoordinates(event) {
			const { clientX, clientY, offsetX, offsetY } = event
			this.coordinates.startX = clientX
			this.coordinates.startY = clientY
			this.coordinates.relativeStartX = offsetX
			this.coordinates.relativeStartY = offsetY
			this.startSelection = true

			console.info('Coordinates: ', clientX, clientY, offsetX, offsetY)
		},
		handleTools() {
			this.enableButtons = !this.enableButtons
		},
		async signDocument(param) {
			this.updating = true
			this.disableButton = true
			console.info(param)
			this.signDoc({ fileId: this.myPdf.uuid, password: param })

			if (this['error/getError'].length > 0) {
				this.updating = false
				this.disableButton = false
			} else {
				this.updating = true
				this.disableButton = true
			}
		},

		showMyCoordinates(event) {
			if (this.isMobile) {
				this.handleTools()
				const containerTools = this.$refs.containerTools

				// add 11 to top and 106 to left for centralized to the point click
				containerTools.style.top = `${event.clientY + 11}px`
				containerTools.style.left = `${event.clientX - 106}px`
			}
		},

		getCoordinatesMove(event) {
			const { clientX, clientY } = event
			this.coordinates.endX = clientX
			this.coordinates.endY = clientY
		},
	},
}
</script>

<style lang="scss" scoped>
.container-viewer{
	display: flex;
	width: 100%;
	background-color: rgb(233, 233, 233);
	overflow: scroll;
	position: relative;
	flex-direction: column !important;
	align-items: center;

	header.info{
		width: 100%;
		padding: 10px;
		background-color: #2b936b;
		position: fixed;
		top: 0px;
		left: 0px;
		justify-content: center;
		align-items: center;
		display: flex;

		span{
			font-size: 1rem;
			font-weight: bold;
		}
	}

	.container-tools{
		width: 100%;
		display: flex;
		flex-direction: row;
		border-bottom: 1px solid #cecece;

		.tools{
			width: 100%;
			display: flex;
			flex-direction: row;
			margin-left: 50%;

			img{
				width: 16px;
				height: 16px;
			}

			.tool{
				margin: 10px;
			}

		}
		.thumbnails{
			margin: 10px;
		}
	}

	.document{
		transform-origin: 0% 0%;
		overflow: visible;
		background-color: rgb(233, 233, 233);
		height: 90%;
		margin-top: 15px;

		.container-tools{
			top: 200px;
			left: 130px;
			position: fixed;
			background-color: #fff;
			border: 1px solid #e9e9e9;
			z-index: 1000;
			min-width: 200px;
			width: auto;
			text-align: left;
			border-radius: 10px;
			padding: 10px;
			flex-direction: column;

			&::before{
				top: -11px;
				left: 105px;
				border: solid transparent;
				border-bottom-color: #e9e9e9;
				border-top-width: 0;
				content: '';
				display: block;
				position: absolute;
				pointer-events: none;
				z-index: 0;
			}

			&::after{
				top: -10px;
				left: 100px;
				border: solid transparent;
				border-width: 10px;
				content: '';
				display: block;
				position: absolute;
				pointer-events: none;
				z-index: 3;
				border-bottom-color: #fff;
				border-top-width: 0;
			}

			header{
				margin-bottom: 10px;
				border-bottom-color: 1px solid #dedede;
				font-size: 1rem;
				font-style: italic;
				font-weight: bold;
			}

			.content-actions-tools{
				display: flex;
				flex-direction: row;
				width: 100%;
				justify-content: center;
				align-items: center;
				margin: 10px;

				button{
					&:first-child{
						margin-right: 10px;
					}
				}
			}
		}

		img{
			display: block;
			width: 100%;
			max-width: 816px;
		}

		.page{
			margin: 30px 0;

			.header{
				display: flex;
				flex-direction: row;

				span{
					margin: 0 10px;
				}
			}
		}
	}
}
</style>
