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
		<div id="container">
			<form>
				<Avatar id="avatar" :user="username.length ? username : 'User'" :size="sizeAvatar" />
				<input v-model="username"
					type="text"
					required
					:placeholder="t('libresign', 'Username...')">
				<input type="password" required :placeholder="t('libresign', 'Password...')">

				<div v-tooltip.right="{
					content: t('libresign', 'Password to confirm signature on the document!'),
					show: true,
					trigger: 'hover focus'

				}">
					<input type="password" required :placeholder="t('libresign', 'Password PFX')">
				</div>
				<button @click="teste">
					Cadastrar
				</button>
			</form>
		</div>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { showSuccess } from '@nextcloud/dialogs'
export default {
	name: 'CreateUser',
	components: {
		Content,
		Avatar,
	},

	data() {
		return {
			username: '',
			sizeAvatar: 100,
		}
	},

	created() {
		this.changeSizeAvatar()
	},

	methods: {
		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},
		teste() {
			showSuccess('Teste')
		},
	},
}
</script>

<style lang="scss" scoped>
#container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
}

form{
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 40%
}

form > div{
	width: 100%;
}

input {
	width: 100%
}
@media screen and (max-width: 535px) {
	form {width: 90%}
}

#tooltip{
	position: relative;

	span{
		width: 160px;
		background: #fefefe;
		padding: 8px;
		border-radius: 4px;
		font-size: 14px;
		font-weight: 500;
		opacity: 0;
		transition: opacity 0.4s;
		visibility: visible;

		position: absolute;
		bottom: calc(100% + 12px);
		left: 50%;
	}
}
</style>
