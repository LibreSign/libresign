/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, reactive, ref, toRaw } from 'vue'
import { t } from '@nextcloud/l10n'

import ConfettiRuleEditor from './settings/confetti/ConfettiRuleEditor.vue'
import { confettiDefinition } from './settings/confetti'
import { identifyFactorsDefinition } from './settings/identify-factors'
import { signatureFlowDefinition } from './settings/signature-flow'
import { signatureStampDefinition } from './settings/signature-stamp'
import type {
	AdminViewMode,
	PolicyEditorDraft,
	PolicyRuleRecord,
	PolicyScope,
	PolicySettingDefinition,
	PolicySettingKey,
	PolicySettingSummary,
	PolicySettingValueMap,
	PolicyTargetOption,
} from './types'

type SettingsState = {
	[K in PolicySettingKey]: PolicyRuleRecord<K>[]
}

type StartEditorOptions = {
	scope: PolicyScope
	ruleId?: string
}

const CURRENT_GROUP_ID = 'finance'

const groups: PolicyTargetOption[] = [
	{ id: 'finance', label: 'Financeiro' },
	{ id: 'legal', label: 'Jurídico' },
	{ id: 'sales', label: 'Comercial' },
]

const users: PolicyTargetOption[] = [
	{ id: 'maria', label: 'Maria Silva', groupId: 'finance' },
	{ id: 'joao', label: 'João Pereira', groupId: 'finance' },
	{ id: 'ana', label: 'Ana Carvalho', groupId: 'legal' },
	{ id: 'bruno', label: 'Bruno Costa', groupId: 'sales' },
]

const definitions = {
	signature_flow: signatureFlowDefinition,
	confetti: confettiDefinition,
	signature_stamp: signatureStampDefinition,
	identify_factors: identifyFactorsDefinition,
	auto_reminders: {
		key: 'auto_reminders',
		title: t('libresign', 'Automatic reminders'),
		context: t('libresign', 'Notification cadence'),
		description: t('libresign', 'Control whether reminder notifications are automatically sent for pending signers.'),
		editor: ConfettiRuleEditor,
		createEmptyValue: () => ({ enabled: true }),
		summarizeValue: (value) => value.enabled
			? t('libresign', 'Enabled')
			: t('libresign', 'Disabled'),
		formatAllowOverride: (allowChildOverride) => allowChildOverride
			? t('libresign', 'Lower layers may override this rule')
			: t('libresign', 'Lower layers must inherit this value'),
	} satisfies PolicySettingDefinition<'auto_reminders'>,
	request_notifications: {
		key: 'request_notifications',
		title: t('libresign', 'Request notifications'),
		context: t('libresign', 'Delivery channel'),
		description: t('libresign', 'Define whether users receive status notifications while a request is in progress.'),
		editor: ConfettiRuleEditor,
		createEmptyValue: () => ({ enabled: true }),
		summarizeValue: (value) => value.enabled
			? t('libresign', 'Enabled')
			: t('libresign', 'Disabled'),
		formatAllowOverride: (allowChildOverride) => allowChildOverride
			? t('libresign', 'Lower layers may override this rule')
			: t('libresign', 'Lower layers must inherit this value'),
	} satisfies PolicySettingDefinition<'request_notifications'>,
	document_download_after_sign: {
		key: 'document_download_after_sign',
		title: t('libresign', 'Download after signing'),
		context: t('libresign', 'Post-sign action'),
		description: t('libresign', 'Control whether the finalized document is automatically offered for download after signing.'),
		editor: ConfettiRuleEditor,
		createEmptyValue: () => ({ enabled: true }),
		summarizeValue: (value) => value.enabled
			? t('libresign', 'Enabled')
			: t('libresign', 'Disabled'),
		formatAllowOverride: (allowChildOverride) => allowChildOverride
			? t('libresign', 'Lower layers may override this rule')
			: t('libresign', 'Lower layers must inherit this value'),
	} satisfies PolicySettingDefinition<'document_download_after_sign'>,
} satisfies { [K in PolicySettingKey]: PolicySettingDefinition<K> }

function cloneValue<K extends PolicySettingKey>(value: PolicySettingValueMap[K]): PolicySettingValueMap[K] {
	return JSON.parse(JSON.stringify(toRaw(value))) as PolicySettingValueMap[K]
}

export function createPolicyWorkbenchState() {
	const viewMode = ref<AdminViewMode>('system-admin')
	const activeSettingKey = ref<PolicySettingKey | null>(null)
	const editorDraft = ref<PolicyEditorDraft | null>(null)
	const editorMode = ref<'create' | 'edit' | null>(null)
	const highlightedRuleId = ref<string | null>(null)
	const nextRuleNumber = ref(100)

	const settingsState = reactive<SettingsState>({
		signature_flow: [
			{
				id: 'signature-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: { enabled: true, flow: 'ordered_numeric' },
			},
			{
				id: 'signature-group-finance',
				scope: 'group',
				targetId: 'finance',
				allowChildOverride: true,
				value: { enabled: true, flow: 'parallel' },
			},
			{
				id: 'signature-group-legal',
				scope: 'group',
				targetId: 'legal',
				allowChildOverride: false,
				value: { enabled: true, flow: 'ordered_numeric' },
			},
			{
				id: 'signature-user-maria',
				scope: 'user',
				targetId: 'maria',
				allowChildOverride: false,
				value: { enabled: true, flow: 'parallel' },
			},
		],
		auto_reminders: [
			{
				id: 'reminders-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'reminders-group-sales',
				scope: 'group',
				targetId: 'sales',
				allowChildOverride: false,
				value: { enabled: false },
			},
			{
				id: 'reminders-user-ana',
				scope: 'user',
				targetId: 'ana',
				allowChildOverride: false,
				value: { enabled: false },
			},
		],
		request_notifications: [
			{
				id: 'notifications-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'notifications-group-legal',
				scope: 'group',
				targetId: 'legal',
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'notifications-user-joao',
				scope: 'user',
				targetId: 'joao',
				allowChildOverride: false,
				value: { enabled: false },
			},
		],
		document_download_after_sign: [
			{
				id: 'download-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'download-group-finance',
				scope: 'group',
				targetId: 'finance',
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'download-user-maria',
				scope: 'user',
				targetId: 'maria',
				allowChildOverride: false,
				value: { enabled: false },
			},
		],
		confetti: [
			{
				id: 'confetti-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'confetti-group-sales',
				scope: 'group',
				targetId: 'sales',
				allowChildOverride: false,
				value: { enabled: false },
			},
			{
				id: 'confetti-group-legal',
				scope: 'group',
				targetId: 'legal',
				allowChildOverride: true,
				value: { enabled: true },
			},
			{
				id: 'confetti-user-ana',
				scope: 'user',
				targetId: 'ana',
				allowChildOverride: false,
				value: { enabled: false },
			},
			{
				id: 'confetti-user-bruno',
				scope: 'user',
				targetId: 'bruno',
				allowChildOverride: false,
				value: { enabled: true },
			},
		],
		signature_stamp: [
			{
				id: 'stamp-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: {
					enabled: true,
					renderMode: 'GRAPHIC_AND_DESCRIPTION',
					template: '{{ signer_name }} - {{ signed_at }}',
					templateFontSize: 10,
					signatureFontSize: 19,
					signatureWidth: 180,
					signatureHeight: 70,
					backgroundMode: 'default',
					showSigningDate: true,
				},
			},
			{
				id: 'stamp-group-legal',
				scope: 'group',
				targetId: 'legal',
				allowChildOverride: false,
				value: {
					enabled: true,
					renderMode: 'DESCRIPTION_ONLY',
					template: '{{ signer_name }} - {{ request_uuid }}',
					templateFontSize: 11,
					signatureFontSize: 16,
					signatureWidth: 220,
					signatureHeight: 80,
					backgroundMode: 'none',
					showSigningDate: false,
				},
			},
			{
				id: 'stamp-group-sales',
				scope: 'group',
				targetId: 'sales',
				allowChildOverride: true,
				value: {
					enabled: true,
					renderMode: 'GRAPHIC_ONLY',
					template: '{{ signer_name }} - {{ organization }}',
					templateFontSize: 10,
					signatureFontSize: 18,
					signatureWidth: 190,
					signatureHeight: 70,
					backgroundMode: 'default',
					showSigningDate: true,
				},
			},
			{
				id: 'stamp-user-joao',
				scope: 'user',
				targetId: 'joao',
				allowChildOverride: false,
				value: {
					enabled: true,
					renderMode: 'SIGNAME_AND_DESCRIPTION',
					template: '{{ signer_name }}',
					templateFontSize: 12,
					signatureFontSize: 22,
					signatureWidth: 210,
					signatureHeight: 80,
					backgroundMode: 'custom',
					showSigningDate: true,
				},
			},
			{
				id: 'stamp-user-ana',
				scope: 'user',
				targetId: 'ana',
				allowChildOverride: false,
				value: {
					enabled: true,
					renderMode: 'DESCRIPTION_ONLY',
					template: '{{ signer_name }} - {{ request_uuid }}',
					templateFontSize: 11,
					signatureFontSize: 17,
					signatureWidth: 220,
					signatureHeight: 82,
					backgroundMode: 'none',
					showSigningDate: false,
				},
			},
		],
		identify_factors: [
			{
				id: 'identify-system',
				scope: 'system',
				targetId: null,
				allowChildOverride: true,
				value: {
					enabled: true,
					requireAnyTwo: false,
					factors: [
						{
							key: 'email',
							label: 'Email',
							enabled: true,
							required: true,
							allowCreateAccount: true,
							signatureMethod: 'email_token',
						},
						{
							key: 'sms',
							label: 'SMS',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'sms_token',
						},
						{
							key: 'whatsapp',
							label: 'WhatsApp',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'whatsapp_token',
						},
						{
							key: 'document',
							label: 'Document data',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'document_validation',
						},
					],
				},
			},
			{
				id: 'identify-group-finance',
				scope: 'group',
				targetId: 'finance',
				allowChildOverride: true,
				value: {
					enabled: true,
					requireAnyTwo: true,
					factors: [
						{
							key: 'email',
							label: 'Email',
							enabled: true,
							required: true,
							allowCreateAccount: true,
							signatureMethod: 'email_token',
						},
						{
							key: 'sms',
							label: 'SMS',
							enabled: true,
							required: true,
							allowCreateAccount: false,
							signatureMethod: 'sms_token',
						},
						{
							key: 'whatsapp',
							label: 'WhatsApp',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'whatsapp_token',
						},
						{
							key: 'document',
							label: 'Document data',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'document_validation',
						},
					],
				},
			},
			{
				id: 'identify-group-legal',
				scope: 'group',
				targetId: 'legal',
				allowChildOverride: false,
				value: {
					enabled: true,
					requireAnyTwo: false,
					factors: [
						{
							key: 'email',
							label: 'Email',
							enabled: true,
							required: true,
							allowCreateAccount: true,
							signatureMethod: 'email_token',
						},
						{
							key: 'sms',
							label: 'SMS',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'sms_token',
						},
						{
							key: 'whatsapp',
							label: 'WhatsApp',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'whatsapp_token',
						},
						{
							key: 'document',
							label: 'Document data',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'document_validation',
						},
					],
				},
			},
			{
				id: 'identify-user-maria',
				scope: 'user',
				targetId: 'maria',
				allowChildOverride: false,
				value: {
					enabled: true,
					requireAnyTwo: false,
					factors: [
						{
							key: 'email',
							label: 'Email',
							enabled: true,
							required: true,
							allowCreateAccount: true,
							signatureMethod: 'email_token',
						},
						{
							key: 'sms',
							label: 'SMS',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'sms_token',
						},
						{
							key: 'whatsapp',
							label: 'WhatsApp',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'whatsapp_token',
						},
						{
							key: 'document',
							label: 'Document data',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'document_validation',
						},
					],
				},
			},
			{
				id: 'identify-user-joao',
				scope: 'user',
				targetId: 'joao',
				allowChildOverride: false,
				value: {
					enabled: true,
					requireAnyTwo: true,
					factors: [
						{
							key: 'email',
							label: 'Email',
							enabled: true,
							required: true,
							allowCreateAccount: true,
							signatureMethod: 'email_token',
						},
						{
							key: 'sms',
							label: 'SMS',
							enabled: true,
							required: true,
							allowCreateAccount: false,
							signatureMethod: 'sms_token',
						},
						{
							key: 'whatsapp',
							label: 'WhatsApp',
							enabled: true,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'whatsapp_token',
						},
						{
							key: 'document',
							label: 'Document data',
							enabled: false,
							required: false,
							allowCreateAccount: false,
							signatureMethod: 'document_validation',
						},
					],
				},
			},
		],
	})

	const activeDefinition = computed(() => activeSettingKey.value ? definitions[activeSettingKey.value] : null)

	const activeRules = computed(() => activeSettingKey.value ? settingsState[activeSettingKey.value] : [])

	const inheritedSystemRule = computed(() => activeRules.value.find(rule => rule.scope === 'system') ?? null)

	const currentGroupRule = computed(() => activeRules.value.find(rule => rule.scope === 'group' && rule.targetId === CURRENT_GROUP_ID) ?? null)

	const visibleGroupRules = computed(() => {
		if (viewMode.value === 'group-admin') {
			return currentGroupRule.value ? [currentGroupRule.value] : []
		}

		return activeRules.value.filter(rule => rule.scope === 'group')
	})

	const visibleUserRules = computed(() => {
		const visibleUsers = viewMode.value === 'group-admin'
			? users.filter(user => user.groupId === CURRENT_GROUP_ID).map(user => user.id)
			: users.map(user => user.id)

		return activeRules.value.filter(rule => rule.scope === 'user' && rule.targetId !== null && visibleUsers.includes(rule.targetId))
	})

	const visibleSettingSummaries = computed<PolicySettingSummary[]>(() => {
		return Object.values(definitions).map((definition) => {
			const rules = settingsState[definition.key]
			const systemRule = rules.find(rule => rule.scope === 'system') ?? null
			const groupRuleCount = viewMode.value === 'group-admin'
				? rules.filter(rule => rule.scope === 'group' && rule.targetId === CURRENT_GROUP_ID).length
				: rules.filter(rule => rule.scope === 'group').length
			const visibleUsersForSummary = viewMode.value === 'group-admin'
				? users.filter(user => user.groupId === CURRENT_GROUP_ID).map(user => user.id)
				: users.map(user => user.id)
			const userRuleCount = rules.filter(rule => rule.scope === 'user' && rule.targetId !== null && visibleUsersForSummary.includes(rule.targetId)).length

			return {
				key: definition.key,
				title: definition.title,
				context: definition.context,
				description: definition.description,
				defaultSummary: systemRule ? definition.summarizeValue(systemRule.value as never) : t('libresign', 'No global default rule'),
				groupCount: groupRuleCount,
				userCount: userRuleCount,
			}
		})
	})

	const availableTargets = computed(() => {
		if (!editorDraft.value) {
			return []
		}

		if (editorDraft.value.scope === 'group') {
			const takenTargets = activeRules.value
				.filter(rule => rule.scope === 'group' && rule.id !== editorDraft.value?.id)
				.map(rule => rule.targetId)

			return groups.filter(group => !takenTargets.includes(group.id))
		}

		if (editorDraft.value.scope === 'user') {
			const baseUsers = viewMode.value === 'group-admin'
				? users.filter(user => user.groupId === CURRENT_GROUP_ID)
				: users
			const takenTargets = activeRules.value
				.filter(rule => rule.scope === 'user' && rule.id !== editorDraft.value?.id)
				.map(rule => rule.targetId)

			return baseUsers.filter(user => !takenTargets.includes(user.id))
		}

		return []
	})

	const draftTargetLabel = computed(() => {
		if (!editorDraft.value) {
			return ''
		}

		return resolveTargetLabel(editorDraft.value.scope, editorDraft.value.targetId)
	})

	const duplicateMessage = computed(() => {
		if (!editorDraft.value || !activeSettingKey.value) {
			return ''
		}

		const hasDuplicate = settingsState[activeSettingKey.value].some((rule) => {
			return rule.scope === editorDraft.value?.scope
				&& rule.targetId === editorDraft.value?.targetId
				&& rule.id !== editorDraft.value?.id
		})

			return hasDuplicate ? t('libresign', 'An override for this target already exists.') : ''
	})

	const canSaveDraft = computed(() => {
		if (!editorDraft.value) {
			return false
		}

		if (duplicateMessage.value) {
			return false
		}

		if (editorDraft.value.scope === 'group' || editorDraft.value.scope === 'user') {
			return editorDraft.value.targetId !== null
		}

		return true
	})

	function setViewMode(mode: AdminViewMode) {
		viewMode.value = mode
		resetEditor()
	}

	function openSetting(key: PolicySettingKey) {
		activeSettingKey.value = key
		resetEditor()
	}

	function closeSetting() {
		activeSettingKey.value = null
		resetEditor()
	}

	function resetEditor() {
		editorDraft.value = null
		editorMode.value = null
		highlightedRuleId.value = null
	}

	function cancelEditor() {
		resetEditor()
	}

	function resolveTargetLabel(scope: PolicyScope, targetId: string | null) {
		if (scope === 'system') {
			return t('libresign', 'Global default rule')
		}

		if (scope === 'group') {
			return groups.find(group => group.id === targetId)?.label ?? t('libresign', 'Unknown group')
		}

		return users.find(user => user.id === targetId)?.label ?? t('libresign', 'Unknown user')
	}

	function getDefinition(key: PolicySettingKey) {
		return definitions[key]
	}

	function createDraft<K extends PolicySettingKey>(key: K, scope: PolicyScope, targetId: string | null): PolicyEditorDraft {
		const definition = definitions[key]
		return {
			id: null,
			settingKey: key,
			scope,
			targetId,
			allowChildOverride: true,
			value: cloneValue(definition.createEmptyValue(scope)) as PolicySettingValueMap[PolicySettingKey],
		}
	}

	function getNextTarget(scope: PolicyScope) {
		if (scope === 'group') {
			return groups.find(group => !activeRules.value.some(rule => rule.scope === 'group' && rule.targetId === group.id))?.id ?? null
		}

		if (scope === 'user') {
			const baseUsers = viewMode.value === 'group-admin'
				? users.filter(user => user.groupId === CURRENT_GROUP_ID)
				: users
			return baseUsers.find(user => !activeRules.value.some(rule => rule.scope === 'user' && rule.targetId === user.id))?.id ?? null
		}

		return null
	}

	function startEditor(options: StartEditorOptions) {
		if (!activeSettingKey.value) {
			return
		}

		if (options.ruleId) {
			const existingRule = activeRules.value.find(rule => rule.id === options.ruleId)
			if (!existingRule) {
				return
			}

			editorMode.value = 'edit'
			editorDraft.value = {
				id: existingRule.id,
				settingKey: activeSettingKey.value,
				scope: existingRule.scope,
				targetId: existingRule.targetId,
				allowChildOverride: existingRule.allowChildOverride,
				value: cloneValue(existingRule.value) as PolicySettingValueMap[PolicySettingKey],
			}
			highlightedRuleId.value = existingRule.id
			return
		}

		editorMode.value = 'create'
		const targetId = options.scope === 'system'
			? null
			: options.scope === 'group' && viewMode.value === 'group-admin'
				? CURRENT_GROUP_ID
				: getNextTarget(options.scope)

		editorDraft.value = createDraft(activeSettingKey.value, options.scope, targetId)
		highlightedRuleId.value = null
	}

	function updateDraftTarget(targetId: string | null) {
		if (!editorDraft.value) {
			return
		}

		editorDraft.value = {
			...editorDraft.value,
			targetId,
		}
	}

	function updateDraftAllowOverride(allowChildOverride: boolean) {
		if (!editorDraft.value) {
			return
		}

		editorDraft.value = {
			...editorDraft.value,
			allowChildOverride,
		}
	}

	function updateDraftValue(value: PolicySettingValueMap[PolicySettingKey]) {
		if (!editorDraft.value) {
			return
		}

		editorDraft.value = {
			...editorDraft.value,
			value,
		}
	}

	function saveDraft() {
		if (!activeSettingKey.value || !editorDraft.value || !canSaveDraft.value) {
			return
		}

		const rules = settingsState[activeSettingKey.value]
		const nextRule: PolicyRuleRecord = {
			id: editorDraft.value.id ?? `${activeSettingKey.value}-${nextRuleNumber.value++}`,
			scope: editorDraft.value.scope,
			targetId: editorDraft.value.scope === 'system' ? null : editorDraft.value.targetId,
			allowChildOverride: editorDraft.value.allowChildOverride,
			value: cloneValue(editorDraft.value.value),
		}

		const existingIndex = rules.findIndex(rule => rule.id === nextRule.id)
		if (existingIndex >= 0) {
			rules.splice(existingIndex, 1, nextRule)
		} else {
			rules.push(nextRule as never)
		}

		resetEditor()
	}

	function removeRule(ruleId: string) {
		if (!activeSettingKey.value) {
			return
		}

		const rules = settingsState[activeSettingKey.value]
		const index = rules.findIndex(rule => rule.id === ruleId)
		if (index >= 0) {
			rules.splice(index, 1)
		}

		if (highlightedRuleId.value === ruleId) {
			resetEditor()
		}
	}

	return {
		viewMode,
		activeSettingKey,
		activeDefinition,
		editorDraft,
		editorMode,
		highlightedRuleId,
		visibleSettingSummaries,
		visibleGroupRules,
		visibleUserRules,
		inheritedSystemRule,
		currentGroupRule,
		availableTargets,
		draftTargetLabel,
		duplicateMessage,
		canSaveDraft,
		currentGroupId: CURRENT_GROUP_ID,
		settingsState,
		setViewMode,
		openSetting,
		closeSetting,
		cancelEditor,
		startEditor,
		updateDraftTarget,
		updateDraftAllowOverride,
		updateDraftValue,
		saveDraft,
		removeRule,
		resolveTargetLabel,
		getDefinition,
	}
}
