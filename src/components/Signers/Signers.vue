<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<draggable v-if="isOrderedNumeric && canReorder"
		v-model="sortableSigners"
		item-key="localKey"
		tag="ul"
		handle=".list-item"
		class="signers-list"
		chosenClass="signer-dragging"
		dragClass="signer-drag-ghost"
		@end="onDragEnd">
		<template #item="{ element: signer }">
			<Signer
				:signer="signer"
				:event="event"
				:draggable="!signer.signed">
				<template #actions="{closeActions}">
					<slot name="actions" :signer="signer" :closeActions="closeActions" />
				</template>
			</Signer>
		</template>
	</draggable>
	<ul v-else>
		<Signer v-for="signer in signers"
			:key="signer.localKey"
			:signer="signer"
			:event="event">
			<template #actions="{closeActions}">
				<slot name="actions" :signer="signer" :closeActions="closeActions" />
			</template>
		</Signer>
	</ul>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import draggable from 'vuedraggable'

import Signer from './Signer.vue'
import { useFilesStore } from '../../store/files.js'
import type { SignatureFlowValue } from '../../types/index'

defineOptions({
	name: 'Signers',
})

type FilesStoreContract = ReturnType<typeof useFilesStore>
type SelectedFile = ReturnType<FilesStoreContract['getFile']>
type SignerListItem = NonNullable<NonNullable<SelectedFile['signers']>[number]>

function normalizeSignatureFlow(flow: SelectedFile['signatureFlow']): SignatureFlowValue | string | null | undefined {
	if (typeof flow === 'number') {
		const flowMap: Record<number, SignatureFlowValue> = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
		return flowMap[flow]
	}
	return flow
}

const props = withDefaults(defineProps<{
	event?: string
}>(), {
	event: '',
})

const emit = defineEmits<{
	(e: 'signing-order-changed'): void
}>()

const filesStore = useFilesStore()

const signers = computed<SignerListItem[] | undefined>(() => {
	const file = filesStore.getFile()
	return file?.signers ?? undefined
})

const sortableSigners = computed<SignerListItem[] | undefined>({
	get() {
		return signers.value
	},
	set(value) {
		const file = filesStore.getFile()
		if (file) {
			file.signers = value
		}
	},
})

const isOrderedNumeric = computed(() => {
	const flow = normalizeSignatureFlow(filesStore.getFile()?.signatureFlow)
	return flow === 'ordered_numeric'
})

const canReorder = computed(() => filesStore.canSave() && (signers.value?.length || 0) > 1)

function onDragEnd(evt: { oldIndex: number; newIndex: number }) {
	const { oldIndex, newIndex } = evt
	if (oldIndex === newIndex) {
		return
	}

	const file = filesStore.getFile()
	file?.signers?.forEach((signer, index) => {
		signer.signingOrder = index + 1
	})

	emit('signing-order-changed')
}

defineExpose({
	signers,
	sortableSigners,
	isOrderedNumeric,
	canReorder,
	onDragEnd,
})
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

</style>
