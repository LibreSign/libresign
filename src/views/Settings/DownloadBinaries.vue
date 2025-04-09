<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<NcNoteCard v-if="errors.length > 0" type="error" heading="Error">
			<p v-for="error in errors" :key="error">
				{{ error }}
			</p>
		</NcNoteCard>
		<NcButton class="primary"
			type="submit"
			variant="primary"
			:disabled="configureCheckStore.downloadInProgress"
			@click="installAndValidate">
			<template #icon>
				<NcLoadingIcon v-if="configureCheckStore.downloadInProgress" :size="20" />
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
import { set } from 'vue'

import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

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
			errors: [],
			downloadStatus: {
				java: 0,
				jsignpdf: 0,
				cfssl: 0,
			},
		}
	},
	computed: {
		labelDownloadAllBinaries() {
			if (this.configureCheckStore.state === 'in progress') {
				return t('libresign', 'Loading …')
			} else if (this.configureCheckStore.state === 'downloading binaries') {
				return t('libresign', 'Downloading binaries')
			} else if (this.configureCheckStore.state === 'need download') {
				return t('libresign', 'Download binaries')
			} else if (this.configureCheckStore.state === 'done') {
				return t('libresign', 'Validate setup')
			}
			return t('libresign', 'Download binaries')
		},
		description() {
			if (this.configureCheckStore.state === 'downloading binaries'
				|| this.configureCheckStore.state === 'need download'
			) {
				return t('libresign', 'Binaries required to work. Download size could be nearly {size}, please wait a moment.', { size: '186MB' })
			}
			return ''
		},
	},
	methods: {
		installAndValidate() {
			const self = this
			const updateEventSource = new OC.EventSource(generateOcsUrl('/apps/libresign/api/v1/admin/install-and-validate'))
			set(this.configureCheckStore, 'state', 'in progress')
			set(this.configureCheckStore, 'downloadInProgress', true)
			this.errors = []
			updateEventSource.listen('total_size', function(message) {
				set(self.configureCheckStore, 'state', 'downloading binaries')
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
				set(self.configureCheckStore, 'state', 'need download')
			})
			updateEventSource.listen('done', function() {
				self.downloadStatus = {}
				set(self.configureCheckStore, 'state', 'done')
				set(self.configureCheckStore, 'downloadInProgress', false)
			})
		},
	},
}
</script>
