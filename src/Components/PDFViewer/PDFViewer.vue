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
	<iframe :src="pdf" frame-src>
		Este iframe não é suportado no seu navegador.
	</iframe>
</template>

<script>
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
			pdf: `data:application/pdf;base64,${this.url}`,
		}
	},
}
</script>

<style scoped>
	iframe {
		overflow: hidden;
		height: 100%;
		width: 100%;
	}
</style>
