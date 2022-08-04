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
	<AppContent>
		<div v-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>
		<div v-else id="content" class="app-libresign">
			<h2>{{ t('libresign', 'Create new subscription.') }}</h2>
			<FormLibresign />
		</div>
	</AppContent>
</template>

<script>
import FormLibresign from './FormLibresign.vue'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import '@nextcloud/dialogs/styles/toast.scss'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'CreateSubscription',
	components: {
		FormLibresign,
		AppContent,
	},
	data() {
		return {
			loading: true,
			error: '',
		}
	},
	computed: {},
	async mounted() {
		await this.checkRootCertificate()
	},

	methods: {
		async checkRootCertificate() {
			this.error = ''
			try {
				const response = await axios.get(
					generateUrl('/apps/libresign/api/0.1/settings/has-root-cert'),
				)
				if (!response.data || !response.data.hasRootCert) {
					this.error = t('libresign', 'Root certificate has not been configured by the Administrator!')
				}
			} catch (e) {
				showError(e)
			}
		},
	},
}

</script>
<style scoped>
#content {
	width: 100vw;
	padding: 20px;
	padding-top: 70px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	align-items: center;
}
</style>
