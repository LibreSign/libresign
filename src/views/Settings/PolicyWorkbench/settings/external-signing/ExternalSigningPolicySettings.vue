<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="canConfigure" data-cy="external-signing-policy-settings">
		<ExternalSigningAdmin :adapter="adapter" layout="compact" embedded />
	</div>
</template>

<script setup lang="ts">
import { createExternalSigningAdminOcsAdapter } from '../../../../../services/externalSigningAdminOcs'
import ExternalSigningAdmin from '../../../ExternalSigning/ExternalSigningAdmin.vue'

defineProps<{ canConfigure?: boolean }>()
const adapter = createExternalSigningAdminOcsAdapter()
</script>
