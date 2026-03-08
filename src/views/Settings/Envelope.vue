<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Envelopes')"
		:description="t('libresign', 'Enable or disable the envelopes feature. When enabled, users can group several files into an envelope and manage them as a single signing process.')">
		<NcCheckboxRadioSwitch type="switch" v-model="envelopeEnabled"
			@update:modelValue="onEnvelopeToggle">
			{{ t('libresign', 'Enable envelopes (group multiple files into one signing flow)') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

defineOptions({
	name: 'EnvelopeSettings',
})

const envelopeEnabled = ref(loadState('libresign', 'envelope_enabled', true) === true)

function saveEnvelopeEnabled() {
	OCP.AppConfig.setValue('libresign', 'envelope_enabled', envelopeEnabled.value ? '1' : '0', {
		success: () => {
			emit('envelope:changed', new CustomEvent('envelope:changed'))
		},
	})
}

function onEnvelopeToggle() {
	saveEnvelopeEnabled()
}

defineExpose({
	envelopeEnabled,
	onEnvelopeToggle,
	saveEnvelopeEnabled,
})
</script>
