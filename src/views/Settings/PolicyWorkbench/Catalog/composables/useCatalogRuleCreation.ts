/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

// TRANSLATORS Message shown in the LibreSign policy workbench when a policy cannot be created in the selected scope for this setting.
const settingUnavailableForScopeMessage = t('libresign', 'Not available for this setting.')
// TRANSLATORS Message shown in the LibreSign policy workbench when a custom default for everyone already exists and the admin should edit it instead of creating another rule.
const globalDefaultAlreadyExistsMessage = t('libresign', 'A default for everyone already exists. Use Change to update it.')
// TRANSLATORS Message shown in the LibreSign policy workbench when inherited rules block the creation of any new exceptions.
const higherLevelRuleBlockingMessage = t('libresign', 'A higher-level rule is blocking new exceptions in all scopes.')
// TRANSLATORS Scope label in the LibreSign rule creation flow for a rule that targets one specific account.
const accountScopeLabel = t('libresign', 'Account')
// TRANSLATORS Description in the LibreSign rule creation flow explaining that the rule affects one specific account.
const accountScopeDescription = t('libresign', 'Affects a specific account')
// TRANSLATORS Scope label in the LibreSign rule creation flow for a rule that targets all members of a group.
const groupScopeLabel = t('libresign', 'Group')
// TRANSLATORS Description in the LibreSign rule creation flow explaining that the rule affects all accounts in a group.
const groupScopeDescription = t('libresign', 'Affects all accounts in a group')
// TRANSLATORS Scope label in the LibreSign rule creation flow for a rule that applies to everyone in the instance.
const everyoneScopeLabel = t('libresign', 'Everyone')
// TRANSLATORS Description in the LibreSign rule creation flow explaining that the rule affects all accounts in the instance.
const everyoneScopeDescription = t('libresign', 'Affects all accounts')
// TRANSLATORS Priority hint label in the LibreSign policy workbench for account-level rules.
const accountPriorityLabel = t('libresign', 'Account')
// TRANSLATORS Priority hint label in the LibreSign policy workbench for group-level rules.
const groupPriorityLabel = t('libresign', 'Group')
// TRANSLATORS Priority hint label in the LibreSign policy workbench for the effective instance-wide default.
const defaultPriorityLabel = t('libresign', 'Default')
// TRANSLATORS Short source label shown beside an effective value in the LibreSign policy workbench when the default was customized by an administrator.
const customizedSourceLabel = t('libresign', 'custom')
// TRANSLATORS Short source label shown beside an effective value in the LibreSign policy workbench when LibreSign is still using its built-in default.
const builtInDefaultSourceLabel = t('libresign', 'default')
// TRANSLATORS Inline label shown in the LibreSign request-access policy dialog before the effective default access value.
const defaultAccessInlineLabel = t('libresign', 'Default access:')
// TRANSLATORS Generic inline label shown in the LibreSign policy workbench before the effective default value.
const defaultInlineLabelText = t('libresign', 'Default:')

export type CreateRuleScope = 'system' | 'group' | 'user'

export interface CreateScopeOption {
	scope: CreateRuleScope
	label: string
	description: string
	disabled: boolean
}

export interface CreateScopeNote {
	scope: 'group' | 'user'
	label: string
	reason: string
}

type RuleCreationStateLike = {
	activeDefinition?: {
		key?: string
		supportedScopes?: ReadonlyArray<CreateRuleScope>
	} | null
	createGroupOverrideDisabledReason: string
	createUserOverrideDisabledReason: string
	hasGlobalDefault: boolean
	viewMode: 'system-admin' | 'group-admin'
	canManageGroups: boolean
	editorDraft?: { scope: CreateRuleScope } | null
	visibleGroupRules: unknown[]
	visibleUserRules: unknown[]
	summary?: unknown
}

/**
 * Derives rule-creation options, priority hints, and default labels for the
 * policy workbench catalog dialogs.
 *
 * @param options Configuration object for the current catalog state.
 * @param options.state Reactive state consumed from the policy workbench catalog.
 */
export function useCatalogRuleCreation(options: {
	state: RuleCreationStateLike
}) {
	/**
	 * Returns the reason why creating a rule in the given scope is currently blocked.
	 *
	 * @param scope Scope being evaluated for rule creation.
	 */
	function scopeCreateDisabledReason(scope: CreateRuleScope) {
		if (options.state.activeDefinition?.supportedScopes && !options.state.activeDefinition.supportedScopes.includes(scope)) {
			return settingUnavailableForScopeMessage
		}

		if (scope === 'group') {
			return options.state.createGroupOverrideDisabledReason || ''
		}

		if (scope === 'user') {
			return options.state.createUserOverrideDisabledReason || ''
		}

		if (options.state.hasGlobalDefault) {
			return globalDefaultAlreadyExistsMessage
		}

		return ''
	}

	const allowedCreateScopes = computed<CreateRuleScope[]>(() => {
		const allScopes: CreateRuleScope[] = ['system', 'group', 'user']
		const supportedScopes = options.state.activeDefinition?.supportedScopes
			? new Set(options.state.activeDefinition.supportedScopes)
			: null

		const isSupported = (scope: CreateRuleScope) => {
			if (!supportedScopes) {
				return true
			}

			return supportedScopes.has(scope)
		}

		if (options.state.viewMode === 'group-admin') {
			if (options.state.canManageGroups === false) {
				return allScopes.filter((scope) => scope === 'user' && isSupported(scope))
			}
			return allScopes.filter((scope) => (scope === 'group' || scope === 'user') && isSupported(scope))
		}

		return allScopes.filter((scope) => isSupported(scope))
	})

	const hasCreatableScope = computed(() => {
		return allowedCreateScopes.value.some((scope) => scopeCreateDisabledReason(scope).length === 0)
	})

	const createRuleDisabledReason = computed(() => {
		if (!hasCreatableScope.value) {
			return higherLevelRuleBlockingMessage
		}

		return ''
	})

	const createScopeOptions = computed<CreateScopeOption[]>(() => {
		const optionsList = [
			{
				scope: 'user' as const,
				label: accountScopeLabel,
				description: accountScopeDescription,
				disabled: scopeCreateDisabledReason('user').length > 0,
			},
			{
				scope: 'group' as const,
				label: groupScopeLabel,
				description: groupScopeDescription,
				disabled: scopeCreateDisabledReason('group').length > 0,
			},
			{
				scope: 'system' as const,
				label: everyoneScopeLabel,
				description: everyoneScopeDescription,
				disabled: scopeCreateDisabledReason('system').length > 0,
			},
		]

		return optionsList.filter((option) => {
			if (!allowedCreateScopes.value.includes(option.scope)) {
				return false
			}

			if (option.scope === 'system') {
				return options.state.viewMode === 'system-admin' && !option.disabled
			}

			if (options.state.viewMode === 'group-admin') {
				return !option.disabled
			}

			if (option.scope !== 'user') {
				return true
			}

			return options.state.viewMode === 'system-admin' || !option.disabled
		})
	})

	const createScopeNotes = computed<CreateScopeNote[]>(() => {
		const notes: CreateScopeNote[] = []

		const groupOption = createScopeOptions.value.find((option) => option.scope === 'group')
		if (groupOption?.disabled) {
			notes.push({
				scope: 'group',
				label: groupScopeLabel,
				reason: scopeCreateDisabledReason('group'),
			})
		}

		const userOption = createScopeOptions.value.find((option) => option.scope === 'user')
		if (options.state.viewMode === 'system-admin' && userOption?.disabled) {
			notes.push({
				scope: 'user',
				label: accountScopeLabel,
				reason: scopeCreateDisabledReason('user'),
			})
		}

		return notes
	})

	/**
	 * Checks whether the active setting definition supports the given priority scope.
	 *
	 * @param scope Scope being checked against the active setting definition.
	 */
	function supportsPriorityScope(scope: CreateRuleScope): boolean {
		if (!options.state.activeDefinition?.supportedScopes || options.state.activeDefinition.supportedScopes.length === 0) {
			return true
		}

		return options.state.activeDefinition.supportedScopes.includes(scope)
	}

	/**
	 * Determines whether a priority scope should be visible in the current dialog context.
	 *
	 * @param scope Descendant scope whose visibility is being evaluated.
	 */
	function hasVisiblePriorityScope(scope: 'group' | 'user'): boolean {
		if (options.state.editorDraft?.scope === scope) {
			return true
		}

		const visibleRules = scope === 'group'
			? options.state.visibleGroupRules
			: options.state.visibleUserRules

		if (visibleRules.length > 0) {
			return true
		}

		return createScopeOptions.value.some((option) => option.scope === scope && !option.disabled)
	}

	/**
	 * Indicates whether a given scope participates in the precedence hint shown to the user.
	 *
	 * @param scope Scope being evaluated for the precedence hint.
	 */
	function isPriorityScopeAccessible(scope: CreateRuleScope): boolean {
		if (!supportsPriorityScope(scope)) {
			return false
		}

		if (scope === 'system') {
			return options.state.viewMode === 'system-admin'
		}

		return hasVisiblePriorityScope(scope)
	}

	const priorityScopeKeys = computed<CreateRuleScope[]>(() => {
		const scopes: CreateRuleScope[] = []

		if (isPriorityScopeAccessible('user')) {
			scopes.push('user')
		}

		if (isPriorityScopeAccessible('group')) {
			scopes.push('group')
		}

		if (isPriorityScopeAccessible('system')) {
			scopes.push('system')
		}

		return scopes
	})

	const priorityNoteScopes = computed<string[]>(() => {
		return priorityScopeKeys.value.map((scope) => {
			if (scope === 'user') {
				return accountPriorityLabel
			}

			if (scope === 'group') {
				return groupPriorityLabel
			}

			return defaultPriorityLabel
		})
	})

	const showDefaultInline = computed(() => {
		if (!options.state.summary) {
			return false
		}

		if (options.state.viewMode === 'system-admin') {
			return true
		}

		return priorityScopeKeys.value.length >= 2
	})

	const singleVisibleCreateScope = computed<CreateRuleScope | null>(() => {
		if (createScopeOptions.value.length !== 1) {
			return null
		}

		const [option] = createScopeOptions.value
		if (!option || option.disabled) {
			return null
		}

		return option.scope
	})

	const defaultSourceLabel = computed(() => {
		return options.state.hasGlobalDefault
			? customizedSourceLabel
			: builtInDefaultSourceLabel
	})

	const defaultInlineLabel = computed(() => {
		if (options.state.activeDefinition?.key === 'groups_request_sign') {
			return defaultAccessInlineLabel
		}

		return defaultInlineLabelText
	})

	return {
		scopeCreateDisabledReason,
		allowedCreateScopes,
		hasCreatableScope,
		createRuleDisabledReason,
		createScopeOptions,
		createScopeNotes,
		priorityScopeKeys,
		priorityNoteScopes,
		showDefaultInline,
		singleVisibleCreateScope,
		defaultSourceLabel,
		defaultInlineLabel,
	}
}
