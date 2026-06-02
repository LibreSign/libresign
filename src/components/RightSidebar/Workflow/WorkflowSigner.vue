<template>
	<div
		class="workflow-signer"
		:class="{
			'workflow-signer--interactive': isInteractive,
			'workflow-signer--signed': isSigned,
			'workflow-signer--disabled': isMethodDisabled,
			'workflow-signer--ordered': isOrderedNumeric,
		}"
		:aria-label="signerAriaLabel"
		role="button"
		tabindex="0"
		@click="handlePrimaryAction"
		@keydown.enter="handlePrimaryAction"
		@keydown.space.prevent="handlePrimaryAction">
		<!-- ========================================= -->
		<!-- DRAG HANDLE -->
		<!-- ========================================= -->
		<div
			v-if="showDragHandle"
			class="workflow-signer-drag-handle">
			<NcIconSvgWrapper
				:path="mdiDrag"
				:size="15" />
		</div>

		<!-- ========================================= -->
		<!-- ORDER -->
		<!-- ========================================= -->
		<div
			v-if="showOrder"
			class="workflow-signer-order"
			:class="{
				'workflow-signer-order--active':
					isOrderedNumeric,
			}">
			{{ signer.signingOrder || index + 1 }}
		</div>

		<!-- ========================================= -->
		<!-- AVATAR -->
		<!-- ========================================= -->
		<div
			class="workflow-signer-avatar"
			:style="avatarStyle">
			{{ initials }}
		</div>

		<!-- ========================================= -->
		<!-- INFO -->
		<!-- ========================================= -->
		<div class="workflow-signer-info">
			<div class="workflow-signer-top-row">
				<div class="workflow-signer-name-group">
					<div class="workflow-signer-name">
						{{ signer.displayName || t('libresign', 'Unknown signer') }}
					</div>

					<div
						v-if="signer.description"
						class="workflow-signer-description">
						{{ signer.description }}
					</div>
				</div>

				<div
					class="workflow-signer-status"
					:class="`workflow-signer-status--${statusVariant}`">
					<div class="workflow-signer-status-dot" />

					<span>
						{{ signer.statusText || t('libresign', 'Draft') }}
					</span>
				</div>
			</div>

			<div class="workflow-signer-email">
				{{ signer.email }}
			</div>

			<div
				v-if="identifyMethodLabels.length"
				class="workflow-signer-methods">
				<div
					v-for="method in identifyMethodLabels"
					:key="method"
					class="workflow-signer-method-chip">
					{{ method }}
				</div>
			</div>

			<div
				v-if="isMethodDisabled"
				class="workflow-signer-warning">
				<NcIconSvgWrapper
					:path="mdiAlertCircleOutline"
					:size="15" />

				<span>
					{{ t('libresign', 'Identification method disabled') }}
				</span>
			</div>
		</div>

		<!-- ========================================= -->
		<!-- ACTIONS -->
		<!-- ========================================= -->
		<div
		    v-if="canEdit"
			class="workflow-signer-actions"
			@click.stop>
			<NcActions
				:open="isActionsOpen"
				@update:open="isActionsOpen = $event"
				@close="isActionsOpen = false"
				@closed="isActionsOpen = false">
				<NcActionCaption name="Signer Actions" />
				<NcActionButton @click="$emit('customize-message', signer)" v-if="canCustomizeMessage" :close-after-click="true">
							<template #icon>
								<NcIconSvgWrapper :path="mdiMessageTextOutline" :size="15" />
							</template>
							{{ t('libresign', 'Customise message') }}
				</NcActionButton>
				<NcActionButton @click="$emit('edit', signer)" v-if="canEdit" :close-after-click="true">
					<template #icon>
						<NcIconSvgWrapper
							:path="mdiPencilOutline"
							:size="15" />
					</template>
					Edit Signer
				</NcActionButton>
				<NcActionButton @click="$emit('request-signature', signer)" v-if="canRequestSignature" :close-after-click="true">
					<template #icon>
						<NcIconSvgWrapper
							:path="mdiSend"
							:size="15" />
					</template>
					Request Signature
				</NcActionButton>
				<NcActionButton @click="$emit('send-reminder', signer)" v-if="canSendReminder" :close-after-click="true">
					<template #icon>
						<NcIconSvgWrapper
							:path="mdiBellOutline"
							:size="15" />
					</template>
					Send Reminder
				</NcActionButton>
				<NcActionButton @click="$emit('delete', signer)" v-if="canDelete" :close-after-click="true" variant="danger">
					<template #icon>
						<NcIconSvgWrapper
							:path="mdiTrashCanOutline"
							:size="15" />
					</template>
					Delete Signer
				</NcActionButton>
			</NcActions>

		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

import { emit as emitEventBus } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper
	from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiAlertCircleOutline,
	mdiBellOutline,
	mdiDrag,
	mdiPencilOutline,
	mdiSend,
	mdiTrashCanOutline,
	mdiMessageTextOutline,
} from '@mdi/js'

import {
	SIGN_REQUEST_STATUS,
} from '../../../constants'

import type {
	IdentifyMethodSetting,
} from '../../../types'

import type { EditableSignerDraft,
} from '../../../store/files'

defineOptions({
	name: 'WorkflowSigner',
})

const props = withDefaults(defineProps<{
	signer: EditableSignerDraft
	index: number

	event?: string

	isOrderedNumeric?: boolean
	showDragHandle?: boolean

	canEdit?: boolean
	canDelete?: boolean
	canRequestSignature?: boolean
	canSendReminder?: boolean
	canCustomizeMessage?: boolean

	requireRequestPermission?: boolean
}>(), {
	event: '',
	isOrderedNumeric: false,
	showDragHandle: false,
	canEdit: false,
	canDelete: false,
	canRequestSignature: false,
	canSendReminder: false,
	canCustomizeMessage: false,
	requireRequestPermission: true,
})

const emit = defineEmits<{
	(e: 'edit', signer: EditableSignerDraft): void
	(e: 'delete', signer: EditableSignerDraft): void
	(e: 'request-signature', signer: EditableSignerDraft): void
	(e: 'send-reminder', signer: EditableSignerDraft): void
	(e: 'customize-message', signer: EditableSignerDraft): void
}>()

const identifyMethodsSettings = loadState<IdentifyMethodSetting[]>(
	'libresign',
	'identify_methods',
	[],
)

const canRequestSign = loadState(
	'libresign',
	'can_request_sign',
	false,
)

const isSigned = computed(() => {
	if (Array.isArray(props.signer.signed)) {
		return props.signer.signed.length > 0
	}

	return Boolean(props.signer.signed)
})

const hasAvailableActions = computed(() => {
	return (
		props.canEdit
		|| props.canDelete
		|| props.canRequestSignature
		|| props.canSendReminder
		|| props.canCustomizeMessage
	)
})

const isInteractive = computed(() => {
	return (
		hasAvailableActions.value
		&& !isMethodDisabled.value
	)
})

const initials = computed(() => {
	const name = props.signer.displayName || ''

	return name
		.split(' ')
		.filter(Boolean)
		.map(part => part.charAt(0))
		.join('')
		.slice(0, 2)
		.toUpperCase()
})

const identifyMethodLabels = computed(() => {
	if (!props.signer.identifyMethods?.length) {
		return []
	}

	return props.signer.identifyMethods
		.map((method) => {
			const setting = identifyMethodsSettings.find(
				item => item.name === method.method,
			)

			return (
				setting?.friendly_name
				|| method.method
			)
		})
})

const isMethodDisabled = computed(() => {
	if (!props.signer.identifyMethods?.length) {
		return false
	}

	return props.signer.identifyMethods.some((method) => {
		const setting = identifyMethodsSettings.find(
			item => item.name === method.method,
		)

		return setting
			? !setting.enabled
			: false
	})
})

const statusVariant = computed(() => {
	switch (props.signer.status) {
	case SIGN_REQUEST_STATUS.SIGNED:
		return 'success'

	case SIGN_REQUEST_STATUS.ABLE_TO_SIGN:
		return 'warning'

	case SIGN_REQUEST_STATUS.DRAFT:
	default:
		return 'neutral'
	}
})

const signerAriaLabel = computed(() => {
	return t(
		'libresign',
		'Signer {name}',
		{
			name: props.signer.displayName || '',
		},
	)
})

const showOrder = computed(() => {
	return props.isOrderedNumeric
})

const avatarStyle = computed(() => {
	const backgrounds = [
		'#dbeafe',
		'#ede9fe',
		'#dcfce7',
		'#fef3c7',
		'#fee2e2',
	]

	const colors = [
		'#1d4ed8',
		'#6d28d9',
		'#15803d',
		'#b45309',
		'#b91c1c',
	]

	const colorIndex = props.index % backgrounds.length

	return {
		background: backgrounds[colorIndex],
		color: colors[colorIndex],
	}
})

// const actionsMenu = ref<any | null>(null)
const isActionsOpen = ref(false)

function handlePrimaryAction() {
	if (!isInteractive.value) {
		return
	}

	isActionsOpen.value = !isActionsOpen.value
}
</script>

<style scoped lang="scss">
.workflow-signer {
	position: relative;

	display: flex;
	align-items: center;
	gap: 14px;

	padding: 12px;

	border-radius: 12px;
	border: 1px solid var(--color-border);

	background:
		linear-gradient(
			to bottom,
			rgba(255, 255, 255, 0.96),
			rgba(255, 255, 255, 0.98)
		);

	transition:
		transform 180ms ease,
		box-shadow 180ms ease,
		border-color 180ms ease,
		background 180ms ease,
		opacity 180ms ease;
}

.workflow-signer--interactive {
	cursor: pointer;
}

.workflow-signer--interactive:hover {
	transform: translateY(-2px);

	border-color:
		rgba(0, 201, 105, 0.16);

	box-shadow:
		0 16px 36px rgba(0, 0, 0, 0.06);
}

.workflow-signer--signed {
	background:
		linear-gradient(
			to bottom,
			rgba(0, 201, 105, 0.02),
			rgba(0, 201, 105, 0.04)
		);
}

.workflow-signer--disabled {
	opacity: 0.58;
	cursor: not-allowed;
}

/* =========================================
 * DRAG HANDLE
 * ========================================= */

.workflow-signer-drag-handle {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 24px;

	opacity: 0.35;

	cursor: grab;

	transition:
		opacity 160ms ease,
		transform 160ms ease;
}

.workflow-signer:hover .workflow-signer-drag-handle {
	opacity: 0.7;
}

.workflow-signer-drag-handle:active {
	cursor: grabbing;
}

/* =========================================
 * ORDER
 * ========================================= */

.workflow-signer-order {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 36px;
	height: 36px;

	border-radius: 999px;

	border:
		1.5px solid rgba(124, 58, 237, 0.26);

	background:
		rgba(124, 58, 237, 0.04);

	color:
		rgb(91, 33, 182);

	font-size: 0.95rem;
	font-weight: 800;

	flex-shrink: 0;

	transition:
		transform 180ms ease,
		box-shadow 180ms ease,
		background 180ms ease;
}

.workflow-signer-order--active {
	background:
		radial-gradient(
			circle at center,
			rgba(167, 139, 250, 0.28),
			rgba(124, 58, 237, 0.1)
		);

	box-shadow:
		0 0 0 4px rgba(124, 58, 237, 0.08),
		0 0 18px rgba(124, 58, 237, 0.18);
}

/* =========================================
 * AVATAR
 * ========================================= */

.workflow-signer-avatar {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 52px;
	height: 52px;

	border-radius: 999px;

	font-size: 1rem;
	font-weight: 800;

	flex-shrink: 0;
}

/* =========================================
 * INFO
 * ========================================= */

.workflow-signer-info {
	display: flex;
	flex-direction: column;
	gap: 8px;

	min-width: 0;
	flex: 1;
}

.workflow-signer-top-row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.workflow-signer-name-group {
	display: flex;
	flex-direction: column;
	gap: 4px;

	min-width: 0;
}

.workflow-signer-name {
	font-size: 14px;
	font-weight: 760;

	line-height: 1.2;

	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.workflow-signer-description {
	font-size: 12px;

	color: var(--color-text-maxcontrast);

	opacity: 0.72;
}

.workflow-signer-email {
	font-size: 12px;

	color: var(--color-text-maxcontrast);

	opacity: 0.82;

	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* =========================================
 * STATUS
 * ========================================= */

.workflow-signer-status {
	display: inline-flex;
	align-items: center;
	gap: 8px;

	padding: 2px 12px;

	border-radius: 999px;

	font-size: 12px;
	font-weight: 700;

	flex-shrink: 0;
}

.workflow-signer-status-dot {
	width: 7px;
	height: 7px;

	border-radius: 999px;

	background: currentColor;
}

.workflow-signer-status--success {
	background:
		rgba(0, 201, 105, 0.12);

	color:
		rgb(18, 122, 64);
}

.workflow-signer-status--warning {
	background:
		rgba(245, 158, 11, 0.16);

	color:
		rgb(161, 92, 0);
}

.workflow-signer-status--neutral {
	background:
		rgba(0, 0, 0, 0.06);

	color:
		var(--color-text-maxcontrast);
}

/* =========================================
 * METHODS
 * ========================================= */

.workflow-signer-methods {
	display: flex;
	align-items: center;
	gap: 8px;

	flex-wrap: wrap;
}

.workflow-signer-method-chip {
	padding: 2px 10px;

	border-radius: 999px;

	background:
		rgba(0, 0, 0, 0.05);

	font-size: 12px;
	font-weight: 700;
}

/* =========================================
 * WARNING
 * ========================================= */

.workflow-signer-warning {
	display: inline-flex;
	align-items: center;
	gap: 8px;

	font-size: 12px;
	font-weight: 600;

	color:
		rgb(180, 83, 9);
}

/* =========================================
 * ACTIONS
 * ========================================= */

.workflow-signer-actions {
	display: flex;
	align-items: center;
	gap: 8px;

	opacity: 0;
	transform: translateX(8px);

	transition:
		opacity 180ms ease,
		transform 180ms ease;
}

.workflow-signer:hover .workflow-signer-actions {
	opacity: 1;
	transform: translateX(0);
}

.workflow-signer-action {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 40px;
	height: 40px;

	border: 1px solid rgba(0, 0, 0, 0.08);
	border-radius: 14px;

	background: white;

	cursor: pointer;

	transition:
		transform 160ms ease,
		background 160ms ease,
		border-color 160ms ease,
		box-shadow 160ms ease;
}

.workflow-signer-action:hover {
	transform: translateY(-1px);

	background:
		rgba(0, 201, 105, 0.06);

	border-color:
		rgba(0, 201, 105, 0.18);

	box-shadow:
		0 8px 18px rgba(0, 0, 0, 0.05);
}

.workflow-signer-action--primary {
	background:
		rgba(0, 201, 105, 0.08);

	border-color:
		rgba(0, 201, 105, 0.18);
}

.workflow-signer-action--danger:hover {
	background:
		rgba(239, 68, 68, 0.08);

	border-color:
		rgba(239, 68, 68, 0.18);
}

@media (max-width: 768px) {
	.workflow-signer {
		align-items: flex-start;
	}

	.workflow-signer-top-row {
		flex-direction: column;
	}

	.workflow-signer-actions {
		opacity: 1;
		transform: none;
	}
}
</style>
