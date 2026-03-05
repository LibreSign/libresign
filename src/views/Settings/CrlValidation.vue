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

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'CrlValidation',
	components: {
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcSettingsSection,
	},
	data() {
		return {
			enabled: loadState('libresign', 'crl_external_validation_enabled', true),
			ldapExtensionAvailable: loadState('libresign', 'ldap_extension_available', true),
		}
	},
	computed: {
		sectionTitle() {
			// TRANSLATORS: Section title. CRL (Certificate Revocation List) is a file published by a certificate authority listing certificates that have been cancelled before their expiry date, similar to a blacklist of invalid credentials.
			return t('libresign', 'Certificate Revocation (CRL)')
		},
		sectionDescription() {
			// TRANSLATORS: Section description. A CRL Distribution Point is a web address embedded in a certificate that tells software where to download the revocation list and check whether that certificate has been cancelled.
			return t('libresign', 'Controls external CRL validation when signing with personal certificates.')
		},
		toggleLabel() {
			// TRANSLATORS: Toggle label. "CRL Distribution Points" are web addresses (URLs) embedded in the certificate that point to the revocation list file. "External" means those addresses lead to servers outside this Nextcloud instance (e.g. a government or corporate CA server).
			return t('libresign', 'Validate external CRL Distribution Points')
		},
		ldapMissingWarning() {
			// TRANSLATORS: Warning shown when CRL validation is on but the PHP LDAP extension is missing. LDAP is a network protocol used by some certificate authorities (especially government ones) to publish their revocation lists instead of a normal HTTPS address. The "extension" refers to a PHP software module that must be installed on the server.
			return t('libresign', 'The PHP LDAP extension is not installed. Users with certificates that use LDAP-based CRL Distribution Points will not be able to sign documents.')
		},
		disabledWarning() {
			// TRANSLATORS: Warning shown when the admin disables external CRL validation. "Revoked" means the certificate authority has cancelled a certificate before its expiry date, for example when a user is removed and their certificate is invalidated. This only affects certificates from external authorities that embed CRL Distribution Points. Certificates issued by LibreSign are not affected.
			return t('libresign', 'External CRL validation is disabled. Revocation of certificates from external authorities will not be checked. Certificates issued by LibreSign are not affected.')
		},
	},
	methods: {
		t,
		saveEnabled() {
			OCP.AppConfig.setValue('libresign', 'crl_external_validation_enabled', this.enabled ? '1' : '0')
		},
	},
}
</script>

<style lang="scss" scoped>
.crl-note {
	margin-top: 12px;
}
</style>
