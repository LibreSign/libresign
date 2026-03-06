<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Dependencies')"
		:description="description">
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
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, reactive, ref } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { useConfigureCheckStore } from '../../store/configureCheck.js'

defineOptions({
	name: 'DownloadBinaries',
})

type ConfigureCheckStore = {
	items: unknown[]
	state: string
	downloadInProgress: boolean
}

type DownloadStatus = Record<string, number>

type EventSourceInstance = {
	listen: (event: string, callback: (payload: string | unknown[]) => void) => void
}

type OCGlobal = {
	EventSource: new (url: string) => EventSourceInstance
}

const configureCheckStore = useConfigureCheckStore() as ConfigureCheckStore
const errors = ref<string[]>([])
const downloadStatus = reactive<DownloadStatus>({
	java: 0,
	jsignpdf: 0,
	cfssl: 0,
})

const labelDownloadAllBinaries = computed(() => {
	if (configureCheckStore.state === 'in progress') {
		return t('libresign', 'Loading …')
	}
	if (configureCheckStore.state === 'downloading binaries') {
		return t('libresign', 'Downloading binaries')
	}
	if (configureCheckStore.state === 'done') {
		return t('libresign', 'Validate setup')
	}
	return t('libresign', 'Download binaries')
})

const description = computed(() => {
	if (configureCheckStore.state === 'downloading binaries' || configureCheckStore.state === 'need download') {
		return t('libresign', 'Binaries required to work. Download size could be nearly {size}, please wait a moment.', { size: '186MB' })
	}
	return ''
})

function installAndValidate() {
	const updateEventSource = new (globalThis as typeof globalThis & { OC: OCGlobal }).OC.EventSource(
		generateOcsUrl('/apps/libresign/api/v1/admin/install-and-validate'),
	)

	configureCheckStore.state = 'in progress'
	configureCheckStore.downloadInProgress = true
	errors.value = []

	updateEventSource.listen('total_size', (message) => {
		configureCheckStore.state = 'downloading binaries'
		const nextDownloadStatus = JSON.parse(String(message)) as DownloadStatus
		Object.entries(nextDownloadStatus).forEach(([service, progress]) => {
			downloadStatus[service] = progress
		})
	})

	updateEventSource.listen('configure_check', (items) => {
		configureCheckStore.items = Array.isArray(items) ? items : []
	})

	updateEventSource.listen('errors', (message) => {
		errors.value = JSON.parse(String(message)) as string[]
		configureCheckStore.state = 'need download'
	})

	updateEventSource.listen('done', () => {
		Object.keys(downloadStatus).forEach((service) => {
			delete downloadStatus[service]
		})
		configureCheckStore.state = 'done'
		configureCheckStore.downloadInProgress = false
	})
}

defineExpose({
	configureCheckStore,
	errors,
	downloadStatus,
	labelDownloadAllBinaries,
	description,
	installAndValidate,
})
</script>
