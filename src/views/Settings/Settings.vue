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
		<SignatureStamp />
		<SignatureHashAlgorithm />
		<DefaultUserFolder />
		<Reminders />
		<TSA />
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

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
import Reminders from './Reminders.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import SignatureHashAlgorithm from './SignatureHashAlgorithm.vue'
import SignatureStamp from './SignatureStamp.vue'
import TSA from './TSA.vue'
import Validation from './Validation.vue'

export default {
	name: 'Settings',
	components: {
		AllowedGroups,
		CertificateEngine,
		CollectMetadata,
		ConfigureCheck,
		DefaultUserFolder,
		DownloadBinaries,
		ExpirationRules,
		IdentificationDocuments,
		IdentificationFactors,
		LegalInformation,
		NcSettingsSection,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SignatureHashAlgorithm,
		SignatureStamp,
		TSA,
		Validation,
		Reminders,
	},
	data() {
		return {
			name: t('libresign', 'LibreSign'),
		}
	},
}

</script>
