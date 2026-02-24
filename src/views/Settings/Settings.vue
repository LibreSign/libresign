<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
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
		<SigningMode v-if="false" />
		<AllowedGroups />
		<LegalInformation />
		<IdentificationDocuments />
		<CollectMetadata />
		<SignatureStamp />
		<SignatureHashAlgorithm />
		<DefaultUserFolder />
		<Envelope />
		<Reminders />
		<TSA />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'

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
import SigningMode from './SigningMode.vue'
import Envelope from './Envelope.vue'
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
		Reminders,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SignatureFlow,
		SignatureHashAlgorithm,
		SignatureStamp,
		SigningMode,
		Envelope,
		SupportProject,
		TSA,
		Validation,
	},
	methods: {
		t,
	},
}

</script>
