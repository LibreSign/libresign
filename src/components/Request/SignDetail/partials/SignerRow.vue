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

<script>
import { t } from '@nextcloud/l10n'

import { emit } from '@nextcloud/event-bus'
import Moment from '@nextcloud/moment'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'

export default {
	name: 'SignerRow',
	inheritAttrs: false,
	components: {
		NcListItem,
		NcAvatar,
	},
	props: {
		signer: {
			type: Object,
			required: true,
		},
		to: {
			type: Object,
			required: false,
			default: undefined,
		},
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
	computed: {
		displayName() {
			const { signer } = this

			if (signer.displayName) {
				return signer.displayName
			}

			if (signer.email) {
				return signer.email
			}

			return t('libresign', 'Account does not exist')
		},
		status() {
			const { signer } = this
			return signer.signed ? 'signed' : 'pending'
		},
		signDate() {
			const { signer } = this

			return signer.signed
				? Moment(signer.signed, 'YYYY-MM-DD').toDate()
				: ''
		},
		element() {
			return this.signer.element || {}
		},
		hasElement() {
			return this.element.elementId > 0
		},
	},
	methods: {
		t,
		signerClickAction(signer) {
			emit(this.event, this.signer)
		},
	},
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
