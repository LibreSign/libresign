<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name">
		<CertificateEngine />
		<DownloadBinaries />
		<ConfigureCheck />
		<RootCertificateCfssl />
		<RootCertificateOpenSsl />
		<IdentificationFactors />
		<ExpirationRules />
		<Validation />
		<AllowedGroups />
		<LegalInformation />
		<IdentificationDocuments />
		<CollectMetadata />
		<DefaultUserFolder />
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'

import AllowedGroups from './AllowedGroups.vue'
import CertificateEngine from './CertificateEngine.vue'
import CollectMetadata from './CollectMetadata.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import DefaultUserFolder from './DefaultUserFolder.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationDocuments from './IdentificationDocuments.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import Validation from './Validation.vue'

export default {
	name: 'Settings',
	components: {
		NcSettingsSection,
		CertificateEngine,
		DownloadBinaries,
		ConfigureCheck,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		IdentificationFactors,
		ExpirationRules,
		Validation,
		AllowedGroups,
		LegalInformation,
		IdentificationDocuments,
		CollectMetadata,
		DefaultUserFolder,
	},
	data() {
		return {
			name: t('libresign', 'LibreSign'),
		}
	},
}

</script>
