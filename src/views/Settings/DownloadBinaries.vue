<template>
	<NcSettingsSection :title="title" :description="description">
		<div class="settings-section">
			<NcButton class="primary"
				type="primary"
				native-type="submit"
				:disabled="downloadInProgress"
				@click="downloadAllBinaries">
				<template #icon>
					<NcLoadingIcon v-if="downloadInProgress" :size="20" />
				</template>
				{{ labelDownloadAllBinaries }}
			</NcButton>
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
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/dist/Components/NcButton'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'DownloadBinaries',
	components: {
		NcSettingsSection,
		NcLoadingIcon,
		NcButton,
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
				this.changeState('need download')
			} else {
				this.changeState('done')
			}
		});
	},
	methods: {
		async downloadAllBinaries() {
			this.changeState('in progress')
			try {
				axios.get(
					generateUrl('/apps/libresign/api/0.1/admin/download-binaries')
				)
				.then(() => {
					this.changeState('waiting check')
					this.$root.$emit('configCheck');
				})
			} catch (e) {
				showError(t('libresign', 'Could not download binaries.'))
				this.changeState('need download')
			}
			this.pooling()
		},
		async pooling() {
			const response = await axios.get(
				generateUrl('/apps/libresign/api/0.1/admin/download-status')
			)
			this.downloadStatus = response.data
			if (!this.downloadInProgress) {
				return
			}
			const waitFor = typeof this.downloadStatus === 'object' ? 1000 : 5000
			setTimeout(() => {
				this.pooling()
			}, waitFor)
			this.$root.$emit('configCheck');
		},
		changeState(state) {
			if (state === 'in progress') {
				this.downloadInProgress = true
				this.labelDownloadAllBinaries = t('libresign', 'Downloading binaries')
				this.description = t('libresign', 'Binaries required to work. Download size could be nearly 340MB, please wait a moment.')
			} else if (state === 'waiting check') {
				this.downloadInProgress = false
				this.labelDownloadAllBinaries = t('libresign', 'Binaries downloaded')
				this.description = t('libresign', 'Binaries required to work. Download size could be nearly 340MB, please wait a moment.')
			} else if (state === 'need download') {
				this.downloadInProgress = false
				this.labelDownloadAllBinaries = t('libresign', 'Download binaries')
				this.description = t('libresign', 'Binaries required to work. Download size could be nearly 340MB, please wait a moment.')
			} else if (state === 'done') {
				this.downloadInProgress = false
				this.labelDownloadAllBinaries = t('libresign', 'Validate setup')
				this.description = t('libresign', 'Binaries downloaded')
			}
		},
	},
}
</script>
