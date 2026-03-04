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
<script>
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'SignatureEngine',
	components: {
		NcSettingsSection,
		NcSelect,
	},
	setup() {
		return { t }
	},
	data() {
		return {
			selectedEngineId: loadState('libresign', 'signature_engine', 'JSignPdf'),
		}
	},
	computed: {
		options() {
			return [
				{ id: 'JSignPdf', label: 'JSignPdf' },
				{ id: 'PhpNative', label: t('libresign', 'PHP native') },
			]
		},
		selectedOption: {
			get() {
				return this.options.find((o) => o.id === this.selectedEngineId) ?? this.options[0]
			},
			set(value) {
				this.selectedEngineId = value?.id ?? 'JSignPdf'
			},
		},
	},
	methods: {
		saveEngine(selected) {
			OCP.AppConfig.setValue('libresign', 'signature_engine', selected.id, {
				success() {
					emit('libresign:signature-engine:changed', selected.id)
				},
			})
		},
	},
}
</script>
<style scoped>
.signature-engine-content {
	display: flex;
	flex-direction: column;
}
</style>
