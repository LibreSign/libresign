
<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
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
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program. If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<div :class="isMobile ? 'container mobile' : 'container'">
		<div v-show="viewDoc" id="viewer" class="content">
			<PDFViewer :url="pdfData.url" />
		</div>
		<div v-show="!isMobile" id="description" class="content">
			<Description
				:uuid="pdfData.uuid"
				:pdf-name="pdfData.filename"
				:pdf-description="pdfData.description"
				@onDocument="showDocument" />
		</div>
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { defaultsDeep } from 'lodash-es'
import { getInitialState } from '../../services/InitialStateService'
import Description from './_partials/Description'
import PDFViewer from '../../Components/PDFViewer'

export default {
	name: 'SignPDF',
	components: { Description, PDFViewer },
	mixins: [
		isMobile,
	],
	props: {
		uuid: {
			type: String,
			default: '',
		},
	},
	data() {
		const state = defaultsDeep(getInitialState() || {}, {
			action: 0,
			user: {
				name: '',
			},
			sign: {
				pdf: {
					url: '',
				},
				uuid: '',
				filename: '',
				description: null,
			},
			settings: {
				hasSignatureFile: false,
			},
		})

		return {
			state,
			viewDoc: true,
		}
	},
	computed: {
		pdfData() {
			const { sign } = this.state

			return {
				url: sign?.pdf?.url,
				uuid: sign?.uuid,
				filename: sign?.filename,
				description: sign?.filename,
			}
		},
	},
	mounted() {
		// this.validate(this.sign.uuid)
	},
	methods: {
		showDocument(param) {
			this.viewDoc = param
		},
		// async validate() {
		// const data = await signService.validateByUUID(this.sign.uuid)
		// },
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	flex-direction: row;
	width: 100%;
	height: 100%;

	.content{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	#description{
		width: 38%;

		@media (max-width: 1024px){
			width: 40%;
		}

		@media (max-width: 650px) {
			width: 100%;
			height: 20%;
		}
	}

	#viewer{
		display: flex;
		justify-content: center;
		align-items: center;
		background: #cecece;
		height: 100%;

		@media (max-width: 1024px){
			width: 60%;
		}

		@media (max-width: 650px) {
			width: 100%;
		}
	}

	@media (max-width: 650px) {
		display: flex;
		flex-direction: column;
	}

}

.mobile{
	#viewer{
		width: 100% !important;
	}
}

</style>
