<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch" :checked="envelopeEnabled"
			@update:checked="(val) => { envelopeEnabled = val; saveEnvelopeEnabled() }">
			{{ t('libresign', 'Enable envelopes (group multiple files into one signing flow)') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'EnvelopeSettings',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			name: t('libresign', 'Envelopes'),
			description: t('libresign', 'Enable or disable the envelopes feature. When enabled, users can group several files into an envelope and manage them as a single signing process.'),
			envelopeEnabled: true,
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			try {
				const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/envelope_enabled'))
				const value = response?.data?.ocs?.data?.data
				this.envelopeEnabled = ['true', true, '1', 1].includes(value)
			} catch (e) {
				// Default to true when not set
				this.envelopeEnabled = true
			}
		},
		saveEnvelopeEnabled() {
			OCP.AppConfig.setValue('libresign', 'envelope_enabled', this.envelopeEnabled ? 1 : 0, {
				success: () => {
					emit('envelope:changed')
				},
			})
		},
	},
}
</script>
