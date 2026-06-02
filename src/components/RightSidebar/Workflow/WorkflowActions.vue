<template>
	<div
		class="workflow-primary-action"
		:class="{
			'workflow-primary-action--ready': workflow.isReady,
			'workflow-primary-action--blocked': isBlocked,
		}">
		<!-- ========================================= -->
		<!-- ICON -->
		<!-- ========================================= -->
		<div class="workflow-primary-action-icon">
			<NcIconSvgWrapper
				:path="primaryActionIcon"
				:size="24" />
		</div>

		<!-- ========================================= -->
		<!-- CONTENT -->
		<!-- ========================================= -->
		<div class="workflow-primary-action-content">
			<div class="workflow-primary-action-title">
				{{ primaryTitle }}
			</div>

			<div class="workflow-primary-action-description">
				{{ primaryDescription }}
			</div>

			<div
				v-if="helperText"
				class="workflow-primary-action-helper">
				{{ helperText }}
			</div>
		</div>

		<!-- ========================================= -->
		<!-- ACTIONS -->
		<!-- ========================================= -->
		<div class="workflow-primary-action-buttons">
			<NcButton
				:disabled="primaryDisabled"
				:class="[
					'workflow-primary-action-button',
					{
						'workflow-primary-action-button--glow':
							!primaryDisabled,
					},
				]"
				variant="primary"
				size="small"
				wide
				@click="handlePrimaryAction">
				<template #icon>
					<NcIconSvgWrapper
						:path="primaryActionIcon"
						:size="20" />
				</template>

				{{ primaryActionLabel }}
			</NcButton>

			<NcButton
				v-if="secondaryActionLabel"
				variant="secondary"
				size="small"
				wide
				class="workflow-secondary-action-button"
				@click="handleSecondaryAction">
				{{ secondaryActionLabel }}
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper
	from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiAccountPlusOutline,
	mdiCheckCircleOutline,
	mdiDraw,
	mdiProgressClock,
	mdiSend,
	mdiSignatureFreehand,
} from '@mdi/js'

import type {
	WorkflowState,
	WorkflowPrimaryAction,
} from '../../../composables/useWorkflowState'

defineOptions({
	name: 'WorkflowActions',
})

const props = defineProps<{
	workflow: WorkflowState
	loading?: boolean
}>()

const emit = defineEmits<{
	(e: 'add-signer'): void
	(e: 'setup-positions'): void
	(e: 'request-signatures'): void
	(e: 'sign-document'): void
	(e: 'view-progress'): void
	(e: 'secondary-action'): void
}>()

const primaryAction = computed<WorkflowPrimaryAction>(() => {
	return props.workflow.primaryAction
})

const isBlocked = computed(() => {
	return (
		!props.workflow.isReady
		&& primaryAction.value !== 'request-signatures'
	)
})

const primaryDisabled = computed(() => {
	return Boolean(props.loading)
})

const primaryActionLabel = computed(() => {
	switch (primaryAction.value) {
	case 'add-signer':
		return t('libresign', 'Add signer')

	case 'setup-positions':
		return t('libresign', 'Setup signature positions')

	case 'request-signatures':
		return t('libresign', 'Request signatures')

	case 'sign-document':
		return t('libresign', 'Proceed to sign')

	case 'view-progress':
		return t('libresign', 'View signing progress')

	case 'completed':
		return t('libresign', 'Completed')

	default:
		return t('libresign', 'Continue')
	}
})

const primaryActionIcon = computed(() => {
	switch (primaryAction.value) {
	case 'add-signer':
		return mdiAccountPlusOutline

	case 'setup-positions':
		return mdiDraw

	case 'request-signatures':
		return mdiSend

	case 'sign-document':
		return mdiSignatureFreehand

	case 'view-progress':
		return mdiProgressClock

	case 'completed':
		return mdiCheckCircleOutline

	default:
		return mdiSend
	}
})

const primaryTitle = computed(() => {
	switch (primaryAction.value) {
	case 'add-signer':
		return t(
			'libresign',
			'Add participants to your workflow',
		)

	case 'setup-positions':
		return t(
			'libresign',
			'Configure signature positions',
		)

	case 'request-signatures':
		return t(
			'libresign',
			'Ready to request signatures',
		)

	case 'sign-document':
		return t(
			'libresign',
			'Your signature is required',
		)

	case 'view-progress':
		return t(
			'libresign',
			'Signing is currently in progress',
		)

	case 'completed':
		return t(
			'libresign',
			'Workflow completed',
		)

	default:
		return t(
			'libresign',
			'Continue workflow',
		)
	}
})

const primaryDescription = computed(() => {
	switch (primaryAction.value) {
	case 'add-signer':
		return t(
			'libresign',
			'Add the people who need to sign or review this document.',
		)

	case 'setup-positions':
		return t(
			'libresign',
			'Place visible signature fields before sending requests.',
		)

	case 'request-signatures':
		return t(
			'libresign',
			'Everything is configured and ready to send.',
		)

	case 'sign-document':
		return t(
			'libresign',
			'You can now review and sign the document.',
		)

	case 'view-progress':
		return t(
			'libresign',
			'Waiting for participants to complete signing.',
		)

	case 'completed':
		return t(
			'libresign',
			'All signatures have been completed successfully.',
		)

	default:
		return ''
	}
})

const helperText = computed(() => {
	if (props.workflow.isOriginalFileDeleted) {
		return t(
			'libresign',
			'The original file is no longer available.',
		)
	}

	if (props.workflow.isDocMdpProtected) {
		return t(
			'libresign',
			'This document prevents additional modifications.',
		)
	}

	if (!props.workflow.hasSigners) {
		return t(
			'libresign',
			'At least one signer is required before continuing.',
		)
	}

	if (
		props.workflow.hasSigners
		&& !props.workflow.hasVisibleElements
	) {
		return t(
			'libresign',
			'Visible signature positions must be configured.',
		)
	}

	if (props.workflow.isOrderedSigning) {
		return t(
			'libresign',
			'Signers will receive requests sequentially.',
		)
	}

	return ''
})

const secondaryActionLabel = computed(() => {
	switch (primaryAction.value) {
	case 'request-signatures':
		return t('libresign', 'Review signers')

	case 'view-progress':
		return t('libresign', 'View signers')

	default:
		return ''
	}
})

function handlePrimaryAction() {
	switch (primaryAction.value) {
	case 'add-signer':
		emit('add-signer')
		break

	case 'setup-positions':
		emit('setup-positions')
		break

	case 'request-signatures':
		emit('request-signatures')
		break

	case 'sign-document':
		emit('sign-document')
		break

	case 'view-progress':
		emit('view-progress')
		break
	}
}

function handleSecondaryAction() {
	emit('secondary-action')
}
</script>

<style scoped lang="scss">
.workflow-primary-action {
	position: relative;

	display: flex;
	flex-direction: column;
	gap: 20px;

	padding: 24px;

	border-radius: 12px;

	border:
		1px solid rgba(0, 0, 0, 0.08);

	background:
		linear-gradient(
			to bottom,
			rgba(255, 255, 255, 0.96),
			rgba(255, 255, 255, 1)
		);

	overflow: hidden;

	transition:
		border-color 220ms ease,
		box-shadow 220ms ease,
		transform 220ms ease;
}

.workflow-primary-action:hover {
	transform: translateY(-1px);

	box-shadow:
		0 18px 42px rgba(0, 0, 0, 0.06);
}

.workflow-primary-action--ready {
	border-color:
		rgba(0, 201, 105, 0.18);

	box-shadow:
		0 0 0 4px rgba(0, 201, 105, 0.04);
}

.workflow-primary-action--blocked {
	opacity: 0.96;
}

/* =========================================
 * ICON
 * ========================================= */

.workflow-primary-action-icon {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 56px;
	height: 56px;

	border-radius: 20px;

	background:
		linear-gradient(
			135deg,
			rgba(0, 201, 105, 0.12),
			rgba(0, 201, 105, 0.04)
		);

	color:
		rgb(0, 145, 76);
}

/* =========================================
 * CONTENT
 * ========================================= */

.workflow-primary-action-content {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.workflow-primary-action-title {
	font-size: 1.18rem;
	font-weight: 780;

	line-height: 1.2;
}

.workflow-primary-action-description {
	font-size: 0.98rem;
	line-height: 1.5;

	color: var(--color-text-maxcontrast);

	opacity: 0.86;
}

.workflow-primary-action-helper {
	font-size: 0.9rem;
	font-weight: 600;

	color:
		rgb(161, 92, 0);
}

/* =========================================
 * BUTTONS
 * ========================================= */

.workflow-primary-action-buttons {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.workflow-primary-action-button {
	transition:
		transform 180ms ease,
		box-shadow 180ms ease;
}

.workflow-primary-action-button:hover:not(:disabled) {
	transform: translateY(-1px);
}

.workflow-primary-action-button--glow {
	box-shadow:
		0 10px 26px rgba(0, 201, 105, 0.18);
}

.workflow-secondary-action-button {
	width: 100%;

	border-radius: 14px;
}

/* =========================================
 * MOBILE
 * ========================================= */

@media (max-width: 640px) {
	.workflow-primary-action {
		padding: 20px;
		border-radius: 24px;
	}
}
</style>
