<template>
	<div class="card table-card">

		<!-- HEADER -->
		<div class="card-header">
			<div class="header-left">
				<div class="header-icon">
					<NcIconSvgWrapper :path="mdiClockOutline" :size="18" />
				</div>

				<div class="header-text">
					<h3>Action Required</h3>
					<p>Documents currently waiting for your attention</p>
				</div>
			</div>

			<div class="header-right">
				<span class="count">{{ items.length }}</span>
			</div>
		</div>

		<!-- EMPTY -->
		<div v-if="items.length === 0" class="empty-state">
			<NcIconSvgWrapper :path="mdiCheckCircleOutline" :size="30" />

			<div class="empty-copy">
				<h4>You're all caught up</h4>
				<p>No pending actions right now</p>
			</div>
		</div>

		<!-- TABLE -->
		<div v-else class="table-container">
			<table class="table">
				<thead>
					<tr>
						<th>Document</th>
						<th>Status</th>
						<th>Participants</th>
						<th>Created</th>
						<th class="actions-column">Action</th>
					</tr>
				</thead>

				<tbody>
					<tr
						v-for="doc in items"
						:key="doc.fileUuid"
						class="table-row"
					>

						<!-- DOCUMENT -->
						<td>
							<div class="document-cell">

								<div class="document-icon">
									<NcIconSvgWrapper
										:path="mdiFileDocumentOutline"
										:size="20" />
								</div>

								<div class="document-info">
									<p class="document-name" :title="doc.documentName">
										{{ doc.documentName }}
									</p>

									<p class="document-meta">
										<span v-if="doc.requesterName">Requested by: </span> {{ doc.requesterName || 'Unknown requester' }}
									</p>
								</div>

							</div>
						</td>

						<!-- STATUS -->
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

						<!-- PARTICIPANTS -->
						<td>
							<div class="participants">

								<div class="avatar-stack">
									<div
										v-for="(signer, index) in visibleSigners(doc.signers)"
										:key="`${signer.displayName}-${index}`"
										class="avatar"
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

							</div>
						</td>

						<!-- UPDATED -->
						<td>
							<span class="updated-at">
								{{ formatDate(doc.updatedAt || doc.createdAt) }}
							</span>
						</td>

						<!-- ACTION -->
						<td>
							<div class="actions-cell">

								<NcButton
									v-if="doc.canAct && doc.primaryAction !== 'NONE'"
									variant="tertiary"
									class="primary-action"
									@click="handlePrimaryAction(doc)"
								>
									{{ getPrimaryActionLabel(doc.primaryAction) }}
								</NcButton>

								<NcActions
									class="row-actions"
								>
									<NcActionCaption name="Actions" />
									<NcActionButton @click="viewDocument(doc)">
										<template #icon>
											<NcIconSvgWrapper
												:path="mdiFileEditOutline"
												:size="18" />
										</template>

										View document
									</NcActionButton>

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
import { computed } from 'vue'
import { generateUrl } from '@nextcloud/router'
import { openDocument }       from '../../../utils/viewer'
import { showError } from '@/services/toast'

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

type DashboardWorkflowItem = {
	documentName: string
	fileUuid: string
	fileId: number
	nodeId: number

	status: string
	statusLabel: string

	primaryAction:
		| 'SIGN'
		| 'WAIT'
		| 'VIEW'
		| 'COMPLETE_PAYMENT'
		| 'NONE'

	canAct: boolean
	completed: boolean

	requesterName?: string

	createdAt?: string
	updatedAt?: string

	signers?: Signer[]
}

defineProps<{
	items: DashboardWorkflowItem[]
}>()

function getPrimaryActionLabel(action: string) {
	switch (action) {
	case 'SIGN':
		return 'Sign now'

	case 'COMPLETE_PAYMENT':
		return 'Sign now'

	case 'VIEW':
		return 'View document'

	default:
		return 'Open'
	}
}

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

function handlePrimaryAction(doc: DashboardWorkflowItem) {
	filesStore.selectFile(doc.fileId)
	sidebarStore.activeRequestSignatureTab()
}

function viewDocument(doc: DashboardWorkflowItem) {
	const fileUrl = currentFileUrl(doc);

	if (!fileUrl) {
		showError('Document URL not found')
		return
	}
	if (typeof doc?.documentName !== 'string' || typeof doc?.nodeId !== 'number') {
		showError('Document not found')
		return
	}

	openDocument({ fileUrl, filename: doc.documentName, nodeId: doc.nodeId })
}

function currentFileUrl (doc: DashboardWorkflowItem) {
	if (!doc) return null
	if (doc.fileUuid) return generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: doc.fileUuid })
	return null
}
</script>

<style scoped>
.card {
	background: white;
	border-radius: 18px;
	padding: 20px;
	border: 1px solid rgba(15, 23, 42, 0.06);

	box-shadow:
		0 1px 2px rgba(15, 23, 42, 0.04),
		0 12px 32px rgba(15, 23, 42, 0.04);
}

/* =======================
   HEADER
======================= */

.card-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;

	margin-bottom: 20px;
	padding-bottom: 16px;

	border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.header-left {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.header-icon {
	width: 34px;
	height: 34px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 10px;

	background: rgba(4, 213, 109, 0.12);
	color: #04D56D;
}

.header-text h3 {
	margin: 0;

	font-size: 17px;
	font-weight: 650;
	letter-spacing: -0.3px;
}

.header-text p {
	margin: 4px 0 0;

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
	border-spacing: 0 10px;
}

.table th {
	text-align: left;

	padding: 0 14px 10px;

	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.5px;
	text-transform: uppercase;

	color: var(--color-text-maxcontrast);
}

.table-row {
	transition:
		transform 0.18s ease,
		background 0.18s ease,
		box-shadow 0.18s ease;
}

.table-row td {
	padding: 16px 14px;

	background: rgba(15, 23, 42, 0.015);

	border-top: 1px solid rgba(15, 23, 42, 0.04);
	border-bottom: 1px solid rgba(15, 23, 42, 0.04);
}

.table-row td:first-child {
	border-top-left-radius: 14px;
	border-bottom-left-radius: 14px;
}

.table-row td:last-child {
	border-top-right-radius: 14px;
	border-bottom-right-radius: 14px;
}

.table-row:hover {
	transform: translateY(-1px);
}

.table-row:hover td {
	background: rgba(4, 213, 109, 0.04);
}

/* =======================
   DOCUMENT
======================= */

.document-cell {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 260px;
}

.document-icon {
	width: 42px;
	height: 42px;

	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 12px;

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
	font-weight: 650;

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

	padding: 6px 10px;
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

.avatar-stack {
	display: flex;
	align-items: center;
}

.avatar {
	width: 34px;
	height: 34px;

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
	transform: translateY(-2px) scale(1.04);
	z-index: 50 !important;
}

.avatar.extra {
	background: rgba(15, 23, 42, 0.08);
	color: var(--color-text-maxcontrast);
}

/* =======================
   UPDATED
======================= */

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

.primary-action {
	transition:
		transform 0.18s ease,
		box-shadow 0.18s ease;
}

.primary-action:hover {
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

	gap: 12px;

	padding: 42px 20px;

	color: var(--color-text-maxcontrast);
}

.empty-copy h4 {
	margin: 0;

	font-size: 16px;
	font-weight: 650;
}

.empty-copy p {
	margin: 4px 0 0;

	font-size: 13px;
}

/* =======================
   RESPONSIVE
======================= */

@media (max-width: 900px) {
	.table {
		min-width: 900px;
	}
}
</style>
