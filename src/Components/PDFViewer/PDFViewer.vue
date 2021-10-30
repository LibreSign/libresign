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
	<div class="container-viewer" @scroll="disableToolbox">
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
				<img ref="documentimage" :src="image.src" @click="displayTools">
				<Tools class="tools"
					:enabled="toolsVisible"
					:document="myPdf"
					:position="positionToolsContainer" />
			</div>
		</div>
	</div>
</template>

<script>
import { mapActions } from 'vuex'
import MyImage1 from '../../assets/images/bg.png'
import ZoomIn from '../../assets/images/zoom_in.png'
import ZoomOut from '../../assets/images/zoom_out.png'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Tools from './Tools.vue'

export default {
	name: 'PDFViewer',
	components: {
		Tools,
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
			positionToolsContainer: {
				top: 0,
				left: 0,
			},
			toolsVisible: false,
		}
	},

	computed: {
		totalPages() {
			return this.myPdf.images.length
		},
	},

	methods: {
		...mapActions({
			signDoc: 'sign/SIGN_DOCUMENT',
		}),

		disableToolbox() {
			this.toolsVisible = false
		},

		handleTools() {
			this.toolsVisible = !this.toolsVisible
		},

		renderContainerTools(e) {
			const documentWidth = this.$refs.documentimage[0].width

			this.handleTools()

			this.positionToolsContainer.top = e.clientY + 11

			if (e.clientX >= (documentWidth - 330)) {
				this.positionToolsContainer.left = documentWidth - 330
			} else {
				this.positionToolsContainer.left = e.clientX - 106
			}
		},

		displayTools(event) {
			if (this.isMobile) {
				this.renderContainerTools(event)
			}
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

		img{
			display: block;
			width: 100%;
		}

		.tools{
			width: 330px;
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
