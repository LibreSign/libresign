<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Certificate engine')"
		:description="t('libresign', 'Select the certificate engine to generate the root certificate')">
		<div class="certificate-engine-content">
			<NcSelect input-id="certificateEngine"
				:aria-label-combobox="t('libresign', 'Select the certificate engine to generate the root certificate')"
				:clearable="false"
				v-model="selectedOption"
				:options="options"
				@update:modelValue="saveEngine" />
		</div>
	</NcSettingsSection>
</template>
<script>
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

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
		return { t, configureCheckStore }
	},
	data() {
		return {
			selectedEngineId: loadState('libresign', 'certificate_engine'),
		}
	},
	computed: {
		options() {
			return [
				{ id: 'cfssl', label: 'CFSSL' },
				{ id: 'openssl', label: 'OpenSSL' },
				{ id: 'none', label: t('libresign', 'I will not use root certificate') },
			]
		},
		selectedOption: {
			get() {
				return this.options.find(opt => opt.id === this.selectedEngineId) || null
			},
			set(value) {
				this.selectedEngineId = value?.id || 'none'
			},
		},
	},
	methods: {
		async saveEngine(selected) {
			const selectedId = selected?.id || 'none'
			const result = await this.configureCheckStore.saveCertificateEngine(selectedId)
			if (result.success) {
				emit('libresign:certificate-engine:changed', result.engine)
			}
		},
	},
}
</script>
