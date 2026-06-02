import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { fetchDashboardData } from '@/services/dashboard'

/**
 * Workflow-aware dashboard item
 */
export type DashboardWorkflowItem = {
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

	isOwner: boolean
	isSigner: boolean

	requesterName?: string

	createdAt?: string
	updatedAt?: string

	signers?: Array<{
		displayName?: string
		status: string
		canRemind: boolean
		canRequestSignature: boolean
		me?: boolean
	}>
}

export const useDashboardStore = defineStore('dashboard', () => {
	// =====================
	// STATE
	// =====================

	const loading = ref(false)
	const loaded = ref(false)

	const error = ref<string | null>(null)

	/**
	 * Dashboard stats
	 */
	const stats = ref({
		totalDocuments: 0,
		pendingDocuments: 0,
		completedDocuments: 0,
		draftDocuments: 0,
	})

	/**
	 * Workflow-aware sections
	 */
	const myDocuments = ref<DashboardWorkflowItem[]>([])
	const receivedDocuments = ref<DashboardWorkflowItem[]>([])

	/**
	 * Payments + credits
	 */
	const recentPayments = ref<any[]>([])

	const entitlements = ref<Record<string, any>>({})

	// =====================
	// ACTIONS
	// =====================

	async function loadDashboard() {
		try {
			loading.value = true
			error.value = null

			const data = await fetchDashboardData()

			console.log('[Dashboard] fetched data', data)

			// =====================
			// STATS
			// =====================

			stats.value = {
				totalDocuments: data.stats?.totalDocuments ?? 0,
				pendingDocuments: data.stats?.pendingDocuments ?? 0,
				completedDocuments: data.stats?.completedDocuments ?? 0,
				draftDocuments: data.stats?.draftDocuments ?? 0,
			}

			// =====================
			// WORKFLOW SECTIONS
			// =====================

			myDocuments.value = data.myDocuments ?? []

			receivedDocuments.value = data.receivedDocuments ?? []

			// =====================
			// PAYMENTS
			// =====================

			recentPayments.value = data.recentPayments ?? []

			// =====================
			// ENTITLEMENTS
			// =====================

			entitlements.value = data.entitlements ?? {}

			loaded.value = true

		} catch (err) {
			console.error('[Dashboard] failed to load', err)

			error.value = 'Failed to load dashboard'
		} finally {
			loading.value = false
		}
	}

	// =====================
	// HELPERS
	// =====================

	/**
	 * Documents requiring immediate user action
	 */
	const actionableDocuments = computed(() => {
		return receivedDocuments.value.filter((doc) => {
			return doc.canAct && doc.primaryAction !== 'NONE'
		})
	})

	return {
		// state
		loading,
		loaded,
		error,

		stats,

		myDocuments,
		receivedDocuments,

		recentPayments,
		entitlements,

		// derived
		actionableDocuments,

		// actions
		loadDashboard,
	}
})
