<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<SupportProject />
		<CertificateEngine />
		<SignatureEngine />
		<DownloadBinaries />
		<ConfigureCheck />
		<RootCertificateCfssl />
		<RootCertificateOpenSsl />
		<IdentificationFactors />
		<ExpirationRules />
		<Validation />
		<CrlValidation />
		<SettingsPolicyWorkbench />
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

<script setup lang="ts">
import AllowedGroups from './AllowedGroups.vue'
import CertificateEngine from './CertificateEngine.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import CollectMetadata from './CollectMetadata.vue'
import CrlValidation from './CrlValidation.vue'
import DefaultUserFolder from './DefaultUserFolder.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import Envelope from './Envelope.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationDocuments from './IdentificationDocuments.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import Reminders from './Reminders.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import SignatureEngine from './SignatureEngine.vue'
import SettingsPolicyWorkbench from './PolicyWorkbench/RealPolicyWorkbench.vue'
import SignatureHashAlgorithm from './SignatureHashAlgorithm.vue'
import SignatureStamp from './SignatureStamp.vue'
import SigningMode from './SigningMode.vue'
import SupportProject from './SupportProject.vue'
import TSA from './TSA.vue'
import Validation from './Validation.vue'

defineOptions({
	name: 'Settings',
	components: {
		AllowedGroups,
		CertificateEngine,
		ConfigureCheck,
		CollectMetadata,
		CrlValidation,
		DefaultUserFolder,
		DownloadBinaries,
		Envelope,
		ExpirationRules,
		IdentificationDocuments,
		IdentificationFactors,
		LegalInformation,
		Reminders,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SettingsPolicyWorkbench,
		SignatureEngine,
		SignatureHashAlgorithm,
		SignatureStamp,
		SigningMode,
		SupportProject,
		TSA,
		Validation,
	},
})
</script>
