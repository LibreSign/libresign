<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem :name="signer.displayName"
		:force-display-actions="true"
		@click="signerClickAction">
		<template #icon>
			<NcAvatar :size="44" :display-name="signer.displayName" />
		</template>
		<template #subname>
			<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
		</template>
		<slot slot="actions" name="actions" />
		<template #indicator>
			<CheckboxBlankCircle :size="16"
				:fill-color="statusColor"
				:title="statusText" />
		</template>
	</NcListItem>
</template>
<script>
import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import Moment from '@nextcloud/moment'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'

import Bullet from '../Bullet/Bullet.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Signer',
	components: {
		NcListItem,
		NcAvatar,
		CheckboxBlankCircle,
		Bullet,
	},
	props: {
		currentSigner: {
			type: Number,
			required: true,
		},
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign', false),
		}
	},
	computed: {
		signer() {
			return this.filesStore.getFile().signers[this.currentSigner]
		},
		identifyMethodsNames() {
			return this.signer.identifyMethods.map(method => method.method)
		},
		statusColor() {
			if (this.signer.signed) {
				return '#008000'
			}
			// Pending
			if (this.signer.signRequestId) {
				return '#d67335'
			}
			// Draft, not saved
			return '#dbdbdb'
		},
		statusText() {
			if (this.signer.signed) {
				return t('libresign', 'signed at {date}', {
					date: Moment(this.signer.request_signed).format('LLL'),
				})
			}
			// Pending
			if (this.signer.signRequestId) {
				return t('libresign', 'pending')
			}
			// Draft, not saved
			return t('libresign', 'draft')
		},
	},
	methods: {
		signerClickAction(signer) {
			if (!this.canRequestSign) {
				return
			}
			if (this.event.length === 0) {
				return
			}
			if (this.signer.signed) {
				return
			}
			emit(this.event, this.signer)
		},
	},
}
</script>
