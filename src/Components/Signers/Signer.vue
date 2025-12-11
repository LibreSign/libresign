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
			<div class="signer-subname">
				<NcChip v-for="method in identifyMethodsNames"
					:key="method"
					:text="method"
					:no-close="true" />
				<NcChip :text="signer.statusText"
					:type="chipType"
					:icon-path="statusIconPath"
					:no-close="true"
					class="signer-status-chip" />
			</div>
		</template>
		<template #extra>
			<div v-if="showDragHandle" class="signer-extra">
				<div class="drag-handle-wrapper">
					<DragVertical :size="20"
						class="drag-handle"
						:title="t('libresign', 'Drag to reorder')" />
				</div>
			</div>
		</template>
		<template #actions>
			<slot name="actions" :closeActions="closeActions" />
		</template>
	</NcListItem>
</template>
<script>
import { mdiCheckCircle, mdiClockOutline, mdiCircleOutline } from '@mdi/js'
import DragVertical from 'vue-material-design-icons/DragVertical.vue'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcListItem from '@nextcloud/vue/components/NcListItem'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Signer',
	components: {
		NcListItem,
		NcAvatar,
		NcChip,
		DragVertical,
	},
	props: {
		signerIndex: {
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
		return {
			filesStore,
			mdiCheckCircle,
			mdiClockOutline,
			mdiCircleOutline,
		}
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			signatureFlow: loadState('libresign', 'signature_flow', 'parallel'),
		}
	},
	computed: {
		signer() {
			const file = this.filesStore.getFile()
			return file?.signers?.[this.signerIndex]
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
		signerStatus() {
			return this.signer.status
		},
		chipType() {
			switch (this.signerStatus) {
			case 2: // SIGNED
				return 'success'
			case 1: // ABLE_TO_SIGN (pending)
				return 'warning'
			case 0: // DRAFT
			default:
				return 'secondary'
			}
		},
		statusIconPath() {
			switch (this.signerStatus) {
			case 2: // SIGNED
				return this.mdiCheckCircle
			case 1: // ABLE_TO_SIGN (pending)
				return this.mdiClockOutline
			case 0: // DRAFT
			default:
				return this.mdiCircleOutline
			}
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
.signer-subname {
	display: flex;
	align-items: center;
	gap: 4px;
	flex-wrap: wrap;
}

.signer-status-chip {
	flex-shrink: 0;
}

.signer-extra {
	display: flex;
	align-items: center;
}

.drag-handle-wrapper {
	display: flex;
	align-items: center;
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
