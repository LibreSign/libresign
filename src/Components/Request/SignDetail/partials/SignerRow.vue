<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem v-bind="{ to, 'counter-number': hasElement ? '📎' : undefined }"
		:name="displayName"
		:details="signDate"
		:class="`signer-row signer-row-${status}`"
		v-on="$listeners"
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
		<slot v-if="$slots.actions" slot="actions" name="actions" />
	</NcListItem>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import Moment from '@nextcloud/moment'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'

export default {
	name: 'SignerRow',
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
