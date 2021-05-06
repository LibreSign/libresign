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
	<AppSidebar
		title="LibreSign">
		<AppSidebarTab
			id="libresign-tab"
			icon="icon-rename"
			:name="t('libresign', 'LibreSign')">
			<div v-show="showButtons" class="buttons">
				<button class="primary" @click="sign">
					{{ t('libresign', 'Sign') }}
				</button>
				<button class="primary" @click="request">
					{{ t('libresign', 'Request sign') }}
				</button>
			</div>

			<Sign v-show="signShow" @sign:pdf="signDocument">
				<template slot="actions">
					<button class="return-button" @click="returnSign">
						{{ t('libresign', 'Return') }}
					</button>
				</template>
			</Sign>
			<Request v-show="requestShow">
				<template slot="actions">
					<button class="return-button" @click="returnRequest">
						{{ t('libresign', 'Return') }}
					</button>
				</template>
			</Request>
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Request from '../Components/Request'
import axios from '@nextcloud/axios'
import Sign from '../Components/Sign'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'LibresignTab',

	components: {
		AppSidebar,
		AppSidebarTab,
		Sign,
		Request,
	},
	mixins: [],

	data() {
		return {
			showButtons: false,
			signShow: false,
			requestShow: false,
			fileInfo: null,
		}
	},

	methods: {
		/**
		 * Update current fileInfo and fetch new data
		 * @param {Object} fileInfo the current file FileInfo
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
			this.resetState()
		},
		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.showButtons = true
			this.signShow = false
		},
		sign() {
			this.showButtons = false
			this.signShow = true
		},
		async signDocument(param) {
			// eslint-disable-next-line no-console
			console.log(param)
			const id = window.location.href.split('fileid=')[1]
			const response = await axios.post(generateUrl(`apps/libresign/api/0.1/sign/file_id/${id}`), {
				password: param,
			})
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
	},
}
</script>
<style lang="scss">
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
}

#tab-libresign .app-sidebar-header {
	display: none;
}
</style>
