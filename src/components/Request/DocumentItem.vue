<template>
	<div class="document-card" :class="{ active: isActive, highlight }" @click="handleSelect">
		<!-- PREVIEW -->
		<div class="doc-preview">
			<img v-if="previewUrl && backgroundFailed !== true" class="preview-image" :src="previewUrl"
				@error="backgroundFailed = true" @load="backgroundFailed = false" />

			<div v-else class="doc-preview-fallback">
				<div class="doc-icon">
					<NcIconSvgWrapper :path="icon" :size="22" />
				</div>
			</div>

			<!-- TYPE BADGE -->
			<div class="type-badge">
				<NcIconSvgWrapper :path="icon" :size="14" />
			</div>
		</div>

		<!-- CONTENT -->
		<div class="doc-content">

			<!-- STATUS -->
			<div class="doc-status">
				<span class="status-dot" :class="[statusClass, { pulse }]" />

				<Transition name="status" mode="out-in">
					<span :key="statusLabel" class="status-text">
						{{ statusLabel }}
					</span>
				</Transition>

				<span v-if="isMulti" class="doc-type">
					· Envelope
				</span>
			</div>

			<!-- NAME -->
			<div class="doc-name">
				{{ displayName }}
			</div>

			<!-- META -->
			<Transition name="meta" mode="out-in">
				<div :key="meta" class="doc-meta">
					{{ meta }}
				</div>
			</Transition>

			<!-- ENVELOPE PREVIEW -->
			<div v-if="isMulti" class="doc-subfiles">
				{{ filePreview }}
			</div>

		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiFileDocumentOutline,
	mdiFolder,
} from '@mdi/js'

import {
	generateOcsUrl,
} from '@nextcloud/router'

const props = defineProps<{
	file: any
	isActive?: boolean
}>()

const emit = defineEmits<{
	(e: 'select', id: number): void
}>()

/* ===================== */
/* PREVIEW */
/* ===================== */
const backgroundFailed = ref(false)

const previewUrl = computed(() => {
	if (backgroundFailed.value === true) {
		return null
	}

	let filePreviewUrl = ''

	if (props.file.nodeId) {
		filePreviewUrl = generateOcsUrl(
			'/apps/libresign/api/v1/file/thumbnail/{nodeId}',
			{
				nodeId: props.file.nodeId,
			},
		)
	} else if (props.file.id) {
		filePreviewUrl = generateOcsUrl(
			'/apps/libresign/api/v1/file/thumbnail/file_id/{fileId}',
			{
				fileId: props.file.id,
			},
		)
	} else {
		return null
	}

	const url = new URL(filePreviewUrl)

	url.searchParams.set('x', '280')
	url.searchParams.set('y', '180')
	url.searchParams.set('mimeFallback', 'true')
	url.searchParams.set('a', '0')

	return url.toString()
})

/* ===================== */
/* TYPE */
/* ===================== */
const isMulti = computed(() => {
	const count =
		props.file.filesCount ||
		props.file.files?.length ||
		1

	return count > 1
})

const icon = computed(() => {
	return isMulti.value
		? mdiFolder
		: mdiFileDocumentOutline
})

/* ===================== */
/* DISPLAY */
/* ===================== */
const displayName = computed(() => {
	if (!isMulti.value && props.file.files?.[0]) {
		return props.file.files[0].name
	}

	return props.file.name
})

const meta = computed(() => {
	const signers = props.file.signersCount || 0

	if (!isMulti.value) {
		const pages =
			props.file.totalPages ||
			props.file.metadata?.p ||
			props.file.files?.[0]?.metadata?.p ||
			0

		return `${pages} page${pages !== 1 ? 's' : ''} • ${signers} signer${signers !== 1 ? 's' : ''}`
	}

	const count =
		props.file.filesCount ||
		props.file.files?.length ||
		0

	return `${count} file${count !== 1 ? 's' : ''} • ${signers} signer${signers !== 1 ? 's' : ''}`
})

const filePreview = computed(() => {
	if (!isMulti.value) return ''

	return props.file.files
		?.slice(0, 2)
		.map((f: any) => f.name)
		.join(', ')
})

/* ===================== */
/* STATUS */
/* ===================== */
const statusLabel = computed(() => {
	if (!props.file.signersCount) {
		return 'Add signer'
	}

	const hasPositions =
		props.file.visibleElements?.length > 0

	if (!hasPositions) {
		return 'Set positions'
	}

	return 'Ready'
})

const statusClass = computed(() => {
	if (!props.file.signersCount) {
		return 'status-warning'
	}

	const hasPositions =
		props.file.visibleElements?.length > 0

	if (!hasPositions) {
		return 'status-warning'
	}

	return 'status-success'
})

/* ===================== */
/* INTERACTION */
/* ===================== */
const pulse = ref(false)
const highlight = ref(false)

function handleSelect() {
	if (typeof props.file.id === 'number') {
		emit('select', props.file.id)
	}
}

watch(
	() => [
		props.file.signersCount,
		props.file.visibleElements?.length,
	],
	() => {
		highlight.value = true
		pulse.value = true

		setTimeout(() => {
			highlight.value = false
			pulse.value = false
		}, 350)
	},
)
</script>

<style scoped>
.document-card {
	position: relative;
	overflow: hidden;

	display: flex;
	flex-direction: column;

	border: 1px solid var(--color-border);
	border-radius: 16px;

	background: var(--color-main-background);

	cursor: pointer;

	transition:
		transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
		border-color 180ms ease,
		box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1),
		background 180ms ease;
}

/* ACTIVE */
.document-card.active {
	transform: scale(0.985);
	border-color: #04d56d;
	background: rgba(4, 213, 109, 0.05);

	box-shadow:
		0 0 0 2px rgba(4, 213, 109, 0.12),
		0 10px 30px rgba(4, 213, 109, 0.08);
}

.document-card.active::before {
	content: '';
	position: absolute;

	left: 0;
	top: 0;
	bottom: 0;

	width: 3px;

	background: #04d56d;
	z-index: 3;
}

/* HOVER */
.document-card:hover {
	transform: translateY(-3px);

	border-color: rgba(4, 213, 109, 0.35);

	box-shadow:
		0 14px 34px rgba(0, 0, 0, 0.08);
}

/* PREVIEW */
.doc-preview {
	position: relative;

	width: 100%;
	height: 180px;

	background: rgba(4, 213, 109, 0.04);

	border-bottom: 1px solid rgba(0, 0, 0, 0.04);

	overflow: hidden;
}

.doc-preview::after {
	content: '';

	position: absolute;
	inset: 0;

	background:
		linear-gradient(
			to top,
			rgba(0,0,0,0.05),
			transparent 40%
		);

	pointer-events: none;
}

.preview-image {
	width: 100%;
	height: 100%;

	object-fit: contain;
	display: block;

	transition:
		transform 0.45s cubic-bezier(0.22, 1, 0.36, 1),
		filter 0.3s ease;
}

.document-card:hover .preview-image {
	transform: scale(1.03);
}

.doc-preview-fallback {
	width: 100%;
	height: 100%;

	display: flex;
	align-items: center;
	justify-content: center;
}

/* ICON */
.doc-icon {
	width: 56px;
	height: 56px;

	border-radius: 14px;

	display: flex;
	align-items: center;
	justify-content: center;

	background: rgba(4, 213, 109, 0.12);

	color: var(--color-primary-element);
	transition:
		transform 0.25s ease,
		background 0.25s ease;
}

.document-card:hover .doc-icon {
	transform: translateY(-2px) scale(1.04);
}

/* BADGE */
.type-badge {
	position: absolute;

	top: 10px;
	right: 10px;

	width: 28px;
	height: 28px;

	border-radius: 999px;

	display: flex;
	align-items: center;
	justify-content: center;

	background: rgba(255, 255, 255, 0.92);

	backdrop-filter: blur(6px);

	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

/* CONTENT */
.doc-content {
	padding: 14px;

	transition:
		transform 0.25s ease;
}

.document-card:hover .doc-content {
	transform: translateY(-1px);
}

/* STATUS */
.doc-status {
	display: flex;
	align-items: center;
	gap: 6px;

	font-size: 12px;

	margin-bottom: 8px;
}

.status-dot {
	width: 6px;
	height: 6px;

	border-radius: 999px;

	transition:
		transform 0.2s ease,
		box-shadow 0.2s ease;
}

.status-warning {
	background: #f59e0b;
}

.status-success {
	background: #04d56d;
	box-shadow: 0 0 0 rgba(4, 213, 109, 0.4);
}

.document-card:hover .status-success {
	box-shadow:
		0 0 10px rgba(4, 213, 109, 0.45);
}

/* TEXT */
.doc-name {
	font-size: 14px;
	font-weight: 600;

	line-height: 1.4;

	margin-bottom: 6px;

	word-break: break-word;
}

.doc-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.doc-subfiles {
	font-size: 11px;
	color: var(--color-text-maxcontrast);

	margin-top: 6px;

	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.doc-type {
	font-size: 11px;
	opacity: 0.6;
}

/* TRANSITIONS */
.meta-enter-active,
.meta-leave-active,
.status-enter-active,
.status-leave-active {
	transition: all 0.18s ease;
}

.meta-enter-from,
.status-enter-from {
	opacity: 0;
	transform: translateY(3px);
}

.meta-leave-to,
.status-leave-to {
	opacity: 0;
	transform: translateY(-3px);
}

/* ANIMATIONS */
.document-card.highlight {
	animation: cardFlash 0.35s ease;
}

.status-dot.pulse {
	animation: dotPulse 0.35s ease;
}

@keyframes cardFlash {
	0% {
		background: rgba(4, 213, 109, 0.14);
	}

	100% {
		background: var(--color-main-background);
	}
}

@keyframes dotPulse {
	0% {
		transform: scale(1);
	}

	50% {
		transform: scale(1.6);
		box-shadow: 0 0 0 6px rgba(4, 213, 109, 0.12);
	}

	100% {
		transform: scale(1);
	}
}

/* MOBILE */
@media (max-width: 768px) {
	.doc-preview {
		height: 140px;
	}

	.doc-content {
		padding: 12px;
	}
}
</style>
