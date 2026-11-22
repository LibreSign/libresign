<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="policies-view">
			<RealPolicyWorkbench>
				<template #how-signing-works-auxiliary="{ layout }">
					<ExternalSigningAdmin :layout="layout === 'compact' ? 'compact' : 'cards'" :adapter="externalSigningAdapter" />
				</template>
			</RealPolicyWorkbench>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import { createExternalSigningAdminOcsAdapter } from '../../services/externalSigningAdminOcs'

import RealPolicyWorkbench from '../Settings/PolicyWorkbench/Catalog/Catalog.vue'
import ExternalSigningAdmin from '../Settings/ExternalSigning/ExternalSigningAdmin.vue'

defineOptions({
	name: 'Policies',
})

const externalSigningAdapter = createExternalSigningAdminOcsAdapter()
</script>

<style scoped lang="scss">
.policies-view {
	padding: 24px;
}
</style>
