<template>
	<AppContent>
		<div v-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>
		<div v-else id="content" class="app-libresign">
			<h2>{{ t('libresign', 'Criar nova assinatura') }}</h2>
			<FormLibresign />
		</div>
	</AppContent>
</template>

<script>
import FormLibresign from './FormLibresign'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'App',
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
					generateUrl('/apps/libresign/api/0.1/signature/check'),
				)
				if (!response.data || !response.data.hasRootCert) {
					this.error = t('libresign', 'Certificado raiz não foi configurado pelo Administrador!')
				}
			} catch (e) {
				console.error(e)
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
