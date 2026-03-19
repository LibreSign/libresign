/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import SignatureFlow from '../SignatureFlow.vue'
import { usePoliciesStore } from '../../../store/policies'
import type { EffectivePolicyValue } from '../../../types/index'

interface PolicyRuleRecord {
	id: string
	scope: 'system' | 'group' | 'user'
	targetId: string | null
	allowChildOverride: boolean
	value: EffectivePolicyValue
}

interface PolicyEditorDraft {
	scope: 'system' | 'group' | 'user'
	ruleId: string | null
	targetId: string | null
	value: EffectivePolicyValue
	allowChildOverride: boolean
}

interface PolicySettingDefinition {
	key: string
	title: string
	description: string
	menuHint: string
	editor: unknown
	createEmptyValue: () => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}

interface PolicySettingSummary {
	key: string
	title: string
	description: string
	menuHint: string
	defaultSummary: string
	groupCount: number
	userCount: number
}

interface PolicyTargetOption {
	id: string
	label: string
	groupId?: string
}

const realDefinitions = {
	signature_flow: {
		key: 'signature_flow',
		title: t('libresign', 'Signing order'),
		description: t('libresign', 'Define whether signers work in parallel or in a sequential order.'),
		menuHint: t('libresign', 'Control the overall signature flow model for documents.'),
		editor: SignatureFlow,
		createEmptyValue: () => ({ flow: 'parallel' } as unknown as EffectivePolicyValue),
		summarizeValue: (value: EffectivePolicyValue) => {
			const flowValue = (value as any)?.flow
			switch (flowValue) {
			case 'parallel':
				return t('libresign', 'Simultaneous (Parallel)')
			case 'ordered_numeric':
				return t('libresign', 'Sequential')
			case 'none':
				return t('libresign', 'Disabled')
			default:
				return t('libresign', 'Not configured')
			}
		},
		formatAllowOverride: (allowChildOverride: boolean) =>
			allowChildOverride
				? t('libresign', 'Lower layers may override this rule')
				: t('libresign', 'Lower layers must inherit this rule'),
	},
}

export function createRealPolicyWorkbenchState() {
	const policiesStore = usePoliciesStore()
	const viewMode = ref<'system-admin' | 'group-admin'>('system-admin')
	const activeSettingKey = ref<string | null>(null)
	const editorDraft = ref<PolicyEditorDraft | null>(null)
	const editorMode = ref<'create' | 'edit' | null>(null)
	const highlightedRuleId = ref<string | null>(null)

	// Mock groups and users for now (will be fetched from API later)
	const groups = ref<PolicyTargetOption[]>([
		{ id: 'finance', label: t('libresign', 'Finance') },
		{ id: 'legal', label: t('libresign', 'Legal') },
		{ id: 'sales', label: t('libresign', 'Sales') },
	])

	const users = ref<PolicyTargetOption[]>([
		{ id: 'user1', label: t('libresign', 'User 1'), groupId: 'finance' },
		{ id: 'user2', label: t('libresign', 'User 2'), groupId: 'finance' },
		{ id: 'user3', label: t('libresign', 'User 3'), groupId: 'legal' },
		{ id: 'user4', label: t('libresign', 'User 4'), groupId: 'sales' },
	])

	const visibleSettingSummaries = computed<PolicySettingSummary[]>(() => {
		return Object.values(realDefinitions).map((definition) => {
			const policy = policiesStore.getPolicy(definition.key)
			const groupCount = 1 // Placeholder
			const userCount = 0  // Placeholder

			return {
				key: definition.key,
				title: definition.title,
				description: definition.description,
				menuHint: definition.menuHint,
				defaultSummary: policy?.effectiveValue ? definition.summarizeValue(policy.effectiveValue) : t('libresign', 'Not configured'),
				groupCount,
				userCount,
			}
		})
	})

	const activeDefinition = computed(() => {
		if (!activeSettingKey.value) {
			return null
		}

		return realDefinitions[activeSettingKey.value as keyof typeof realDefinitions] || null
	})

	const inheritedSystemRule = computed<PolicyRuleRecord | null>(() => {
		if (!activeDefinition.value) {
			return null
		}

		const policy = policiesStore.getPolicy(activeDefinition.value.key)
		if (!policy?.effectiveValue) {
			return null
		}

		return {
			id: 'system-default',
			scope: 'system',
			targetId: null,
			allowChildOverride: true,
			value: policy.effectiveValue,
		}
	})

	const visibleGroupRules = computed<PolicyRuleRecord[]>(() => {
		// Placeholder for now
		return []
	})

	const visibleUserRules = computed<PolicyRuleRecord[]>(() => {
		// Placeholder for now
		return []
	})

	const availableTargets = computed<PolicyTargetOption[]>(() => {
		if (!editorDraft.value) {
			return []
		}

		if (editorDraft.value.scope === 'group') {
			return groups.value
		}

		if (editorDraft.value.scope === 'user') {
			return users.value
		}

		return []
	})

	const draftTargetLabel = computed(() => {
		if (!editorDraft.value?.targetId) {
			return null
		}

		const target = availableTargets.value.find(t => t.id === editorDraft.value?.targetId)
		return target?.label || null
	})

	const canSaveDraft = computed(() => {
		if (!editorDraft.value) {
			return false
		}

		if (editorDraft.value.scope !== 'system' && !editorDraft.value.targetId) {
			return false
		}

		return true
	})

	const duplicateMessage = ref<string | null>(null)

	function openSetting(key: string) {
		activeSettingKey.value = key
	}

	function closeSetting() {
		activeSettingKey.value = null
		editorDraft.value = null
		editorMode.value = null
	}

	function startEditor({ scope, ruleId }: { scope: 'system' | 'group' | 'user', ruleId?: string }) {
		if (!activeDefinition.value) {
			return
		}

		const isEdit = !!ruleId
		editorMode.value = isEdit ? 'edit' : 'create'

		let value: EffectivePolicyValue = activeDefinition.value.createEmptyValue()
		if (isEdit && scope === 'system' && inheritedSystemRule.value) {
			value = inheritedSystemRule.value.value
		}

		editorDraft.value = {
			scope,
			ruleId: ruleId || null,
			targetId: null,
			value,
			allowChildOverride: true,
		}
	}

	function cancelEditor() {
		editorDraft.value = null
		editorMode.value = null
		duplicateMessage.value = null
	}

	function updateDraftValue(value: EffectivePolicyValue) {
		if (editorDraft.value) {
			editorDraft.value.value = value
		}
	}

	function updateDraftTarget(targetId: string | null) {
		if (editorDraft.value) {
			editorDraft.value.targetId = targetId
		}
	}

	function updateDraftAllowOverride(allowChildOverride: boolean) {
		if (editorDraft.value) {
			editorDraft.value.allowChildOverride = allowChildOverride
		}
	}

	async function saveDraft() {
		if (!editorDraft.value || !activeDefinition.value) {
			return
		}

		const { scope, value, allowChildOverride, targetId } = editorDraft.value

		try {
			if (scope === 'system') {
				await policiesStore.saveSystemPolicy(activeDefinition.value.key, value)
			} else if (scope === 'group' && targetId) {
				await policiesStore.saveGroupPolicy(
					targetId,
					activeDefinition.value.key,
					value,
					allowChildOverride,
				)
			}

			cancelEditor()
		} catch (error) {
			console.error('Failed to save policy:', error)
		}
	}

	function removeRule(ruleId: string) {
		if (!activeDefinition.value) {
			return
		}

		// Placeholder for now
		console.log('Remove rule:', ruleId)
	}

	function resolveTargetLabel(scope: 'group' | 'user', targetId: string): string {
		const targets = scope === 'group' ? groups.value : users.value
		return targets.find(t => t.id === targetId)?.label || targetId
	}

	return reactive({
		activeDefinition,
		editorDraft,
		editorMode: editorMode as any,
		inheritedSystemRule,
		visibleGroupRules,
		visibleUserRules,
		visibleSettingSummaries,
		highlightedRuleId: highlightedRuleId as any,
		viewMode: viewMode as any,
		availableTargets,
		draftTargetLabel,
		duplicateMessage: duplicateMessage as any,
		canSaveDraft,
		openSetting,
		closeSetting,
		startEditor,
		cancelEditor,
		updateDraftValue,
		updateDraftTarget,
		updateDraftAllowOverride,
		saveDraft,
		removeRule,
		resolveTargetLabel,
	})
}
