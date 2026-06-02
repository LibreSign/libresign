<template>
	<div class="card table-card">

		<!-- ===================== -->
		<!-- HEADER -->
		<!-- ===================== -->

		<div class="card-header">

			<div class="header-left">

				<div class="header-icon">
					<NcIconSvgWrapper
						:path="mdiFileEditOutline"
						:size="18" />
				</div>

				<div class="header-text">
					<h3>My Documents</h3>

					<p>
						Track participant progress, blockers and workflow activity
					</p>
				</div>

			</div>

			<div class="header-right">
				<span class="count">
					{{ items.length }}
				</span>
			</div>

		</div>

		<!-- ===================== -->
		<!-- EMPTY -->
		<!-- ===================== -->

		<div v-if="items.length === 0" class="empty-state">

			<NcIconSvgWrapper
				:path="mdiFileDocumentOutline"
				:size="34" />

			<div class="empty-copy">
				<h4>No documents yet</h4>

				<p>
					Uploaded documents and workflows will appear here
				</p>
			</div>

		</div>

		<!-- ===================== -->
		<!-- TABLE -->
		<!-- ===================== -->

		<div v-else class="table-container">

			<table class="table">

				<thead>
					<tr>
						<th>Document</th>
						<th>Status</th>
						<th>Participants</th>
						<th>Created</th>
						<th class="actions-column">Actions</th>
					</tr>
				</thead>

				<tbody>

					<tr
						v-for="doc in items"
						:key="doc.fileUuid"
						class="table-row"
					>

						<!-- ===================== -->
						<!-- DOCUMENT -->
						<!-- ===================== -->

						<td>

							<div class="document-cell">

								<div class="document-icon">
									<NcIconSvgWrapper
										:path="mdiFileDocumentOutline"
										:size="20" />
								</div>

								<div class="document-info">

									<p
										class="document-name"
										:title="doc.documentName"
									>
										{{ doc.documentName }}
									</p>

									<p class="document-meta">
										{{ getDocumentMeta(doc) }}
									</p>

								</div>

							</div>

						</td>

						<!-- ===================== -->
						<!-- STATUS -->
						<!-- ===================== -->

						<td>

							<div
								:class="[
									'status-badge',
									getStatusVariant(doc.primaryAction)
								]"
							>
								{{ doc.statusLabel }}
							</div>

						</td>

						<!-- ===================== -->
						<!-- PARTICIPANTS -->
						<!-- ===================== -->

						<td>

							<div class="participants-column">

								<div class="avatar-stack">

									<div
										v-for="(signer, index) in visibleSigners(doc.signers)"
										:key="`${signer.displayName}-${index}`"
										class="avatar"
										:class="{
											signed: signer.status === 'SIGNED',
											current: signer.me
										}"
										:style="{ zIndex: 20 - index }"
										:title="signer.displayName"
									>
										{{ getInitials(signer.displayName) }}
									</div>

									<div
										v-if="remainingSigners(doc.signers) > 0"
										class="avatar extra"
									>
										+{{ remainingSigners(doc.signers) }}
									</div>

								</div>

								<div class="participants-meta">

									<span class="participants-summary">
										{{ getParticipantSummary(doc.signers) }}
									</span>

								</div>

							</div>

						</td>

						<!-- ===================== -->
						<!-- UPDATED -->
						<!-- ===================== -->

						<td>

							<div class="updated-column">

								<span class="updated-at">
									{{ formatDate(doc.updatedAt || doc.createdAt) }}
								</span>

							</div>

						</td>

						<!-- ===================== -->
						<!-- ACTIONS -->
						<!-- ===================== -->

						<td>

							<div class="actions-cell">

								<NcActions
									class="row-actions"
								>

									<NcActionCaption name="Actions" />
									<NcActionButton
										@click="viewDocument(doc)"
									>

										<template #icon>
											<NcIconSvgWrapper
												:path="mdiFileEditOutline"
												:size="18" />
										</template>

										View workflow

									</NcActionButton>

									<!-- <NcActionButton
										v-if="canRemind(doc)"
										@click="sendReminder(doc)"
									>

										<template #icon>
											<NcIconSvgWrapper
												:path="mdiClockOutline"
												:size="18" />
										</template>

										Send reminder

									</NcActionButton> -->

								</NcActions>

							</div>

						</td>

					</tr>

				</tbody>

			</table>

		</div>

	</div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiFileDocumentOutline,
	mdiClockOutline,
	mdiCheckCircleOutline,
	mdiFileEditOutline,
} from '@mdi/js'

import { useFilesStore } from '@/store/files'
import { useSidebarStore } from '@/store/sidebar'
import type { DashboardWorkflowItem } from '@/store/dashboard'

const router = useRouter()
const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()

type Signer = {
	displayName?: string
	status: string
	canRemind: boolean
	canRequestSignature: boolean
	me?: boolean
}

defineProps<{
	items: DashboardWorkflowItem[]
}>()

function getStatusVariant(action: string) {
	switch (action) {
	case 'SIGN':
		return 'success'

	case 'COMPLETE_PAYMENT':
		return 'info'

	case 'WAIT':
		return 'warning'

	default:
		return 'muted'
	}
}

function formatDate(date?: string) {
	if (!date) return '—'

	return new Date(date).toLocaleDateString(undefined, {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	})
}

function visibleSigners(signers?: Signer[]) {
	return (signers || []).slice(0, 5)
}

function remainingSigners(signers?: Signer[]) {
	if (!signers) return 0

	return Math.max(signers.length - 5, 0)
}

function getInitials(name?: string) {
	if (!name) return '?'

	return name
		.split(' ')
		.map((part) => part.charAt(0))
		.join('')
		.slice(0, 2)
		.toUpperCase()
}

function getParticipantSummary(signers?: Signer[]) {
	if (!signers?.length) {
		return 'No participants'
	}

	const signed = signers.filter(
		(signer) => signer.status === 'SIGNED'
	).length

	return `${signed}/${signers.length} completed`
}

function getDocumentMeta(doc: DashboardWorkflowItem) {
	if (doc.completed) {
		return 'Workflow completed'
	}

	if (doc.primaryAction === 'WAIT') {
		return 'Waiting for participant action'
	}

	return 'Workflow active'
}

function canRemind(doc: DashboardWorkflowItem) {
	return doc.signers?.some((signer) => signer.canRemind)
}

function sendReminder(doc: DashboardWorkflowItem) {
	console.log('[Dashboard] send reminder', doc)
}

function viewDocument(doc: DashboardWorkflowItem) {
	filesStore.selectFile(doc.fileId)
	sidebarStore.activeRequestSignatureTab()
}
</script>

<style scoped>
.card {
	background: white;

	border-radius: 20px;
	padding: 22px;

	border: 1px solid rgba(15, 23, 42, 0.06);

	box-shadow:
		0 1px 2px rgba(15, 23, 42, 0.04),
		0 14px 40px rgba(15, 23, 42, 0.04);
}

/* =======================
   HEADER
======================= */

.card-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;

	margin-bottom: 22px;
	padding-bottom: 18px;

	border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.header-left {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.header-icon {
	width: 36px;
	height: 36px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 12px;

	background: rgba(15, 23, 42, 0.05);

	color: #0F172A;
}

.header-text h3 {
	margin: 0;

	font-size: 18px;
	font-weight: 680;

	letter-spacing: -0.4px;
}

.header-text p {
	margin: 5px 0 0;

	font-size: 13px;
	line-height: 1.5;

	color: var(--color-text-maxcontrast);
}

.count {
	font-size: 12px;
	font-weight: 600;

	padding: 5px 10px;
	border-radius: 999px;

	background: rgba(15, 23, 42, 0.05);

	color: var(--color-text-maxcontrast);
}

/* =======================
   TABLE
======================= */

.table-container {
	overflow-x: auto;
}

.table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0 12px;
}

.table th {
	text-align: left;

	padding: 0 14px 12px;

	font-size: 11px;
	font-weight: 700;

	letter-spacing: 0.5px;
	text-transform: uppercase;

	color: var(--color-text-maxcontrast);
}

.table-row {
	transition:
		transform 0.2s ease,
		box-shadow 0.2s ease;
}

.table-row td {
	padding: 18px 14px;

	background: rgba(15, 23, 42, 0.015);

	border-top: 1px solid rgba(15, 23, 42, 0.04);
	border-bottom: 1px solid rgba(15, 23, 42, 0.04);
}

.table-row td:first-child {
	border-top-left-radius: 16px;
	border-bottom-left-radius: 16px;
}

.table-row td:last-child {
	border-top-right-radius: 16px;
	border-bottom-right-radius: 16px;
}

.table-row:hover {
	transform: translateY(-1px);
}

.table-row:hover td {
	background: rgba(4, 213, 109, 0.035);
}

/* =======================
   DOCUMENT
======================= */

.document-cell {
	display: flex;
	align-items: center;
	gap: 14px;

	min-width: 280px;
}

.document-icon {
	width: 44px;
	height: 44px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 14px;

	background: rgba(239, 68, 68, 0.08);

	color: #ef4444;

	flex-shrink: 0;
}

.document-info {
	min-width: 0;
}

.document-name {
	margin: 0;

	font-size: 14px;
	font-weight: 680;

	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.document-meta {
	margin: 4px 0 0;

	font-size: 12px;

	color: var(--color-text-maxcontrast);
}

/* =======================
   STATUS
======================= */

.status-badge {
	display: inline-flex;
	align-items: center;

	padding: 6px 11px;

	border-radius: 999px;

	font-size: 12px;
	font-weight: 600;
}

.status-badge.success {
	background: rgba(4, 213, 109, 0.12);
	color: #04D56D;
}

.status-badge.warning {
	background: rgba(255, 165, 0, 0.12);
	color: orange;
}

.status-badge.info {
	background: rgba(59, 130, 246, 0.12);
	color: #2563eb;
}

.status-badge.muted {
	background: rgba(120, 120, 120, 0.1);
	color: gray;
}

/* =======================
   PARTICIPANTS
======================= */

.participants-column {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.avatar-stack {
	display: flex;
	align-items: center;
}

.avatar {
	width: 36px;
	height: 36px;

	display: flex;
	align-items: center;
	justify-content: center;

	margin-left: -8px;

	border-radius: 999px;
	border: 2px solid white;

	background: #0F172A;
	color: white;

	font-size: 11px;
	font-weight: 700;

	transition:
		transform 0.18s ease,
		z-index 0.18s ease;
}

.avatar:first-child {
	margin-left: 0;
}

.avatar:hover {
	transform: translateY(-2px) scale(1.05);
	z-index: 50 !important;
}

.avatar.signed {
	background: #04D56D;
}

.avatar.current {
	box-shadow:
		0 0 0 3px rgba(4, 213, 109, 0.15);
}

.avatar.extra {
	background: rgba(15, 23, 42, 0.08);
	color: var(--color-text-maxcontrast);
}

.participants-summary {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

/* =======================
   UPDATED
======================= */

.updated-column {
	display: flex;
	align-items: center;
}

.updated-at {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

/* =======================
   ACTIONS
======================= */

.actions-cell {
	display: flex;
	align-items: center;
	justify-content: flex-end;

	gap: 10px;
}

.ghost-action {
	transition:
		transform 0.18s ease,
		background 0.18s ease;
}

.ghost-action:hover {
	transform: translateX(2px);
}

/* =======================
   EMPTY
======================= */

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	gap: 14px;

	padding: 56px 20px;

	text-align: center;

	color: var(--color-text-maxcontrast);
}

.empty-copy h4 {
	margin: 0;

	font-size: 17px;
	font-weight: 680;

	color: var(--color-main-text);
}

.empty-copy p {
	margin: 5px 0 0;

	font-size: 13px;
	line-height: 1.5;
}

/* =======================
   RESPONSIVE
======================= */

@media (max-width: 1000px) {
	.table {
		min-width: 980px;
	}
}
</style>
