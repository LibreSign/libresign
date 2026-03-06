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
<script setup lang="ts">
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { useConfigureCheckStore } from '../../store/configureCheck.js'

type EngineOption = {
	id: string
	label: string
}

type ConfigureCheckStore = {
	saveCertificateEngine: (engine: string) => Promise<{ success: boolean; engine: string }>
}

defineOptions({
	name: 'CertificateEngine',
})

const configureCheckStore = useConfigureCheckStore() as ConfigureCheckStore
const selectedEngineId = ref(loadState('libresign', 'certificate_engine'))

const options = computed<EngineOption[]>(() => {
	return [
		{ id: 'cfssl', label: 'CFSSL' },
		{ id: 'openssl', label: 'OpenSSL' },
		{ id: 'none', label: t('libresign', 'I will not use root certificate') },
	]
})

const selectedOption = computed<EngineOption | null>({
	get() {
		return options.value.find(opt => opt.id === selectedEngineId.value) || null
	},
	set(value) {
		selectedEngineId.value = value?.id || 'none'
	},
})

async function saveEngine(selected: EngineOption | null | undefined) {
	const selectedId = selected?.id || 'none'
	const result = await configureCheckStore.saveCertificateEngine(selectedId)
	if (result.success) {
		emit('libresign:certificate-engine:changed', result.engine)
	}
}

defineExpose({
	t,
	configureCheckStore,
	selectedEngineId,
	options,
	selectedOption,
	saveEngine,
})
</script>
