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
		<SettingsPolicyWorkbench />
		<SigningMode v-if="false" />
		<TSA />
	</div>
</template>

<script setup lang="ts">
import CertificateEngine from './CertificateEngine.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import SettingsPolicyWorkbench from './PolicyWorkbench/RealPolicyWorkbench.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import SignatureEngine from './SignatureEngine.vue'
import SigningMode from './SigningMode.vue'
import SupportProject from './SupportProject.vue'
import TSA from './TSA.vue'

defineOptions({
	name: 'Settings',
	components: {
		CertificateEngine,
		ConfigureCheck,
		DownloadBinaries,
		IdentificationFactors,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		SettingsPolicyWorkbench,
		SignatureEngine,
		SigningMode,
		SupportProject,
		TSA,
	},
})
</script>
