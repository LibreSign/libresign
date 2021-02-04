
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
	<div id="container">
		<div id="viewer" class="content">
			<PDFViewer :url="urlPdf" />
		</div>
		<div id="description" class="content">
			<Description :pdf-name="name" :pdf-description="desc" />
		</div>
	</div>
</template>

<script>
import { showInfo } from '@nextcloud/dialogs'
import json from '../assets/dados.json'
import Description from '../Components/Description'
import PDFViewer from '../Components/PDFViewer'

export default {
	name: 'AssignExternal',

	components: { Description, PDFViewer },
	data: () => ({
		dados: json,
		desc: '',
		urlPdf: '',
		name: '',
		user: false,
	}),
	created() {
		this.getData()
	},
	mounted() {
		this.checkHasUser()
	},

	methods: {
		checkHasUser() {
			if (this.user === false) {
				showInfo('Usuario não existe, por favor crie uma nova conta!')
				this.$router.push({ name: 'CreateUser' })
			}
			// eslint-disable-next-line
			console.log(this.user)
		},
		getData() {
			this.urlPdf = this.dados.pdf.urlPDF
			this.name = this.dados.pdf.name
			this.desc = this.dados.pdf.description
			this.user = this.dados.user
		},
	},
}
</script>

<style lang="scss" scoped>
	#container {
		display: flex;
		flex-direction: row;
		width: 100%;
		height: 100%;
	}

	.content{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	#description{
		width: 38%;
	}

	#viewer{
		display: flex;
		justify-content: center;
		align-items: center;
		background: #cecece;
	}

</style>
