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
	<div class="container">
		<div class="document">
			<div class="container-tools">
				<div ref="tools" class="tools">
					<img class="tool" :src="zoomInIcon" alt="Zoom In">
					<img class="tool" :src="zoomOutIcon" alt="Zoom Out">
				</div>
				<div class="thumbnails" />
			</div>
			<div v-for="image in myPdf.images" :key="image.id" class="page">
				<span>Pagina: {{ image.id }} de {{ myPdf.images.length }}</span>
				<img :src="image.src" @mousedown="getCoordinates" @mousemove="getCoordinatesMove">
			</div>
		</div>
	</div>
</template>

<script>
import MyImage1 from '../../assets/images/pdf/image.png'
import ZoomIn from '../../assets/images/zoom_in.png'
import ZoomOut from '../../assets/images/zoom_out.png'

export default {
	name: 'PDFViewer',

	props: {
		url: {
			type: String,
			required: false,
			default: '',
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
				images: [{ id: 1, src: MyImage1 }, { id: 2, src: MyImage1 }],
			},
			startSelection: false,
			coordinates: {
				startX: 0,
				startY: 0,
				relativeStartX: 0,
				relativeStartY: 0,
				endX: 0,
				endY: 0,
			},
		}
	},

	methods: {
		getCoordinates(event) {
			const { clientX, clientY, offsetX, offsetY } = event
			this.coordinates.startX = clientX
			this.coordinates.startY = clientY
			this.coordinates.relativeStartX = offsetX
			this.coordinates.relativeStartY = offsetY
			this.startSelection = true
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
.container{
	overflow: scroll;
	position: relative;
	display: inline-block;
	widows: 818px;
	height: 2214px;
	justify-content: center;

	.document{
		transform-origin: 0% 0%;
		overflow: visible;
		background-color: rgb(233, 233, 233);

		img{
			display: block;
			width: 816px;
		}

		.container-tools{
			display: flex;
			flex-direction: row;
			border-bottom: 1px solid #cecece;

			.tools{
				width: 90%;
				display: flex;
				flex-direction: row;

				img{
					width: 22px;
					height: 22px;
				}

				.tool{
					margin: 0 10px;
				}
			}
		}
	}
}
</style>
