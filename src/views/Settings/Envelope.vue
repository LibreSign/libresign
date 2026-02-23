<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Envelopes')"
		:description="t('libresign', 'Enable or disable the envelopes feature. When enabled, users can group several files into an envelope and manage them as a single signing process.')">
		<NcCheckboxRadioSwitch type="switch" :checked="envelopeEnabled"
			@update:checked="(val) => { envelopeEnabled = val; saveEnvelopeEnabled() }">
			{{ t('libresign', 'Enable envelopes (group multiple files into one signing flow)') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'EnvelopeSettings',
	components: {
		NcSettingsSection,
	},
	data() {
		return {
			envelopeEnabled: loadState('libresign', 'envelope_enabled', true),
		}
	},
	methods: {
		t,
		saveEnvelopeEnabled() {
			OCP.AppConfig.setValue('libresign', 'envelope_enabled', this.envelopeEnabled ? '1' : '0', {
				success: () => {
					emit('envelope:changed')
				},
			})
		},
	},
}
</script>
