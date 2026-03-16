<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="sectionTitle"
		:description="sectionDescription">
		<NcCheckboxRadioSwitch
			type="switch"
			v-model="enabled"
			@update:model-value="saveEnabled">
			{{ toggleLabel }}
		</NcCheckboxRadioSwitch>

		<NcNoteCard v-if="enabled && !ldapExtensionAvailable"
			type="warning"
			class="crl-note">
			{{ ldapMissingWarning }}
		</NcNoteCard>

		<NcNoteCard v-if="!enabled"
			type="warning"
			class="crl-note">
			{{ disabledWarning }}
		</NcNoteCard>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import type { AdminInitialState } from '../../types'

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string) => void
	}
}

defineOptions({
	name: 'CrlValidation',
})

const enabled = ref(loadState<AdminInitialState['crl_external_validation_enabled']>('libresign', 'crl_external_validation_enabled', true))
const ldapExtensionAvailable = ref(loadState<AdminInitialState['ldap_extension_available']>('libresign', 'ldap_extension_available', true))

const sectionTitle = computed(() => {
	return t('libresign', 'Certificate Revocation (CRL)')
})

const sectionDescription = computed(() => {
	return t('libresign', 'Controls external CRL validation when signing with personal certificates.')
})

const toggleLabel = computed(() => {
	return t('libresign', 'Validate external CRL Distribution Points')
})

const ldapMissingWarning = computed(() => {
	return t('libresign', 'The PHP LDAP extension is not installed. Users with certificates that use LDAP-based CRL Distribution Points will not be able to sign documents.')
})

const disabledWarning = computed(() => {
	return t('libresign', 'External CRL validation is disabled. Revocation of certificates from external authorities will not be checked. Certificates issued by LibreSign are not affected.')
})

function saveEnabled() {
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'crl_external_validation_enabled',
		enabled.value ? '1' : '0',
	)
}

defineExpose({
	t,
	enabled,
	ldapExtensionAvailable,
	sectionTitle,
	sectionDescription,
	toggleLabel,
	ldapMissingWarning,
	disabledWarning,
	saveEnabled,
})
</script>

<style lang="scss" scoped>
.crl-note {
	margin-top: 12px;
}
</style>
