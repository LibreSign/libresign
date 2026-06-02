<template>
	<div class="card table-card">

		<div class="card-header">
			<div class="header-left">
				<div class="header-icon">
					<NcIconSvgWrapper :path="mdiHistory" :size="18" />
				</div>

				<div class="header-text">
					<h3>Recent Requests</h3>
					<p>Your latest document activity</p>
				</div>
			</div>

			<div class="header-right">
				<span class="count">{{ items.length }}</span>
			</div>
		</div>

		<!-- EMPTY -->
		<div v-if="items.length === 0" class="empty-state">
			<div class="empty-state">
				<NcIconSvgWrapper :path="mdiFileDocumentOutline" :size="28" />
				<p>No documents yet</p>
			</div>
		</div>

		<!-- TABLE -->
		<div v-else class="table-container">
			<table class="table">
				<thead>
					<tr>
						<th>Document</th>
						<th>Status</th>
						<th>Date</th>
					</tr>
				</thead>

				<tbody>
					<tr v-for="doc in items" :key="doc.documentName">
						<td class="doc-cell">
							<div class="doc-info">
								<NcIconSvgWrapper :path="mdiFileDocumentOutline" :size="18" />

								<div class="doc-text">
									<p class="doc-name" :title="doc.documentName">
										{{ doc.documentName }}
									</p>
								</div>
							</div>
						</td>

						<td>
							<span :class="['badge', getStatusClass(doc.status)]">
								{{ doc.status }}
							</span>
						</td>

						<td>{{ formatDate(doc.createdAt) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

	</div>
</template>

<script setup lang="ts">
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiFilePdfBox, mdiFileDocumentOutline, mdiHistory } from '@mdi/js'
defineProps<{
	items: any[]
}>()

function formatDate(date: string) {
	return new Date(date).toLocaleDateString(undefined, {
		day: '2-digit',
		month: 'short',
		year: 'numeric'
	})
}

function getStatusClass(status: string) {
	if (status === 'SIGNED') return 'success'
	if (status === 'PENDING') return 'warning'
	return 'muted'
}
</script>

<style scoped>
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

.table-container {
	overflow-x: auto;
}

.table {
	width: 100%;
	border-collapse: collapse;
}

.table th,
.table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
}

.table td {
	padding: 14px 0;
	border-top: 1px solid var(--color-border);
}

.table th {
	padding-bottom: 10px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	font-weight: 800;
	font-size: 11px;
	letter-spacing: 0.4px;
}

.doc-cell {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.doc-info {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.doc-text {
	min-width: 0;
}

.doc-name {
	margin: 0;
	font-weight: 700;

	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.badge {
	font-size: 11px;
	padding: 4px 10px;
	border-radius: 999px;
	font-weight: 500;
}

.badge.success {
	background: rgba(4, 213, 109, 0.12);
	color: #04D56D;
}

.badge.warning {
	background: rgba(255, 165, 0, 0.12);
	color: orange;
}

.badge.muted {
	background: rgba(120, 120, 120, 0.12);
	color: gray;
}

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;

	padding: 24px;
	color: var(--color-text-maxcontrast);
}
</style>
