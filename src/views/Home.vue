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
	<Content app-name="libresign">
		<h1>Home</h1>
		<button @click="nextPage('CreateUser')">
			Cadastrar Usuarios
		</button>
		<button @click="routeExternal">
			Page 1
		</button>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'

export default {
	name: 'Home',
	components: {
		Content,
	},
	methods: {
		nextPage(name) { this.$router.push({ name }) },
		routeExternal() {
			const url = window.location.href
			// eslint-disable-next-line
			console.log(url)
			window.location.href = url + 'external'
		},
	},
}
</script>

<style scoped>
	button {
		width: 150px;
		height: 50px;
	}
</style>
