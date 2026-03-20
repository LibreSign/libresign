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
		<DocMDP />
		<SettingsPolicyWorkbench />
		<div class="policy-workbench-frozen-preview">
			<div class="policy-workbench-frozen-preview__header">
				<div>
					<h3>{{ t('libresign', 'Frozen workbench reference') }}</h3>
					<p>{{ t('libresign', 'Comparison reference rendered from the frozen implementation.') }}</p>
				</div>
				<button
					type="button"
					data-testid="toggle-frozen-preview"
					class="policy-workbench-frozen-preview__toggle"
					@click="showFrozenPreview = !showFrozenPreview">
					{{ showFrozenPreview ? t('libresign', 'Hide reference') : t('libresign', 'Show reference') }}
				</button>
			</div>
			<FrozenSettingsPolicyWorkbench v-if="showFrozenPreview" />
		</div>
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
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'

import AllowedGroups from './AllowedGroups.vue'
import CertificateEngine from './CertificateEngine.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import CollectMetadata from './CollectMetadata.vue'
import CrlValidation from './CrlValidation.vue'
import DefaultUserFolder from './DefaultUserFolder.vue'
import DocMDP from './DocMDP.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import Envelope from './Envelope.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationDocuments from './IdentificationDocuments.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import Reminders from './Reminders.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import FrozenSettingsPolicyWorkbench from './_frozen/PolicyWorkbenchPoc/SettingsPolicyWorkbench.vue'
import SignatureEngine from './SignatureEngine.vue'
import SettingsPolicyWorkbench from './PolicyWorkbench/RealPolicyWorkbench.vue'
import SignatureHashAlgorithm from './SignatureHashAlgorithm.vue'
import SignatureStamp from './SignatureStamp.vue'
import SigningMode from './SigningMode.vue'
import SupportProject from './SupportProject.vue'
import TSA from './TSA.vue'
import Validation from './Validation.vue'

const showFrozenPreview = ref(false)

defineOptions({
	name: 'Settings',
	components: {
		AllowedGroups,
		CertificateEngine,
		ConfigureCheck,
		CollectMetadata,
		CrlValidation,
		DefaultUserFolder,
		DocMDP,
		DownloadBinaries,
		Envelope,
		ExpirationRules,
		IdentificationDocuments,
		IdentificationFactors,
		LegalInformation,
		Reminders,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		FrozenSettingsPolicyWorkbench,
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

<style scoped>
.policy-workbench-frozen-preview {
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border-maxcontrast);
}

.policy-workbench-frozen-preview__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 14px;
}

.policy-workbench-frozen-preview__toggle {
	height: 32px;
	padding: 0 12px;
	border-radius: 999px;
	border: 1px solid var(--color-border-maxcontrast);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-weight: 600;
	cursor: pointer;
}

.policy-workbench-frozen-preview h3 {
	margin: 0 0 6px;
	font-size: 16px;
	font-weight: 600;
}

.policy-workbench-frozen-preview p {
	margin: 0 0 14px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
