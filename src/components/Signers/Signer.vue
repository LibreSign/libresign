<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem ref="listItem"
		:name="signerName"
		:link-aria-label="signerLinkAriaLabel"
		:counter-number="counterNumber"
		:counter-type="counterType"
		:force-display-actions="true"
		:class="signerClass"
		:title="disabledTooltip"
		:aria-disabled="isMethodDisabled || signer.signed ? true : undefined"
		@click="signerClickAction">
		<template #icon>
			<NcAvatar :size="44" :display-name="signer.displayName" aria-hidden="true" />
		</template>
		<template #subname>
			<div class="signer-subname">
				<NcChip v-for="method in identifyMethodsNames"
					:key="method"
					:text="method"
					:aria-label="t('libresign', 'Identification method: {method}', { method })"
					:no-close="true" />
				<NcChip :text="signer.statusText ?? ''"
					:variant="chipType"
					:icon-path="statusIconPath"
					:aria-label="t('libresign', 'Signer status: {status}', { status: signer.statusText ?? '' })"
					:no-close="true"
					class="signer-status-chip" />
				<span v-if="disabledTooltip" class="sr-only">{{ disabledTooltip }}</span>
			</div>
		</template>
		<template #extra>
			<div v-if="showDragHandle" class="signer-extra">
				<div class="drag-handle-wrapper">
					<NcIconSvgWrapper :path="mdiDragVertical" :size="20"
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
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import {
	mdiCheckCircle,
	mdiCircleOutline,
	mdiClockOutline,
	mdiDragVertical,
} from '@mdi/js'
import { emit as emitEventBus } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { SIGN_REQUEST_STATUS } from '../../constants.js'
import { useFilesStore } from '../../store/files.js'
import type { components } from '../../types/openapi/openapi'
import type { IdentifyMethodSetting, SignatureFlowMode } from '../../types/index'
defineOptions({
	name: 'Signer',
})

type OpenApiSigner = components['schemas']['Signer']

type SignerListItem = {
	displayName?: OpenApiSigner['displayName']
	email?: OpenApiSigner['email']
	status?: OpenApiSigner['status']
	statusText?: string
	signed?: unknown
	identifyMethods?: OpenApiSigner['identifyMethods']
	signingOrder?: OpenApiSigner['signingOrder'] | number
}

const props = withDefaults(defineProps<{
	signerIndex: number
	event?: string
	draggable?: boolean
	requireRequestPermission?: boolean
}>(), {
	event: '',
	draggable: false,
	requireRequestPermission: true,
})

const emit = defineEmits<{
	(event: 'select', signer: SignerListItem): void
}>()

const filesStore = useFilesStore()
const listItem = ref<any | null>(null)

const canRequestSign = loadState('libresign', 'can_request_sign', false)
const methods = loadState<IdentifyMethodSetting[]>('libresign', 'identify_methods', [])

const signatureFlow = computed(() => {
	const file = filesStore.getFile()
	const rawFlow = file?.signatureFlow
	let flow: SignatureFlowMode = 'parallel'
	if (typeof rawFlow === 'number') {
		const flowMap: Record<number, SignatureFlowMode> = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
		flow = flowMap[rawFlow] || 'parallel'
	} else if (rawFlow === 'none' || rawFlow === 'parallel' || rawFlow === 'ordered_numeric') {
		flow = rawFlow
	}
	return flow
})

const signer = computed<SignerListItem>(() => {
	const file = filesStore.getFile()
	return file?.signers?.[props.signerIndex] || {}
})

const signerName = computed(() => signer.value.displayName || '')

const counterNumber = computed(() => {
	const file = filesStore.getFile()
	const totalSigners = file?.signers?.length || 0
	if (signatureFlow.value === 'ordered_numeric' && totalSigners > 1 && signer.value.signingOrder) {
		return signer.value.signingOrder
	}
	return 0
})

const counterType = computed(() => (counterNumber.value > 0 ? 'highlighted' : undefined))

const isMethodDisabled = computed(() => {
	if (!signer.value.identifyMethods?.length) {
		return false
	}
	const signerMethod = signer.value.identifyMethods[0].method
	const methodConfig = methods.find(m => m.name === signerMethod)
	return methodConfig ? !methodConfig.enabled : false
})

const disabledMethodLabel = computed(() => {
	if (!signer.value.identifyMethods?.length) {
		return ''
	}
	const signerMethod = signer.value.identifyMethods[0].method
	const methodConfig = methods.find(m => m.name === signerMethod)
	return methodConfig?.friendly_name || signerMethod
})

const disabledTooltip = computed(() => {
	if (isMethodDisabled.value) {
		return t('libresign', 'This signer cannot be used because the identification method "{method}" has been disabled by the administrator.', { method: disabledMethodLabel.value })
	}
	return ''
})

const signerClass = computed(() => ({
	'signer-signed': signer.value.signed,
	'signer-method-disabled': isMethodDisabled.value,
}))

const showDragHandle = computed(() => {
	if (!props.draggable) {
		return false
	}
	if (filesStore.isOriginalFileDeleted()) {
		return false
	}
	const file = filesStore.getFile()
	if (!file || !file.signers) {
		return false
	}
	const totalSigners = file.signers.length
	return signatureFlow.value === 'ordered_numeric'
		&& totalSigners > 1
		&& !signer.value.signed
		&& filesStore.canSave()
})

const identifyMethodsNames = computed(() => {
	if (!signer.value?.identifyMethods) {
		return []
	}
	return signer.value.identifyMethods.map(method => method.method)
})

const chipType = computed(() => {
	switch (signer.value.status) {
	case SIGN_REQUEST_STATUS.SIGNED:
		return 'success'
	case SIGN_REQUEST_STATUS.ABLE_TO_SIGN:
		return 'warning'
	case SIGN_REQUEST_STATUS.DRAFT:
	default:
		return 'secondary'
	}
})

const signerLinkAriaLabel = computed(() => {
	if (signer.value.signed) {
		return t('libresign', 'Signer {name} (already signed)', { name: signerName.value })
	}
	return t('libresign', 'Edit signer {name}', { name: signerName.value })
})

const statusIconPath = computed(() => {
	switch (signer.value.status) {
	case SIGN_REQUEST_STATUS.SIGNED:
		return mdiCheckCircle
	case SIGN_REQUEST_STATUS.ABLE_TO_SIGN:
		return mdiClockOutline
	case SIGN_REQUEST_STATUS.DRAFT:
	default:
		return mdiCircleOutline
	}
})

function signerClickAction() {
	if (props.requireRequestPermission && !canRequestSign) {
		return
	}
	if (filesStore.isOriginalFileDeleted()) {
		return
	}
	if (signer.value.signed) {
		return
	}
	if (isMethodDisabled.value) {
		return
	}
	emit('select', signer.value)
	if (props.event.length > 0) {
		emitEventBus(props.event, signer.value)
	}
}

function closeActions() {
	const actionsRef = listItem.value?.$refs?.actions
	if (actionsRef && typeof actionsRef.closeMenu === 'function') {
		actionsRef.closeMenu()
	}
}

defineExpose({
	signatureFlow,
	signer,
	signerName,
	counterNumber,
	counterType,
	isMethodDisabled,
	disabledTooltip,
	showDragHandle,
	chipType,
	statusIconPath,
	signerClickAction,
	closeActions,
	filesStore,
})
</script>
<style lang="scss" scoped>
.signer-subname {
	display: flex;
	align-items: center;
	gap: 4px;
	flex-wrap: nowrap;
	min-width: 0;
	overflow: hidden;
}
.signer-status-chip {
	flex-shrink: 0;
}
:deep(.signer-subname .nc-chip) {
	flex-shrink: 1;
	min-width: 0;
}
.signer-extra {
	display: flex;
	align-items: center;
	height: 100%;
}
.drag-handle-wrapper {
	display: flex;
	align-items: center;
	height: 100%;
	margin-top: 0;
}
.drag-handle {
	cursor: grab;
	color: var(--color-text-maxcontrast);
	opacity: 0.7;
	&:hover {
		opacity: 1;
	}
}
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}
.signer-signed .drag-handle {
	cursor: not-allowed;
	opacity: 0.3;
}
.signer-method-disabled {
	opacity: 0.6;
	:deep(.list-item__wrapper) {
		cursor: not-allowed !important;
	}
	:deep(.list-item-content__wrapper) {
		position: relative;
		&::after {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: var(--color-background-dark);
			opacity: 0.2;
			pointer-events: none;
		}
	}
	:deep(.list-item-content__actions) {
		opacity: 1;
		pointer-events: auto;
	}
}
</style>
