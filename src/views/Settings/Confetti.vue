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
<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

defineOptions({
	name: 'ConfettiSettings',
})

const showConfetti = ref(loadState('libresign', 'show_confetti_after_signing', true) === true)

function saveShowConfetti() {
	OCP.AppConfig.setValue('libresign', 'show_confetti_after_signing', showConfetti.value ? '1' : '0')
}

defineExpose({
	showConfetti,
	saveShowConfetti,
})
</script>
