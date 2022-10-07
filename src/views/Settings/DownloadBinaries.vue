<template>
	<SettingsSection :title="title" :description="description">
		<div class="settings-section">
		<input type="button"
			class="primary"
			:value="submitLabel"
			:disabled="formDisabled"
			@click="downloadBinaries">
		</div>
	</SettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'DownloadBinaries',
	components: {
		SettingsSection,
	},
	data: () => ({
		title: t('libresign', 'Download binaries'),
		description: t('libresign', 'Binaries required to work. Could be near by 340Mb to download, wait a moment.'),
		submitLabel: t('libresign', 'Download binaries'),
	}),
	created() {
		this.formDisabled = true
		this.isBinariesInstalled();
	},
	methods: {
		async isBinariesInstalled() {
			const java = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/java_path', {})
			const jsignpdf = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/jsignpdf_jar_path', {})
			const libresign_cli = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/libresign_cli_path', {})
			if (java.data.ocs.data.data !== ''
				&& jsignpdf.data.ocs.data.data !== ''
				&& libresign_cli.data.ocs.data.data !== ''
			) {
				this.submitLabel = t('libresign', 'Binaries downloaded')
				return
			}
			this.formDisabled = false
		},
		async downloadBinaries() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Downloading binaries')
			try {
				const response = await axios.get(
					generateUrl('/apps/libresign/api/0.1/admin/download-binaries')
				)

				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.submitLabel = t('libresign', 'Binaries downloaded')

				return
			} catch (e) {
				console.error(e)
				if (e.response.data.message) {
					showError(t('libresign', 'Could not download binaries.') + '\n' + e.response.data.message)
				} else {
					showError(t('libresign', 'Could not download binaries.'))
				}
				this.submitLabel = t('libresign', 'Download binaries')

			}
			this.formDisabled = false
		},
	},
}
</script>
