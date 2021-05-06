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

		<Sign v-show="signShow" :disabled="disabledSign" @sign:pdf="signDocument">
			<template slot="actions">
				<button class="return-button" @click="returnSign">
					Retornar
				</button>
			</template>
		</Sign>
		<Request v-show="requestShow" :fileinfo="info" @request:signature="request">
			<template slot="actions">
				<button class="return-button" @click="requestSignature">
					Retornar
				</button>
			</template>
		</Request>
	</AppSidebarTab>
</template>

<script>
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { showError, showSuccess } from '@nextcloud/dialogs'
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
			disabledSign: false,
			info: this.fileInfo,
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

	created() {
		this.getInfo()
	},

	methods: {
		async getInfo() {
			const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/file_id/${this.fileInfo.id}`))
			// eslint-disable-next-line no-console
			console.log(response)
		},
		sign() {
			this.showButtons = false
			this.signShow = true
		},
		async signDocument(param) {
			try {
				const response = await axios.post(generateUrl(`apps/libresign/api/0.1/sign/file_id/${this.fileInfo.id}`), {
					password: param,
				})
				showSuccess(response.data.message)
				this.disabledSign = true
			} catch (err) {
				console.error(err.response)
				showError(err.response.data.errors[0])
			}
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
		async requestSignature(param) {
			// const id = this.fileInfo.id
			// eslint-disable-next-line no-console
			console.log(this.fileInfo)
			const name = 'teste'
			const users = param
			try {
				const response = await axios.post(generateUrl('/apps/libresign/api/0.1/webhook/register'), {
					file: {
						fileid: this.fileInfo.id,
					},
					name,
					users,
				})
				// eslint-disable-next-line no-console
				console.log(response)
			} catch (err) {
				// eslint-disable-next-line no-console
				console.error(err)
			}
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
