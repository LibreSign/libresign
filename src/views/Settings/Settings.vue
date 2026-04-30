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
		<CrlValidation />
		<SettingsPolicyWorkbench />
		<SigningMode v-if="false" />
		<LegalInformation />
		<SignatureHashAlgorithm />
		<DefaultUserFolder />
		<Envelope />
		<Reminders />
		<TSA />
	</div>
</template>

<script setup lang="ts">
import CertificateEngine from './CertificateEngine.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import CrlValidation from './CrlValidation.vue'
import DefaultUserFolder from './DefaultUserFolder.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import Envelope from './Envelope.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import Reminders from './Reminders.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import SignatureEngine from './SignatureEngine.vue'
import SettingsPolicyWorkbench from './PolicyWorkbench/RealPolicyWorkbench.vue'
import SignatureHashAlgorithm from './SignatureHashAlgorithm.vue'
import SigningMode from './SigningMode.vue'
import SupportProject from './SupportProject.vue'
import TSA from './TSA.vue'

defineOptions({
	name: 'Settings',
	components: {
		CertificateEngine,
		ConfigureCheck,
		CrlValidation,
		DefaultUserFolder,
		DownloadBinaries,
		Envelope,
		ExpirationRules,
		IdentificationFactors,
		LegalInformation,
		Reminders,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SettingsPolicyWorkbench,
		SignatureEngine,
		SignatureHashAlgorithm,
		SigningMode,
		SupportProject,
		TSA,
	},
})
</script>
