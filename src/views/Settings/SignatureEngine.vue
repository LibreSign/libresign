<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Signature engine')"
		:description="t('libresign', 'Select the signature engine to sign the documents')">
		<div class="signature-engine-content">
			<NcSelect input-id="signatureEngine"
				:aria-label-combobox="t('libresign', 'Select the signature engine to sign the documents')"
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

defineOptions({
	name: 'SignatureEngine',
})

type SignatureEngineOption = {
	id: string
	label: string
}

type AppConfigGlobal = {
	AppConfig: {
		setValue: (
			app: string,
			key: string,
			value: string,
			callbacks: { success: () => void }
		) => void
	}
}

const selectedEngineId = ref(loadState('libresign', 'signature_engine', 'JSignPdf'))

const options = computed<SignatureEngineOption[]>(() => [
	{ id: 'JSignPdf', label: 'JSignPdf' },
	// TRANSLATORS "Native" refers to a signature engine that runs directly with PHP, without requiring external runtimes like Java
	{ id: 'PhpNative', label: t('libresign', 'Native') },
])

const selectedOption = computed<SignatureEngineOption>({
	get() {
		return options.value.find((option) => option.id === selectedEngineId.value) ?? options.value[0]
	},
	set(value) {
		selectedEngineId.value = value?.id ?? 'JSignPdf'
	},
})

function saveEngine(selected: SignatureEngineOption) {
	;(globalThis as typeof globalThis & { OCP: AppConfigGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'signature_engine',
		selected.id,
		{
			success() {
				emit('libresign:signature-engine:changed', selected.id)
			},
		},
	)
}

defineExpose({
	selectedEngineId,
	options,
	selectedOption,
	saveEngine,
})
</script>
<style scoped>
.signature-engine-content {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}
</style>
