<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<draggable v-if="isOrderedNumeric && canReorder"
		v-model="sortableSigners"
		tag="ul"
		handle=".list-item"
		class="signers-list"
		chosenClass="signer-dragging"
		dragClass="signer-drag-ghost"
		@start="onDragStart"
		@end="onDragEnd">
		<transition-group name="signer-list" tag="div">
			<Signer v-for="(signer, index) in sortableSigners"
				:key="signer.identify || index"
				:current-signer="index"
				:event="event"
				:draggable="!signer.signed">
				<template #actions="{closeActions}">
					<slot name="actions" :signer="signer" :closeActions="closeActions" />
				</template>
			</Signer>
		</transition-group>
	</draggable>
	<ul v-else>
		<Signer v-for="(signer, index) in signers"
			:key="signer.identify || index"
			:current-signer="index"
			:event="event">
			<template #actions="{closeActions}">
				<slot name="actions" :signer="signer" :closeActions="closeActions" />
			</template>
		</Signer>
	</ul>
</template>
<script>
import { loadState } from '@nextcloud/initial-state'

import draggable from 'vuedraggable'

import Signer from './Signer.vue'
import signingOrderMixin from '../../mixins/signingOrderMixin.js'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Signers',
	mixins: [signingOrderMixin],
	components: {
		Signer,
		draggable,
	},
	props: {
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
			originalOrders: [],
		}
	},
	computed: {
		signers() {
			return this.filesStore.getFile()?.signers ?? []
		},
		sortableSigners: {
			get() {
				return this.signers
			},
			set(value) {
				const file = this.filesStore.getFile()
				this.$set(file, 'signers', value)
			},
		},
		isOrderedNumeric() {
			return loadState('libresign', 'signature_flow', 'parallel') === 'ordered_numeric'
		},
		canReorder() {
			return this.filesStore.canSave() && this.signers.length > 1
		},
	},
	methods: {
		onDragStart() {
			const file = this.filesStore.getFile()
			this.originalOrders = file.signers.map(s => s.signingOrder)
		},
		onDragEnd(evt) {
			const { oldIndex, newIndex } = evt
			if (oldIndex === newIndex) {
				return
			}

			const file = this.filesStore.getFile()
			const signers = file.signers

			this.recalculateSigningOrders(signers, newIndex, this.originalOrders, oldIndex)

			const sorted = [...file.signers].sort((a, b) => {
				return (a.signingOrder || 999) - (b.signingOrder || 999)
			})
			this.$set(file, 'signers', sorted)

			this.$emit('signing-order-changed')
		},
	},
}
</script>

<style lang="scss" scoped>
.signers-list {
	list-style: none;
	padding: 0;
}

:deep(.signer-dragging) {
	opacity: 0.5;
	background: var(--color-background-hover);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

:deep(.signer-drag-ghost) {
	opacity: 0.8;
	background: var(--color-primary-element-light);
	border: 2px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
}

.signer-list {
	&-move {
		transition: transform 0.3s ease;
	}

	&-enter-active,
	&-leave-active {
		transition: all 0.3s ease;
	}

	&-enter-from,
	&-leave-to {
		opacity: 0;
		transform: translateX(30px);
	}
}
</style>
