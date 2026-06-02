<template>
	<div v-if="shouldShow" class="workflow-signature-positions">

		<div class="workflow-signature-positions__body">
			<div class="workflow-signature-positions__icon-wrap">
				<NcIconSvgWrapper :path="mdiVectorSquareEdit" :size="18" />
			</div>

			<div class="workflow-signature-positions__text">
				<span class="workflow-signature-positions__eyebrow">
					{{ t('libresign', 'Signature fields') }}
				</span>

				<p class="workflow-signature-positions__summary">
					{{ summaryLine }}
				</p>
			</div>
		</div>

		<NcButton v-if="!isBlocked" class="workflow-signature-positions__btn" variant="tertiary" size="small"
			@click="$emit('edit-positions')">

			<template #icon>
				<NcIconSvgWrapper :path="mdiPencilOutline" :size="16" />
			</template>

			{{ t('libresign', 'Edit') }}
		</NcButton>

		<div v-else class="workflow-signature-positions__locked">
			{{ lockedMessage }}
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiPencilOutline,
	mdiVectorSquareEdit,
} from '@mdi/js'

import { FILE_STATUS } from '../../../constants'

defineOptions({ name: 'WorkflowSignatureSetup' })

const props = defineProps<{
	file: any | null
}>()

console.log('WorkflowSignatureSetup mounted', props.file)

defineEmits<{
	(e: 'edit-positions'): void
}>()

function isSignerSigned(signer: any): boolean {
	if (Array.isArray(signer?.signed)) return signer.signed.length > 0
	return Boolean(signer?.signed)
}

const isBlocked = computed(() => {
	const status = props.file?.status
	return (
		status === FILE_STATUS.SIGNED
		|| status === FILE_STATUS.SIGNING_IN_PROGRESS
	)
})

const lockedMessage = computed(() => {
	const status = props.file?.status

	if (status === FILE_STATUS.SIGNED) {
		return t('libresign', 'Fields finalized')
	}

	if (status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		return t('libresign', 'Signing in progress')
	}

	return t('libresign', 'Fields locked')
})

const hasPositions = computed(() => {
	console.log('Checking visible elements:', props.file?.visibleElements)
	return (props.file?.visibleElements?.length ?? 0) > 0
})

const hasUnsignedSigners = computed(() => {
	const signers = props.file?.signers ?? []
	const unsignedSigners = signers.filter((s: any) => !isSignerSigned(s))
	console.log('Unsigned signers:', unsignedSigners)
	return unsignedSigners.length > 0
})

/**
 * Show the card only when:
 *  - at least one position has been placed
 *  - at least one signer hasn't signed yet (still editable)
 *  - the document isn't fully signed / in-progress
 */
const shouldShow = computed(() =>
	hasPositions.value,
)

const fieldCount = computed(() =>
	props.file?.visibleElements?.length ?? 0,
)

const signerCount = computed(() => {
	const visibleElements = props.file?.visibleElements ?? []
	const ids = new Set(
		visibleElements
			.map((el: any) => el?.signRequestId)
			.filter(Boolean),
	)
	return ids.size
})

const summaryLine = computed(() => {
	const fields = fieldCount.value
	const signers = signerCount.value

	if (signers > 1) {
		return t(
			'libresign',
			'{fields} fields across {signers} signers',
			{ fields, signers },
		)
	}

	return t(
		'libresign',
		'{fields} fields configured',
		{ fields },
	)
})
</script>

<style scoped lang="scss">
.workflow-signature-positions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;

	padding: 12px 14px;

	border-radius: 14px;

	border: 1px solid rgba(34, 197, 94, 0.14);

	background: linear-gradient(160deg,
			rgba(34, 197, 94, 0.07),
			rgba(34, 197, 94, 0.03));

	transition:
		border-color 180ms ease,
		box-shadow 180ms ease,
		transform 180ms ease;

	&:hover {
		border-color: rgba(34, 197, 94, 0.22);
		box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
		transform: translateY(-1px);
	}
}

/* ── body ──────────────────────────────────────────────────────────────────── */

.workflow-signature-positions__body {
	display: flex;
	align-items: center;
	gap: 12px;

	min-width: 0;
}

.workflow-signature-positions__icon-wrap {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 36px;
	height: 36px;

	flex-shrink: 0;

	border-radius: 10px;

	background: linear-gradient(160deg,
			rgba(34, 197, 94, 0.16),
			rgba(34, 197, 94, 0.08));

	color: #15803d;

	border: 1px solid rgba(34, 197, 94, 0.10);
}

.workflow-signature-positions__text {
	display: flex;
	flex-direction: column;
	gap: 2px;

	min-width: 0;
}

.workflow-signature-positions__eyebrow {
	font-size: 10px;
	font-weight: 800;
	letter-spacing: 0.08em;
	text-transform: uppercase;

	color: var(--color-text-maxcontrast);
	opacity: 0.65;
}

.workflow-signature-positions__summary {
	margin: 0;

	font-size: 13px;
	font-weight: 600;
	line-height: 1.3;

	color: var(--color-main-text);

	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* ── action button ─────────────────────────────────────────────────────────── */

.workflow-signature-positions__btn {
	flex-shrink: 0;

	border: 1px solid var(--color-border) !important;

	transition:
		border-color 180ms ease,
		transform 180ms ease;

	&:hover:not(:disabled) {
		border-color: rgba(34, 197, 94, 0.22) !important;
		transform: translateY(-1px);
	}
}

/* ── locked message ─────────────────────────────────────────────────────────── */
.workflow-signature-positions__locked {
	display: flex;
	align-items: center;

	flex-shrink: 0;

	padding: 6px 10px;

	border-radius: 999px;

	font-size: 12px;
	font-weight: 600;

	color: var(--color-text-maxcontrast);

	background: rgba(15, 23, 42, 0.05);

	border: 1px solid rgba(15, 23, 42, 0.06);

	white-space: nowrap;
}

/* ── mobile ────────────────────────────────────────────────────────────────── */

@media (max-width: 512px) {
	.workflow-signature-positions {
		padding: 10px 12px;
		border-radius: 12px;
	}

	.workflow-signature-positions__eyebrow {
		display: none;
	}
}
</style>
