<template>
	<SettingsSection :title="title" :description="description">
		<div class="settings-section">
		<input type="button"
			class="primary"
			:value="labelDownloadAllBinaries"
			:disabled="formDisabled"
			@click="downloadAllBinaries">
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
		title: t('libresign', 'Dependencies'),
		description: t('libresign', 'Binaries required to work. Could be near by 340Mb to download, wait a moment.'),
		labelDownloadAllBinaries: t('libresign', 'Download binaries'),
		formDisabled: true,
		checklist: {
			java: false,
			jsignpdf: false,
			libresign_cli: false
		}
	}),
	created() {
		this.isBinariesInstalled();
	},
	methods: {
		async isBinariesInstalled() {
			this.isJavaInstalled()
			this.isJsignpdfInstalled()
			this.isLibresignInstalled()
			if (this.checklist.java
				&& this.checklist.jsignpdf
				&& this.checklist.libresign_cli
			) {
				this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')
				return
			}
			this.formDisabled = false
		},
		async isJavaInstalled() {
			const java = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/java_path', {})
			this.checklist.java = java.data.ocs.data.data !== ''
		},
		async isJsignpdfInstalled() {
			const jsignpdf = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/jsignpdf_jar_path', {})
			this.checklist.jsignpdf = jsignpdf.data.ocs.data.data !== ''
		},
		async isLibresignInstalled() {
			const libresign_cli = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + '/config/apps/libresign/libresign_cli_path', {})
			this.checklist.libresign_cli = libresign_cli.data.ocs.data.data !== ''
		},
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
				this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')

				return
			} catch (e) {
				console.error(e)
				if (e.response.data.message) {
					showError(t('libresign', 'Could not download binaries.') + '\n' + e.response.data.message)
				} else {
					showError(t('libresign', 'Could not download binaries.'))
				}
				this.labelDownloadAllBinaries = t('libresign', 'Download binaries')

			}
			this.formDisabled = false
		},
	},
}
</script>
