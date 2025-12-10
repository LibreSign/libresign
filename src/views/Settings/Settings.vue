<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name">
		<SupportProject />
		<CertificateEngine />
		<DownloadBinaries />
		<ConfigureCheck />
		<RootCertificateCfssl />
		<RootCertificateOpenSsl />
		<IdentificationFactors />
		<ExpirationRules />
		<Validation />
		<DocMDP />
		<SignatureFlow />
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
import DocMDP from './DocMDP.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationDocuments from './IdentificationDocuments.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import Reminders from './Reminders.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import SignatureFlow from './SignatureFlow.vue'
import SignatureHashAlgorithm from './SignatureHashAlgorithm.vue'
import SignatureStamp from './SignatureStamp.vue'
import SupportProject from './SupportProject.vue'
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
		DocMDP,
		DownloadBinaries,
		ExpirationRules,
		IdentificationDocuments,
		IdentificationFactors,
		LegalInformation,
		NcSettingsSection,
		Reminders,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SignatureFlow,
		SignatureHashAlgorithm,
		SignatureStamp,
		SupportProject,
		TSA,
		Validation,
	},
	data() {
		return {
			name: t('libresign', 'LibreSign'),
		}
	},
}

</script>
