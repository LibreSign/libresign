<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="certificate-engine-content">
			<NcSelect input-id="certificateEngine"
				:aria-label-combobox="description"
				:clearable="false"
				:value="value"
				:options="options"
				@input="saveEngine" />
		</div>
	</NcSettingsSection>
</template>
<script>
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { useConfigureCheckStore } from '../../store/configureCheck.js'

export default {
	name: 'CertificateEngine',
	components: {
		NcSettingsSection,
		NcSelect,
	},
	setup() {
		const configureCheckStore = useConfigureCheckStore()
		return { configureCheckStore }
	},
	data() {
		return {
			name: t('libresign', 'Certificate engine'),
			description: t('libresign', 'Select the certificate engine to generate the root certificate'),
			value: [],
			options: [
				{ id: 'cfssl', label: 'CFSSL' },
				{ id: 'openssl', label: 'OpenSSL' },
				{ id: 'none', label: t('libresign', 'I will not use root certificate') },
			],
		}
	},
	beforeMount() {
		const currentOption = {}
		currentOption.id = loadState('libresign', 'certificate_engine')
		if (currentOption.id === 'openssl') {
			currentOption.label = 'OpenSSL'
		} else if (currentOption.id === 'cfssl') {
			currentOption.label = 'CFSSL'
		} else {
			currentOption.label = t('libresign', 'I will not use root certificate')
		}
		this.value = [currentOption]
	},
	methods: {
		saveEngine(selected) {
			this.value = selected
			OCP.AppConfig.setValue('libresign', 'certificate_engine', selected.id, {
				success: () => {
					this.configureCheckStore.checkSetup()
					emit('libresign:certificate-engine:changed', selected.id)
				},
			})
		},
	},
}
</script>
<style scoped>
.certificate-engine-content{
	display: flex;
	flex-direction: column;
}
</style>
