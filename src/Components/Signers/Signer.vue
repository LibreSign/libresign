<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem ref="listItem"
		:name="signerName"
		:counter-number="counterNumber"
		:counter-type="counterType"
		:force-display-actions="true"
		:class="signerClass"
		@click="signerClickAction">
		<template #icon>
			<NcAvatar :size="44" :display-name="signer.displayName" />
		</template>
		<template #subname>
			<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
		</template>
		<template #extra>
			<div v-if="showDragHandle" class="drag-handle-wrapper">
				<DragVertical :size="20"
					class="drag-handle"
					:title="t('libresign', 'Drag to reorder')" />
			</div>
		</template>
		<template #actions>
			<slot name="actions" :closeActions="closeActions" />
		</template>
		<template #indicator>
			<CheckboxBlankCircle :size="16"
				:fill-color="statusColor"
				:title="statusText" />
		</template>
	</NcListItem>
</template>
<script>
import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import DragVertical from 'vue-material-design-icons/DragVertical.vue'

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
		DragVertical,
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
		draggable: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			signatureFlow: loadState('libresign', 'signature_flow', 'parallel'),
		}
	},
	computed: {
		signer() {
			return this.filesStore.getFile().signers[this.currentSigner]
		},
		signerName() {
			return this.signer.displayName
		},
		counterNumber() {
			const file = this.filesStore.getFile()
			const totalSigners = file?.signers?.length || 0
			if (this.signatureFlow === 'ordered_numeric' && totalSigners > 1 && this.signer.signingOrder) {
				return this.signer.signingOrder
			}
			return null
		},
		counterType() {
			return this.counterNumber !== null ? 'highlighted' : undefined
		},
		signerClass() {
			return {
				'signer-signed': this.signer.signed,
			}
		},
		showDragHandle() {
			if (!this.draggable) {
				return false
			}
			const file = this.filesStore.getFile()
			if (!file || !file.signers) {
				return false
			}
			const totalSigners = file.signers.length
			return this.signatureFlow === 'ordered_numeric' &&
				totalSigners > 1 &&
				!this.signer.signed &&
				this.filesStore.canSave()
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
		closeActions() {
			const actionsRef = this.$refs.listItem?.$refs.actions
			if (actionsRef && typeof actionsRef.closeMenu === 'function') {
				actionsRef.closeMenu()
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.drag-handle-wrapper {
	display: flex;
	align-items: center;
	height: 100%;
}

.drag-handle {
	cursor: grab;
	color: var(--color-text-maxcontrast);
	opacity: 0.7;

	&:hover {
		opacity: 1;
	}
}

.signer-signed .drag-handle {
	cursor: not-allowed;
	opacity: 0.3;
}
</style>
