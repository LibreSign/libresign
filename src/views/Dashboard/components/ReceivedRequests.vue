<template>
	<div class="card">

		<div class="card-header">
			<div class="header-left">
				<div class="header-icon">
					<NcIconSvgWrapper :path="mdiClockOutline" :size="16" />
				</div>

				<div class="header-text">
					<h3>Awaiting your signature</h3>
					<p>Documents that require your action</p>
				</div>
			</div>

			<div class="header-right">
				<span class="count">{{ count }}</span>
			</div>
		</div>

		<!-- EMPTY -->
		<div v-if="items.length === 0" class="empty-state">
			<div class="empty-state">
				<NcIconSvgWrapper :path="mdiFileDocumentAlertOutline" :size="28" />
				<p>No documents yet</p>
			</div>
		</div>

		<!-- LIST -->
		<div v-else class="list">
			<div v-for="doc in items" :key="doc.fileUuid" class="list-item" @click="goToSign(doc.fileUuid)">
				<div class="left">
					<div class="doc-icon">
						<NcIconSvgWrapper :path="mdiFileDocumentOutline" :size="18" />
					</div>

					<div class="doc-info">
						<p class="name" :title="doc.documentName">
							{{ doc.documentName }}
						</p>
						<span class="date">
							{{ formatDate(doc.createdAt) }}
						</span>
					</div>
				</div>

				<div class="right">
					<span class="status pending">Pending</span>
					<span class="action">Sign →</span>
				</div>
			</div>
		</div>

	</div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiClockOutline,
	mdiFileDocumentAlertOutline,
	mdiFileDocumentOutline,
} from '@mdi/js'

const router = useRouter()

defineProps<{
	items: any[]
	count: number
}>()

function goToSign(fileUuid: string) {
	router.push(`/sign/${fileUuid}`)
}

function formatDate(date: string) {
	return new Date(date).toLocaleDateString(undefined, {
		day: '2-digit',
		month: 'short',
		year: 'numeric'
	})
}
</script>

<style scoped>
.card {
	background: white;
	border-radius: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;

	padding-bottom: 12px;
	margin-bottom: 12px;

	border-bottom: 1px solid var(--color-border);
}

/* LEFT */
.header-left {
	display: flex;
	gap: 10px;
	align-items: flex-start;
}

.header-icon {
	width: 28px;
	height: 28px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 8px;

	background: rgba(4, 213, 109, 0.12);
	color: var(--color-primary-element);
}

/* TEXT STACK */
.header-text {
	display: flex;
	flex-direction: column;
}

/* TITLE */
.header-text h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	letter-spacing: -0.2px;
}

/* DESCRIPTION (this is the magic) */
.header-text p {
	margin: 2px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
}

/* ICON */
.header-left :deep(svg) {
	margin-top: 2px;
	/* aligns with title nicely */
	color: var(--color-text-maxcontrast);
	opacity: 0.8;
}

/* RIGHT */
.header-right {
	display: flex;
	align-items: center;
}

/* COUNT */
.count {
	font-size: 12px;
	font-weight: 500;

	padding: 4px 10px;
	border-radius: 999px;

	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}

.badge {
	background: var(--color-primary-element-light);
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
}

.list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

/* ITEM */
.list-item {
	display: flex;
	justify-content: space-between;
	align-items: center;

	padding: 12px 14px;
	border-radius: 10px;
	cursor: pointer;

	transition: all 0.2s ease;
}

/* HOVER */
.list-item:hover {
	background: rgba(4, 213, 109, 0.06);
	transform: translateX(2px);
}

/* LEFT SIDE */
.left {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
}

.doc-icon {
	width: 34px;
	height: 34px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 8px;
	background: rgba(4, 213, 109, 0.1);
	color: var(--color-primary-element);
}

/* TEXT */
.doc-info {
	min-width: 0;
}

.name {
	margin: 0;
	font-weight: 500;

	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.date {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

/* RIGHT SIDE */
.right {
	display: flex;
	align-items: center;
	gap: 12px;
}

.status.pending {
	font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;

  background: rgba(255, 165, 0, 0.12);
  color: orange;
  font-weight: 500;
}

.action {
	font-size: 12px;
	color: var(--color-primary-element);
	font-weight: 500;
	transition: all 0.15s ease;
}

.list-item:hover .action {
  transform: translateX(3px);
}

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;

	padding: 24px;
	color: var(--color-text-maxcontrast);
}
</style>
