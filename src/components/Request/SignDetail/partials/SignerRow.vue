<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem v-bind="{ ...$attrs, to, 'counter-number': hasElement ? '📎' : undefined }"
		:name="displayName"
		:details="signDate"
		:class="`signer-row signer-row-${status}`"
		@click="signerClickAction">
		<template #icon>
			<NcAvatar is-no-user
				:size="44"
				:user="signer.email"
				:display-name="displayName" />
		</template>
		<template #subname>
			<span class="signer-status">{{ status }}</span>
		</template>
		<template v-if="$slots.actions" #actions>
			<slot name="actions" />
		</template>
	</NcListItem>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

import { emit } from '@nextcloud/event-bus'
import Moment from '@nextcloud/moment'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import type { SignerDetailRecord } from '../../../../types/index'

defineOptions({
	name: 'SignerRow',
	inheritAttrs: false,
})

const props = withDefaults(defineProps<{
	signer: SignerDetailRecord
	elementId?: number
	to?: Record<string, unknown>
	event?: string
}>(), {
	elementId: undefined,
	to: undefined,
	event: '',
})

const displayName = computed(() => {
	if (props.signer.displayName) {
		return props.signer.displayName
	}

	if (props.signer.email) {
		return props.signer.email
	}

	return t('libresign', 'Account does not exist')
})

const status = computed(() => (props.signer.signed ? 'signed' : 'pending'))

const signDate = computed(() => (
	props.signer.signed
		? Moment(props.signer.signed, 'YYYY-MM-DD').toDate()
		: ''
))

const hasElement = computed(() => (props.elementId || 0) > 0)

function signerClickAction() {
	emit(props.event, props.signer)
}
</script>

<style>
	.signer-row-signed .signer-status {
		font-weight: bold;
	}

	.signer-row-pending .signer-status {
		color: var(--color-warning, #eca700)
	}
</style>
