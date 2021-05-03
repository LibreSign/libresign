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
	<AppSidebarTab
		:id="id"
		:icon="icon"
		:name="name">
		<div v-show="showButtons" class="buttons">
			<button class="primary" @click="sign">
				Assinar
			</button>
			<button class="primary" @click="request">
				Solicitar assinatura
			</button>
		</div>

		<Sign v-show="signShow" @sign:pdf="signRequest">
			<template slot="actions">
				<button class="return-button" @click="returnSign">
					Retornar
				</button>
			</template>
		</Sign>
		<Request v-show="requestShow">
			<template slot="actions">
				<button class="return-button" @click="returnRequest">
					Retornar
				</button>
			</template>
		</Request>
	</AppSidebarTab>
</template>

<script>
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Request from '../Components/Request'
import axios from '@nextcloud/axios'
import Sign from '../Components/Sign'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'LibresignTab',

	components: {
		AppSidebarTab,
		Sign,
		Request,
	},
	mixins: [],

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},
	data() {
		return {
			icon: 'icon-rename',
			name: t('libresign', 'LibreSign'),
			showButtons: true,
			signShow: false,
			requestShow: false,
		}
	},

	computed: {
		id() {
			return 'libresignTab'
		},
		activeTab() {
			return this.$parent.activeTab
		},
	},
	methods: {
		sign() {
			this.showButtons = false
			this.signShow = true
		},
		returnSign() {
			this.showButtons = true
			this.signShow = false
		},
		request() {
			this.showButtons = false
			this.requestShow = true
		},
		returnRequest() {
			this.showButtons = true
			this.requestShow = false
		},

		async signRequest(param) {
			const uuid = ''
			const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/sign/${uuid}`), {
				password: param,
			})

			// eslint-disable-next-line no-console
			console.log(response)
		},
	},
}
</script>
<style lang="scss" scoped>
.buttons{
	display: flex;
	flex-direction: column;
	width: 100%;
	button{
		width: 100%
	}
}

.return-button{
	width: 80%;
	align-self: center;
	position:absolute;
	bottom: 10px;
}
</style>
