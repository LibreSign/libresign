<template>
	<NcSettingsSection :name="name" :description="description">
		<NcNoteCard v-if="errors.length > 0" type="error" heading="Error">
			<p v-for="error in errors" :key="error">
				{{ error }}
			</p>
		</NcNoteCard>
		<NcButton class="primary"
			type="primary"
			native-type="submit"
			:disabled="downloadInProgress"
			@click="installAndValidate">
			<template #icon>
				<NcLoadingIcon v-if="downloadInProgress" :size="20" />
			</template>
			{{ labelDownloadAllBinaries }}
		</NcButton>

		<div v-for="(progress, service) in downloadStatus"
			:key="service">
			<label v-if="progress > 0">{{ service }}</label>
			<NcProgressBar v-if="progress > 0"
				size="medium"
				:value="progress" />
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { set } from 'vue'
import { useConfigureCheckStore } from '../../store/configureCheck.js'

export default {
	name: 'DownloadBinaries',
	components: {
		NcSettingsSection,
		NcLoadingIcon,
		NcButton,
		NcNoteCard,
		NcProgressBar,
	},
	setup() {
		const configureCheckStore = useConfigureCheckStore()
		return { configureCheckStore }
	},
	data() {
		return {
			name: t('libresign', 'Dependencies'),
			description: t('libresign', 'Binaries required to work. Download size could be nearly 340MB, please wait a moment.'),
			labelDownloadAllBinaries: t('libresign', 'Download binaries'),
			downloadInProgress: false,
			errors: [],
			downloadStatus: {
				java: 0,
				jsignpdf: 0,
				cfssl: 0,
			},
		}
	},
	mounted() {
		this.$root.$on('after-config-check', data => {
			if (this.downloadInProgress) {
				return
			}
			const java = data.filter((o) => o.resource === 'java' && o.status === 'error').length === 0
			const jsignpdf = data.filter((o) => o.resource === 'jsignpdf' && o.status === 'error').length === 0
			const cfssl = data.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
			if (!java
				|| !jsignpdf
				|| !cfssl
			) {
				this.changeState('need download')
			} else {
				this.changeState('done')
			}
		})
	},
	methods: {
		async downloadAllBinaries() {
			this.changeState('in progress')
			axios.get(
				generateOcsUrl('/apps/libresign/api/v1/admin/download-binaries'),
			)
				.then(() => {
					this.installAndValidate()
				})
				.catch(({ response }) => {
					showError(t('libresign', 'Could not download binaries.'))
					if (typeof response?.data === 'object' && response?.data.message.length > 0) {
						showError(t('libresign', response.data.message))
					}
					this.changeState('need download')
				})
		},
		changeState(state) {
			if (state === 'in progress') {
				this.errors = []
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
		installAndValidate() {
			const self = this
			const updateEventSource = new OC.EventSource(generateOcsUrl('/apps/libresign/api/v1/admin/install-and-validate'))
			this.changeState('in progress')
			updateEventSource.listen('total_size', function(message) {
				const downloadStatus = JSON.parse(message)
				Object.keys(downloadStatus).forEach(service => {
					set(self.downloadStatus, service, downloadStatus[service])
				})
			})
			updateEventSource.listen('configure_check', function(items) {
				set(self.configureCheckStore, 'items', items)
			})
			updateEventSource.listen('errors', function(message) {
				self.errors = JSON.parse(message)
				self.changeState('need download')
			})
			updateEventSource.listen('done', function() {
				self.downloadStatus = {}
				self.changeState('waiting check')
			})
		},
	},
}
</script>
