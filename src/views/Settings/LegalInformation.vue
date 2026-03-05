<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Legal information')"
		:description="t('libresign', 'This information will appear on the validation page')">
		<div class="legal-information-content">
			<MarkdownEditor v-model="legalInformation"
				:label="t('libresign', 'Legal Information')"
				min-height="80px"
				:placeholder="t('libresign', 'Legal Information')"
				@update:model-value="saveLegalInformation" />
			<div v-if="legalInformation" class="legal-information-preview">
				<strong>{{ t('libresign', 'Preview') }}</strong>
				<NcRichText class="legal-information-preview-content"
					:text="legalInformation"
					:use-markdown="true" />
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import MarkdownEditor from '../../components/MarkdownEditor.vue'

export default {
	name: 'LegalInformation',
	components: {
		MarkdownEditor,
		NcRichText,
		NcSettingsSection,
	},
	data() {
		return {
			legalInformation: loadState('libresign', 'legal_information', ''),
		}
	},
	methods: {
		t,
		saveLegalInformation() {
			OCP.AppConfig.setValue('libresign', 'legal_information', this.legalInformation)
		},
	},
}
</script>
<style scoped>
.legal-information-content{
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.legal-information-preview {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.legal-information-preview-content {
	overflow-wrap: anywhere;
}
</style>
