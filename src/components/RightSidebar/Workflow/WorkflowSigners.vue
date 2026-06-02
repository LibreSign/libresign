<template>
	<div class="workflow-signers">
		<!-- ========================================= -->
		<!-- HEADER -->
		<!-- ========================================= -->
		<div class="workflow-signers-header">
			<div class="workflow-signers-title">
				{{ t('libresign', 'Signers') }}
			</div>

			<NcButton v-if="canAddSigner" variant="tertiary" size="small" class="workflow-add-signer-button"
				@click="$emit('add-signer')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" :size="18" />
				</template>

				{{ t('libresign', 'Add signer') }}
			</NcButton>
		</div>

		<!-- ========================================= -->
		<!-- SIGN ORDER -->
		<!-- ========================================= -->
		<div v-if="showSigningOrder && canManageSigners" class="workflow-sign-order" :class="{
			'workflow-sign-order--active':
				isOrderedNumeric,
		}">
			<div class="workflow-sign-order-label">
				{{ t('libresign', 'Sign in order') }}
			</div>

			<NcCheckboxRadioSwitch :model-value="isOrderedNumeric" type="switch"
				@update:modelValue="toggleSigningOrder" />
		</div>

		<!-- ========================================= -->
		<!-- DRAGGABLE -->
		<!-- ========================================= -->
		<draggable v-if="isOrderedNumeric && canReorder" v-model="sortableSigners" item-key="localKey" tag="div"
			handle=".workflow-signer-drag-handle" class="workflow-signer-list" chosenClass="workflow-signer-dragging"
			dragClass="workflow-signer-drag-ghost" animation="220" @end="onDragEnd">
			<template #item="{ element: signer, index }">
				<WorkflowSigner
					:signer="signer"
					:index="index"
					:event="event"
					:is-ordered-numeric="isOrderedNumeric"
					:show-drag-handle="!signer.signed"
					:can-edit="canEditSigner(signer)"
					:can-delete="canDeleteSigner(signer)"
					:can-request-signature="canRequestSignature(signer)"
					:can-send-reminder="canSendReminder(signer)"
					:can-customize-message="canCustomizeMessage(signer)"
					@edit="$emit('edit-signer', signer)"
					@delete="$emit('delete-signer', signer)"
					@request-signature="$emit('request-signature', signer)"
					@send-reminder="$emit('send-reminder', signer)"
					@customize-message="$emit('customize-message', signer)" />
			</template>
		</draggable>

		<!-- ========================================= -->
		<!-- STATIC -->
		<!-- ========================================= -->
		<TransitionGroup v-else name="workflow-signer-transition" tag="div" class="workflow-signer-list">
			<WorkflowSigner
			    v-for="(signer, index) in signers"
				:key="signer.localKey"
				:signer="signer" :index="index"
				:event="event"
				:is-ordered-numeric="isOrderedNumeric"
				:can-edit="canEditSigner(signer)"
				:can-delete="canDeleteSigner(signer)"
				:can-request-signature="canRequestSignature(signer)"
				:can-send-reminder="canSendReminder(signer)"
				:can-customize-message="canCustomizeMessage(signer)"
				@edit="$emit('edit-signer', signer)"
				@delete="$emit('delete-signer', signer)"
				@request-signature="$emit('request-signature', signer)"
				@send-reminder="$emit('send-reminder', signer)"
				@customize-message="$emit('customize-message', signer)" />
		</TransitionGroup>
	</div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'

import draggable from 'vuedraggable'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch
	from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper
	from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiPlus,
} from '@mdi/js'

import WorkflowSigner from './WorkflowSigner.vue'

import { useFilesStore } from '../../../store/files'
import { useWorkflowController } from '../../../composables/useWorkflowController'

import type {
	SignatureFlowValue,
} from '../../../types'

import type {
	EditableSignerDraft,
} from '../../../store/files'

defineOptions({
	name: 'WorkflowSigners',
})

function normalizeSignatureFlow(
	flow: SignatureFlowValue | number | null | undefined,
): SignatureFlowValue | null | undefined {
	if (typeof flow === 'number') {
		const flowMap: Record<number, SignatureFlowValue> = {
			0: 'none',
			1: 'parallel',
			2: 'ordered_numeric',
		}

		return flowMap[flow]
	}

	return flow
}

const props = defineProps({
	canManageSigners: {
		type: Boolean,
		default: false,
	},
	signers: {
		type: Array as PropType<EditableSignerDraft[]>,
		required: true,
	},
	event: {
		type: String,
		default: '',
	},
})

const emit = defineEmits<{
	(e: 'add-signer'): void
	(e: 'edit-signer', signer: EditableSignerDraft): void
	(e: 'delete-signer', signer: EditableSignerDraft): void
	(e: 'request-signature', signer: EditableSignerDraft): void
	(e: 'send-reminder', signer: EditableSignerDraft): void
	(e: 'customize-message', signer: EditableSignerDraft): void
	(e: 'toggle-sign-order', enabled: boolean): void
	(e: 'signing-order-changed'): void
}>()

const filesStore = useFilesStore()

const controller = useWorkflowController()

const file = computed(() => filesStore.getFile())

const isOriginalFileDeleted = computed(() => filesStore.isOriginalFileDeleted())

const sortableSigners = computed({
	get() {
		return props.signers
	},

	set(value) {
		if (file.value) {
			file.value.signers = value
		}
	},
})

const isOrderedNumeric = computed(() => {
	const flow = normalizeSignatureFlow(
		file.value?.signatureFlow,
	)

	return flow === 'ordered_numeric'
})

const canReorder = computed(() => {
	return (
		filesStore.canSave()
		&& (props.signers?.length || 0) > 1
	)
})

const showSigningOrder = computed(() => {
	return (props.signers?.length || 0) > 1
})

const canAddSigner = computed(() => {
	return filesStore.canAddSigner()
})

function toggleSigningOrder(enabled: boolean) {
	emit('toggle-sign-order', enabled)
}

function onDragEnd(evt: {
	oldIndex: number
	newIndex: number
}) {
	const {
		oldIndex,
		newIndex,
	} = evt

	if (oldIndex === newIndex) {
		return
	}

	file.value?.signers?.forEach((signer, index) => {
		signer.signingOrder = index + 1
	})

	emit('signing-order-changed')
}

function canEditSigner(signer: EditableSignerDraft) {
    if (!props.canManageSigners) {
		return false
	}

	if (isOriginalFileDeleted.value) {
		return false
	}

	return !controller.isSignerSigned(signer)
}

function canDeleteSigner(signer: EditableSignerDraft) {
	if (!props.canManageSigners) {
		return false
	}

	if (isOriginalFileDeleted.value) {
		return false
	}

	return (
		filesStore.canSave()
		&& !controller.isSignerSigned(signer)
	)
}

function canRequestSignature(signer: EditableSignerDraft) {
	if (
		isOriginalFileDeleted.value
		|| !filesStore.canRequestSign
		|| file.value?.status === 0
		|| controller.isSignerSigned(signer)
		|| !signer?.signRequestId
		|| signer?.me
		|| signer?.status !== 0
		|| !props.canManageSigners
	) {
		return false
	}

	return controller.canSignerActInOrder(signer)
}

function canSendReminder(signer: EditableSignerDraft) {
	if (
		isOriginalFileDeleted.value
		|| !filesStore.canRequestSign
		|| file.value?.status === 0
		|| controller.isSignerSigned(signer)
		|| !signer?.signRequestId
		|| signer?.me
		|| signer?.status !== 1
		|| !props.canManageSigners
	) {
		return false
	}

	return controller.canSignerActInOrder(signer)
}

function canCustomizeMessage(signer: EditableSignerDraft) {
	if (!props.canManageSigners) {
		return false
	}
	if (isOriginalFileDeleted.value) {
		return false
	}

	if (
		controller.isSignerSigned(signer)
		|| !signer.signRequestId
		|| signer.me
	) {
		return false
	}

	return controller.canSignerActInOrder(signer)
}
</script>

<style scoped lang="scss">
.workflow-signers {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.workflow-signers-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;

	& .button-vue {
		border: 1px solid var(--color-border);
	}
}

.workflow-signers-title {
	font-size: 12px;
	font-weight: 800;
	letter-spacing: 0.08em;
	text-transform: uppercase;

	color: var(--color-text-maxcontrast);

	opacity: 0.75;
}

.workflow-add-signer-button {
	flex-shrink: 0;
}

.workflow-sign-order {
	display: flex;
	align-items: center;
	justify-content: space-between;

	padding: 4px 10px;

	border-radius: 10px;

	border:
		1px solid rgba(0, 0, 0, 0.06);

	background:
		rgba(120, 120, 120, 0.04);

	transition:
		background 180ms ease,
		border-color 180ms ease,
		box-shadow 180ms ease;
}

.workflow-sign-order--active {
	border-color:
		rgba(124, 58, 237, 0.18);

	box-shadow:
		0 0 0 4px rgba(124, 58, 237, 0.05);
}

.workflow-sign-order-label {
	font-size: 14px;
	font-weight: 600;
}

.workflow-signer-list {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

/* =========================================
 * TRANSITIONS
 * ========================================= */

.workflow-signer-transition-enter-active,
.workflow-signer-transition-leave-active {
	transition:
		all 220ms cubic-bezier(0.2, 0, 0, 1);
}

.workflow-signer-transition-enter-from {
	opacity: 0;

	transform:
		translateY(10px) scale(0.985);
}

.workflow-signer-transition-leave-to {
	opacity: 0;

	transform:
		scale(0.98);
}

.workflow-signer-transition-move {
	transition:
		transform 220ms cubic-bezier(0.2, 0, 0, 1);
}

/* =========================================
 * DRAGGING
 * ========================================= */

:deep(.workflow-signer-dragging) {
	opacity: 0.45;

	transform: scale(0.985);

	filter: saturate(0.9);
}

:deep(.workflow-signer-drag-ghost) {
	border-radius: 24px;

	box-shadow:
		0 18px 40px rgba(0, 0, 0, 0.12);

	transform: rotate(-1deg);
}
</style>
