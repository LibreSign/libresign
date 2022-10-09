<template>
	<NcSettingsSection :title="title" :description="description">
		<div class="settings-section">
		<input type="button"
			class="primary"
			:value="labelDownloadAllBinaries"
			:disabled="formDisabled"
			@click="downloadAllBinaries">
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'DownloadBinaries',
	components: {
		NcSettingsSection,
	},
	data: () => ({
		title: t('libresign', 'Dependencies'),
		description: t('libresign', 'Binaries required to work. Could be near by 340MB to download, wait a moment.'),
		labelDownloadAllBinaries: t('libresign', 'Download binaries'),
		formDisabled: true,
		checklist: {
			java: false,
			jsignpdf: false,
			libresign_cli: false,
			cfssl: false
		}
	}),
	mounted() {
		this.$root.$on('afterConfigCheck', data => {
			this.items = data
			const java = data.filter((o) => o.resource == 'java' && o.status == 'error').length == 0
			const jsignpdf = data.filter((o) => o.resource == 'jsignpdf' && o.status == 'error').length == 0
			const libresign_cli = data.filter((o) => o.resource == 'libresign-cli' && o.status == 'error').length == 0
			const cfssl = data.filter((o) => o.resource == 'cfssl' && o.status == 'error').length == 0
			if (!java
				|| !jsignpdf
				|| !libresign_cli
				|| !cfssl
			) {
				this.labelDownloadAllBinaries = t('libresign', 'Download binaries')
				this.formDisabled = false
			} else {
				this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')
				this.formDisabled = true
			}
		});
	},
	methods: {
		async downloadAllBinaries() {
			this.formDisabled = true
			this.labelDownloadAllBinaries = t('libresign', 'Downloading binaries')
			try {
				const response = await axios.get(
					generateUrl('/apps/libresign/api/0.1/admin/download-binaries')
				)

				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
			} catch (e) {
				console.error(e)
				if (e.response.data.message) {
					showError(t('libresign', 'Could not download binaries.') + '\n' + e.response.data.message)
				} else {
					showError(t('libresign', 'Could not download binaries.'))
				}
				

			}
			this.$root.$emit('configCheck');
		},
	},
}
</script>
