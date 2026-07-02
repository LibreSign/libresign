<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		v-if="variant === 'dialog' && hasPriorityScopes"
		class="policy-workbench__table-priority-note"
		role="note"
		aria-live="polite">
		<NcIconSvgWrapper :path="mdiInformationOutline" :size="16" />
		<span class="policy-workbench__priority-content">
			<span class="policy-workbench__priority-label">{{ priorityLabel }}</span>
			<span class="policy-workbench__priority-spacer">{{ ' ' }}</span>
			<span class="policy-workbench__priority-scopes" dir="ltr">
				<template v-for="(scope, index) in normalizedScopes" :key="`${variant}-${scope}-${index}`">
					<bdi class="policy-workbench__priority-scope">{{ scope }}</bdi>
					<span v-if="index < normalizedScopes.length - 1" class="policy-workbench__priority-separator"> &gt; </span>
				</template>
			</span>
		</span>
	</div>
	<p v-else-if="hasPriorityScopes" class="policy-workbench__precedence-hint">
		<span class="policy-workbench__priority-content">
			<span class="policy-workbench__priority-label">{{ priorityLabel }}</span>
			<span class="policy-workbench__priority-spacer">{{ ' ' }}</span>
			<span class="policy-workbench__priority-scopes" dir="ltr">
				<template v-for="(scope, index) in normalizedScopes" :key="`${variant}-${scope}-${index}`">
					<bdi class="policy-workbench__priority-scope">{{ scope }}</bdi>
					<span v-if="index < normalizedScopes.length - 1" class="policy-workbench__priority-separator"> &gt; </span>
				</template>
			</span>
		</span>
	</p>
</template>

<script setup lang="ts">
import { mdiInformationOutline } from '@mdi/js'
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

const props = withDefaults(defineProps<{
	scopes?: string[]
	variant?: 'dialog' | 'editor'
}>(), {
	scopes: () => [],
	variant: 'dialog',
})

const normalizedScopes = computed(() => props.scopes
	.map((scope) => scope.trim())
	.filter((scope) => scope.length > 0))

const hasPriorityScopes = computed(() => normalizedScopes.value.length >= 2)

// TRANSLATORS Label introducing the ordered policy precedence chain that follows.
const priorityLabel = computed(() => t('libresign', 'Priority:'))
</script>

<style scoped lang="scss">
.policy-workbench__priority-content {
	display: inline;
}

.policy-workbench__priority-scopes {
	direction: ltr;
	unicode-bidi: isolate;
}

.policy-workbench__priority-scope {
	unicode-bidi: isolate;
}
</style>
