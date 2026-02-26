<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Confetti animation')"
		:description="t('libresign', 'Control whether a confetti animation is shown after a document is signed.')">
		<NcCheckboxRadioSwitch type="switch"
			v-model="showConfetti"
			@update:modelValue="saveShowConfetti">
			{{ t('libresign', 'Show confetti animation after signing') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>
<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'ConfettiSettings',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			showConfetti: loadState('libresign', 'show_confetti_after_signing', true) === true,
		}
	},
	methods: {
		t,
		saveShowConfetti() {
			OCP.AppConfig.setValue('libresign', 'show_confetti_after_signing', this.showConfetti ? '1' : '0')
		},
	},
}
</script>
