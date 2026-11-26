<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="my-certificates">
			<NcSettingsSection
				:name="pageTitle"
				:description="pageDescription">
				<ManagePassword />
			</NcSettingsSection>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import ManagePassword from '../Account/partials/ManagePassword.vue'

defineOptions({
	name: 'MyCertificates',
})

// TRANSLATORS Title of the settings page holding the certificate actions of the signed-in user.
const pageTitle = t('libresign', 'My certificates')
// TRANSLATORS Description of the settings page holding the certificate actions of the signed-in user. The certificate is the one used to sign documents.
const pageDescription = t('libresign', 'Upload, create, read, delete or change the password of the certificate used to sign your documents.')

defineExpose({
	pageTitle,
	pageDescription,
})
</script>

<style lang="scss" scoped>
.my-certificates {
	padding: 24px;
}
</style>
