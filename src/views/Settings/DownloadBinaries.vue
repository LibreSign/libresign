<template>
	<NcSettingsSection :title="title" :description="description">
		<div class="settings-section">
		<input type="button"
			class="primary"
			:value="labelDownloadAllBinaries"
			:disabled="downloadInProgress"
			@click="downloadAllBinaries">
		</div>

		<label v-if="downloadStatus.java > 0">Java</label>
		<NcProgressBar
			:error="true"
			size="medium"
			v-if="downloadStatus.java > 0"
			:value="downloadStatus.java"/>

		<label v-if="downloadStatus.cfssl > 0">cfssl</label>
		<NcProgressBar
			:error="true"
			size="medium"
			v-if="downloadStatus.cfssl > 0"
			:value="downloadStatus.cfssl"/>

		<label v-if="downloadStatus.jsignpdf > 0">jsignpdf</label>
		<NcProgressBar
			:error="true"
			size="medium"
			v-if="downloadStatus.jsignpdf > 0"
			:value="downloadStatus.jsignpdf"/>

		<label v-if="downloadStatus.cli > 0">cli</label>
		<NcProgressBar
			:error="true"
			size="medium"
			v-if="downloadStatus.cli > 0"
			:value="downloadStatus.cli"/>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'DownloadBinaries',
	components: {
		NcSettingsSection,
		NcProgressBar,
	},
	data: () => ({
		title: t('libresign', 'Dependencies'),
		description: t('libresign', 'Binaries required to work. Could be near by 340Mb to download, wait a moment.'),
		labelDownloadAllBinaries: t('libresign', 'Download binaries'),
		downloadInProgress: false,
		downloadStatus: {
			java: 0,
			jsignpdf: 0,
			cli: 0,
			cfssl: 0,
		}
	}),
	mounted() {
		this.$root.$on('afterConfigCheck', data => {
			if (this.downloadInProgress) {
				return
			}
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
			} else {
				this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')
			}
		});
	},
	methods: {
		async downloadAllBinaries() {
			this.downloadInProgress = true
			this.labelDownloadAllBinaries = t('libresign', 'Downloading binaries')
			try {
				axios.get(
					generateUrl('/apps/libresign/api/0.1/admin/download-binaries')
				)
				.then(() => {
					this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')
					this.downloadInProgress = false
					console.debug('Done download with success')
					this.$root.$emit('configCheck');
				})
			} catch (e) {
				showError(t('libresign', 'Could not download binaries.'))
				this.downloadInProgress = false
			}
			this.pooling()
		},
		async pooling() {
			console.debug('Start pooling')
			const response = await axios.get(
				generateUrl('/apps/libresign/api/0.1/admin/download-status')
			)
			this.downloadStatus = response.data
			console.debug(['Download status:', this.downloadStatus])
			if (!this.downloadInProgress) {
				console.debug('stop pooling')
				return
			}
			const waitFor = typeof this.downloadStatus === 'object' ? 1000 : 5000
			console.debug('wait for: ' + waitFor)
			setTimeout(() => {
				this.pooling()
			}, waitFor)
			this.$root.$emit('configCheck');
		}
	},
}
</script>
