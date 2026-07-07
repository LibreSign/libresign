<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		class="policy-workbench__section"
		:name="settingsSectionName"
		:description="settingsSectionDescription">

		<CatalogToolbar
			:model-value="catalogState.settingsFilter.value"
			:has-active-filter="hasActiveFilter"
			:is-small-viewport="isSmallViewport"
			:effective-catalog-layout="effectiveCatalogLayout"
			:is-catalog-collapsed="catalogState.isCatalogCollapsed.value"
			:catalog-view-button-label="catalogViewButtonLabel"
			:catalog-collapse-button-label="catalogCollapseButtonLabel"
			:has-visible-category-sections="hasVisibleCategorySections"
			:toolbar-ref="navigation.catalogToolbarRef"
			@update:modelValue="onSettingsFilterModelValueChange"
			@clear-filter="catalogState.clearSettingsFilter"
			@toggle-layout="toggleCatalogLayout"
			@toggle-collapsed="toggleCatalogCollapsed" />

		<CatalogCategoryNavigation
			v-if="showCategoryNavigation"
			:sections="visibleCategorySections"
			:active-category="navigation.activeCategory.value"
			:is-rtl="isRtl"
			:chips-scroller-ref="navigation.categoryChipsScroller"
			@navigate="handleCategoryChipNavigation" />

		<div class="policy-workbench__category-sections">
			<CatalogCategorySection
				v-for="category in visibleCategorySections"
				:key="`${effectiveCatalogLayout}-${category.key}`"
				:category="category"
				:layout="effectiveCatalogLayout"
				:is-active="navigation.activeCategory.value === category.key"
				:is-expanded="isCategoryExpandedForRender(category.key)"
				:section-ref="navigation.setCategorySectionRef(category.key)"
				:highlight-text="highlightText"
				:has-active-overrides="hasActiveOverrides"
				:resolve-default-stat-label="resolveDefaultStatLabel"
				:resolve-overrides-stat-label="resolveOverridesStatLabel"
				:format-override-summary="formatOverrideSummary"
				@toggle-category="catalogState.toggleCategoryCollapsed"
				@track-press="trackPress($event.layout, $event.key, $event.event)"
				@mark-selection="markSelectionGesture($event.layout, $event.key)"
				@open-from-pointer="openSettingFromPointer($event.layout, $event.key, $event.event)"
				@open-from-keyboard="openSettingFromKeyboard"
				@open-from-action="openSettingFromAction($event.key, $event.event)" />
		</div>

		<NcNoteCard v-if="filteredSettingSummaries.length === 0" type="info">
			<div class="policy-workbench__empty-state">
				<p>{{ emptyCatalogSearchMessage }}</p>
				<div class="policy-workbench__empty-state-actions">
					<NcButton variant="secondary" :aria-label="clearSettingsFilterAriaLabel" :disabled="!hasActiveFilter" @click="catalogState.clearSettingsFilter">
						{{ clearFilterButtonLabel }}
					</NcButton>
					<NcButton variant="tertiary" :aria-label="showAllSettingsAriaLabel" @click="catalogState.clearSettingsFilter">
						{{ showAllSettingsButtonLabel }}
					</NcButton>
				</div>
			</div>
		</NcNoteCard>

		<Teleport to="body">
			<Transition name="policy-workbench-back-to-top">
				<NcButton
					v-if="navigation.showBackToTop.value"
					class="policy-workbench__back-to-top"
					variant="secondary"
					:aria-label="backToTopAriaLabel"
					@click="navigation.scrollToTop()">
					<template #icon>
						<NcIconSvgWrapper :path="mdiArrowUp" :size="18" />
					</template>
					{{ backToTopButtonLabel }}
				</NcButton>
			</Transition>
		</Teleport>

		<NcDialog
			v-if="state.activeDefinition"
			:name="state.activeDefinition.title"
			size="full"
			:can-close="true"
			@closing="requestCloseSetting()">
			<CatalogSettingDialogFrame
				:dialog-description="dialogDescription"
				:priority-note-scopes="priorityNoteScopes"
				:removal-feedback="removalFeedback"
				:show-default-inline="showDefaultInline"
				:default-inline-label="defaultInlineLabel"
				:current-base-value="state.summary?.currentBaseValue ?? ''"
				:default-source-label="defaultSourceLabel"
				:show-change-default-action="state.viewMode === 'system-admin'"
				@change-default="openRuleEditor('system')">
				<CatalogCrudRulesTable
					:crud-search="crudSearch"
					:crud-scope-filter="crudScopeFilter"
					:scope-filter-open="scopeFilterOpen"
					:displayed-crud-rows="displayedCrudRows"
					:loading-more-crud-rows="loadingMoreCrudRows"
					:crud-selected-rows-count="crudSelectedRowsCount"
					:crud-all-visible-rows-selected="crudAllVisibleRowsSelected"
					:has-selectable-visible-crud-rows="hasSelectableVisibleCrudRows"
					:is-removing-rule="isRemovingRule"
					:rules-loading="state.rulesLoading"
					:has-creatable-scope="hasCreatableScope"
					:create-rule-disabled-reason="createRuleDisabledReason"
					:create-user-override-disabled-reason="state.createUserOverrideDisabledReason || ''"
					:has-active-crud-filters="hasActiveCrudFilters"
					:crud-empty-state-name="crudEmptyStateName"
					:crud-empty-state-description="crudEmptyStateDescription"
					:crud-empty-state-icon-path="crudEmptyStateIconPath"
					:active-scope-filter-chip="activeScopeFilterChip"
					:open-rule-actions-key="openRuleActionsKey"
					:crud-scope-label="crudScopeLabel"
					:is-crud-row-selected="isCrudRowSelected"
					@crud-search-change="onCrudSearchChange"
					@update:scopeFilterOpen="scopeFilterOpen = $event"
					@crud-scope-filter="setCrudScopeFilter($event.value, $event.selected)"
					@visible-selection-change="onVisibleCrudRowsSelectionChange"
					@row-selection-change="onCrudRowSelectionChange($event.ruleId, $event.selected)"
					@update-rule-actions-open="updateRuleActionsOpen($event.ruleKey, $event.open)"
					@edit-rule="handleEditRule($event.scope, $event.ruleId)"
					@prompt-rule-removal="handlePromptRuleRemoval($event.ruleId, $event.scope, $event.targetLabel)"
					@prompt-bulk-rule-removal="handlePromptBulkRuleRemoval"
					@clear-selection="clearCrudSelection"
					@request-create-rule="requestCreateRule()"
					@table-scroll="handleCrudTableScroll" />
			</CatalogSettingDialogFrame>
			</NcDialog>

			<NcDialog
				v-if="showCreateScopeDialog || state.editorDraft"
				:key="ruleDialogInstanceKey"
				:name="ruleDialogTitle"
				:size="ruleEditorDialogSize"
				:class="ruleEditorDialogClass"
				:buttons="ruleDialogButtons"
				:can-close="true"
				@closing="requestCloseRuleDialog()">
				<div
					v-if="state.editorDraft"
					class="policy-workbench__editor-modal-body"
					:class="ruleEditorDialogBodyClass">
				<PolicyRuleEditorPanel
					v-if="state.editorDraft"
					:editor-draft="state.editorDraft"
					:editor-mode="state.editorMode"
					:editor-title="editorTitle"
					:editor-help="editorHelp"
					:precedence-scopes="priorityNoteScopes"
					:active-editor="activeEditor"
					:editor-props="activeEditorProps"
					:editor-initial-target-ids="state.editorInitialTargetIds"
					:selected-target-options="selectedTargetOptions"
					:available-targets="state.availableTargets"
					:loading-targets="state.loadingTargets"
					:duplicate-message="state.duplicateMessage"
					:can-save-draft="state.canSaveDraft"
					:save-status="saveStatus"
					:show-inline-actions="false"
					:show-back-button="showCreateRuleBackAction"
					:show-allow-override-switch="true"
					:allow-override-mutable="isAllowOverrideMutable"
					:hide-target-selector="hideTargetSelector"
					@search-targets="state.searchAvailableTargets"
					@update-targets="onTargetChange"
					@update-value="state.updateDraftValue"
					@template-changed="state.markDraftTouched"
					@update-allow-override="state.updateDraftAllowOverride"
					@back="requestBackToCreateScope()"
					@save="handleSaveDraft()"
					@cancel="requestCloseRuleDialog()" />
			</div>
			<CatalogCreateScopeSelector
				v-else
				:options="createScopeOptions"
				:selected-scope="selectedCreateScope"
				:notes="createScopeNotes"
				@select-scope="selectCreateScope" />
		</NcDialog>

		<NcDialog
			v-if="pendingDiscardAction"
			:name="discardUnsavedChangesDialogName"
			:message="discardUnsavedChangesDialogMessage"
			:buttons="discardDialogButtons"
			size="normal"
			:can-close="true"
			@closing="cancelDiscardDialog" />

		<NcDialog
			v-if="pendingRemoval"
			:name="confirmRuleRemovalDialogName"
			:message="pendingRemovalMessage"
			:buttons="removalDialogButtons"
			size="normal"
			:can-close="!isRemovingRule"
			@closing="cancelRuleRemoval" />
	</NcSettingsSection>
</template>

<script setup lang="ts">
import {
	mdiArrowUp,
	mdiFilterVariant,
	mdiPlus,
} from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../../../store/policies'
import { useUserConfigStore } from '../../../../store/userconfig.js'
import CatalogCategoryNavigation from './components/CatalogCategoryNavigation.vue'
import CatalogCategorySection from './components/CatalogCategorySection.vue'
import CatalogCreateScopeSelector from './components/CatalogCreateScopeSelector.vue'
import CatalogCrudRulesTable from './components/CatalogCrudRulesTable.vue'
import { useCatalogRuleCreation } from './composables/useCatalogRuleCreation'
import CatalogSettingDialogFrame from './components/CatalogSettingDialogFrame.vue'
import CatalogToolbar from './components/CatalogToolbar.vue'
import PolicyRuleEditorPanel from '../PolicyRuleEditorPanel.vue'
import { createRealPolicyWorkbenchState } from '../useRealPolicyWorkbench'
import { useCatalogCrudTable } from './composables/useCatalogCrudTable'
import { useCatalogPresentation } from './composables/useCatalogPresentation'
import { useCatalogState } from './composables/useCatalogState'
import { useCatalogInteractions } from './composables/useCatalogInteractions'
import { useNavigation } from './composables/useNavigation'
import type { RealPolicySettingCategory } from '../settings/realTypes'
import type { CreateRuleScope } from './composables/useCatalogRuleCreation'

type PolicyWorkbenchSaveStatus = 'idle' | 'saving' | 'saved'
type PendingDiscardActionKey = 'back-create-rule' | 'cancel-create-rule' | 'cancel-editor' | 'close-setting'
type CatalogCategoryKey = RealPolicySettingCategory

interface PendingRemovalState {
	ruleId?: string
	ruleIds?: string[]
	scope?: CreateRuleScope
	targetLabel?: string
	help: string
}

defineOptions({
	name: 'RealPolicyWorkbench',
})

const policiesStore = usePoliciesStore()
const userConfigStore = useUserConfigStore()
const state = reactive(createRealPolicyWorkbenchState())
const isSmallViewport = ref(false)
const saveStatus = ref<PolicyWorkbenchSaveStatus>('idle')
const saveFeedbackTimeout = ref<number | null>(null)
const pendingRemoval = ref<PendingRemovalState | null>(null)
const pendingDiscardAction = ref<PendingDiscardActionKey | null>(null)
const showCreateScopeDialog = ref(false)
const selectedCreateScope = ref<CreateRuleScope | null>(null)
const ruleDialogInstanceKey = ref(0)
const isRemovingRule = ref(false)
const removalFeedback = ref<string | null>(null)
const removalFeedbackTimeout = ref<number | null>(null)
const openRuleActionsKey = ref<string | null>(null)
const isRtl = ref(false)

// TRANSLATORS Section title for the policy workbench catalog.
const settingsSectionName = t('libresign', 'Document signing settings')
// TRANSLATORS Section description explaining that the catalog configures signing behavior.
const settingsSectionDescription = t('libresign', 'Configure how signing works.')
// TRANSLATORS Empty-state message shown when no policy settings match the current search.
const emptyCatalogSearchMessage = t('libresign', 'No settings match this search. Try fewer keywords or clear the filter.')
// TRANSLATORS Aria label for the button that clears the current settings filter.
const clearSettingsFilterAriaLabel = t('libresign', 'Clear settings filter')
// TRANSLATORS Button label that clears the current settings filter.
const clearFilterButtonLabel = t('libresign', 'Clear filter')
// TRANSLATORS Aria label for the action that restores the full settings catalog.
const showAllSettingsAriaLabel = t('libresign', 'Show all settings')
// TRANSLATORS Button label that restores the full settings catalog after an empty filter result.
const showAllSettingsButtonLabel = t('libresign', 'Show all settings')
// TRANSLATORS Aria label for the floating action that scrolls the settings catalog back to the top.
const backToTopAriaLabel = t('libresign', 'Back to top')
// TRANSLATORS Floating button label that scrolls the settings catalog back to the top.
const backToTopButtonLabel = t('libresign', 'Back to top')
// TRANSLATORS Dialog title shown when closing an editor with unsaved changes.
const discardUnsavedChangesDialogName = t('libresign', 'Discard unsaved changes?')
// TRANSLATORS Dialog message warning that unsaved editor changes will be lost.
const discardUnsavedChangesDialogMessage = t('libresign', 'You have unsaved changes in this editor. If you continue, your changes will be lost.')
// TRANSLATORS Dialog title shown before deleting one or more policy rules.
const confirmRuleRemovalDialogName = t('libresign', 'Confirm rule removal')

// Initialize catalog state composable
const catalogState = useCatalogState()
const {
	clearCatalogFocusOnClose,
	markSelectionGesture,
	trackPress,
	openSettingFromPointer,
	openSettingFromAction,
	openSettingFromKeyboard,
	highlightText,
} = useCatalogInteractions({
	getFilter: () => catalogState.settingsFilter.value,
	onOpenSetting: (key) => state.openSetting(key as never),
})
const {
	filteredSettingSummaries,
	visibleCategorySections,
	effectiveCatalogLayout,
	hasActiveFilter,
	hasVisibleCategorySections,
	showCategoryNavigation,
	catalogViewButtonLabel,
	catalogCollapseButtonLabel,
} = useCatalogPresentation({
	visibleSettingSummaries: computed(() => state.visibleSettingSummaries),
	settingsFilter: catalogState.settingsFilter,
	catalogLayout: catalogState.catalogLayout,
	isCatalogCollapsed: catalogState.isCatalogCollapsed,
	isSmallViewport,
})
const {
	crudSearch,
	crudScopeFilter,
	scopeFilterOpen,
	displayedCrudRows,
	hasMoreCrudRows,
	loadingMoreCrudRows,
	selectedCrudRowsCount: crudSelectedRowsCount,
	allVisibleCrudRowsSelected: crudAllVisibleRowsSelected,
	hasSelectableVisibleCrudRows,
	selectedCrudRuleIds,
	isCrudRowSelected,
	toggleCrudRowSelection,
	toggleVisibleCrudRowsSelection,
	clearCrudSelection,
	loadMoreCrudRows,
	activeScopeFilterChip,
	crudScopeLabel,
	onCrudSearchChange,
	setCrudScopeFilter,
} = useCatalogCrudTable({
	state,
	summarizeRuleValue,
})

const REMOVAL_FEEDBACK_DURATION_MS = 6000

const navigation = useNavigation(visibleCategorySections)

const activeEditor = computed(() => state.activeDefinition?.editor ?? null)
const hideTargetSelector = computed(() => {
	if (!state.editorDraft || state.editorDraft.scope === 'system') {
		return false
	}

	return state.activeDefinition?.extractScopeTargets !== undefined
})
const activeEditorProps = computed<Record<string, unknown>>(() => {
	if (!state.activeDefinition) {
		return {}
	}

	const baseEditorProps = state.activeDefinition.editorProps ?? {}
	const activePolicy = policiesStore.getPolicy(state.activeDefinition.key)

	return state.activeDefinition.resolveEditorProps?.(activePolicy, baseEditorProps) ?? baseEditorProps
})

const selectedTargetOptions = computed(() => {
	if (!state.editorDraft) {
		return []
	}

	return state.availableTargets.filter((option: { id: string }) => state.editorDraft?.targetIds.includes(option.id))
})

const {
	scopeCreateDisabledReason,
	allowedCreateScopes,
	hasCreatableScope,
	createRuleDisabledReason,
	createScopeOptions,
	createScopeNotes,
	priorityNoteScopes,
	showDefaultInline,
	singleVisibleCreateScope,
	defaultSourceLabel,
	defaultInlineLabel,
} = useCatalogRuleCreation({ state })


const ruleDialogTitle = computed(() => {
	if (!state.editorDraft) {
		return t('libresign', 'What do you want to create?')
	}

	return state.editorMode === 'edit'
		? t('libresign', 'Edit rule')
		: t('libresign', 'Create rule')
})

const editorTitle = computed(() => {
	if (!state.editorDraft) {
		return ''
	}

	return state.editorMode === 'edit'
		? t('libresign', 'Edit rule')
		: t('libresign', 'Create rule')
})

const editorHelp = computed(() => {
	if (!state.editorDraft) {
		return ''
	}

	return ''
})

const isAllowOverrideMutable = computed(() => {
	if (!state.editorDraft || !state.activeDefinition) {
		return true
	}

	const scope = state.editorDraft.scope
	const context = {
		scope,
		editorMode: state.editorMode,
		viewMode: state.viewMode,
	}
	const normalizedTrue = state.activeDefinition.normalizeAllowChildOverride(scope, true, context)
	const normalizedFalse = state.activeDefinition.normalizeAllowChildOverride(scope, false, context)

	return normalizedTrue !== normalizedFalse
})

const showCreateRuleBackAction = computed(() => {
	return showCreateScopeDialog.value && state.editorMode === 'create'
})

const dialogDescription = computed(() => state.activeDefinition?.description || '')

const ruleEditorDialogClass = computed(() => {
	return state.activeDefinition?.editorDialogLayout === 'wide'
		? 'policy-workbench__rule-dialog policy-workbench__rule-dialog--wide'
		: 'policy-workbench__rule-dialog'
})

const ruleEditorDialogSize = computed(() => {
	return state.activeDefinition?.editorDialogLayout === 'wide'
		? 'large'
		: 'normal'
})

const ruleEditorDialogBodyClass = computed(() => {
	return state.activeDefinition?.editorDialogLayout === 'wide'
		? 'policy-workbench__editor-modal-body--wide'
		: ''
})

const hasActiveCrudFilters = computed(() => {
	return crudSearch.value.trim().length > 0 || crudScopeFilter.value !== 'all'
})

const crudEmptyStateName = computed(() => {
	return hasActiveCrudFilters.value
		? t('libresign', 'No rules match the current filters.')
		: t('libresign', 'No custom rules created yet.')
})

const crudEmptyStateDescription = computed(() => {
	return hasActiveCrudFilters.value
		// TRANSLATORS Empty-state suggestion shown when scope/search filters hide all policy rules.
		? t('libresign', 'Try adjusting or clearing the current filters.')
		// TRANSLATORS Empty-state guidance encouraging admins to delegate signature-request access with scoped rules.
		: t('libresign', 'Create a rule to delegate signature request access for specific accounts or groups.')
})

const crudEmptyStateIconPath = computed(() => {
	return hasActiveCrudFilters.value ? mdiFilterVariant : mdiPlus
})

const pendingRemovalMessage = computed(() => {
	if (!pendingRemoval.value) {
		return ''
	}

	if (pendingRemoval.value.ruleIds?.length) {
		return t('libresign', 'You are about to remove {count} selected rules. {help}', {
			count: String(pendingRemoval.value.ruleIds.length),
			help: pendingRemoval.value.help,
		})
	}

	return t('libresign', 'You are about to remove the rule for {target}. {help}', {
		target: pendingRemoval.value.targetLabel ?? '',
		help: pendingRemoval.value.help,
	})
})

const ruleDialogButtons = computed(() => {
	if (!state.editorDraft) {
		return undefined
	}

	const buttons: Array<{
		label: string,
		variant: 'primary' | 'secondary' | 'tertiary',
		disabled?: boolean,
		callback: () => false | Promise<false>,
	}> = []

	if (showCreateRuleBackAction.value) {
		buttons.push({
			label: t('libresign', '← Back'),
			variant: 'tertiary',
			disabled: saveStatus.value === 'saving',
			callback: () => {
				requestBackToCreateScope()
				return false
			},
		})
	}

	buttons.push({
		label: state.editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule'),
		variant: 'primary',
		disabled: !state.canSaveDraft,
		callback: async () => {
			await handleSaveDraft()
			return false as const
		},
	})

	buttons.push({
		label: t('libresign', 'Cancel'),
		variant: 'secondary',
		disabled: saveStatus.value === 'saving',
		callback: () => {
			requestCloseRuleDialog()
			return false
		},
	})

	return buttons
})

const discardDialogButtons = computed(() => {
	return [
		{
			label: t('libresign', 'Keep editing'),
			variant: 'secondary' as const,
			callback: () => cancelDiscardDialog(),
		},
		{
			label: t('libresign', 'Discard changes'),
			variant: 'error' as const,
			callback: () => confirmDiscardDialog(),
		},
	]
})

const removalDialogButtons = computed(() => {
	return [
		{
			label: t('libresign', 'Cancel'),
			variant: 'secondary' as const,
			disabled: isRemovingRule.value,
			callback: () => cancelRuleRemoval(),
		},
		{
			label: isRemovingRule.value ? t('libresign', 'Removing exception...') : t('libresign', 'Remove exception'),
			variant: 'error' as const,
			disabled: isRemovingRule.value,
			callback: () => {
				void confirmRuleRemoval()
			},
		},
	]
})

function resolveTargetId(option: unknown): string | null {
	if (typeof option === 'string') {
		const trimmed = option.trim()
		return trimmed.length > 0 ? trimmed : null
	}

	if (typeof option === 'number' && Number.isFinite(option)) {
		return String(option)
	}

	if (option && typeof option === 'object') {
		const record = option as Record<string, unknown>
		for (const key of ['id', 'user', 'uid', 'value', 'group', 'groupId', 'identifier']) {
			const candidate = record[key]
			const resolvedCandidate = resolveTargetId(candidate)
			if (resolvedCandidate !== null) {
				return resolvedCandidate
			}
		}
	}

	return null
}

function onTargetChange(option: unknown[] | unknown | null) {
	if (Array.isArray(option)) {
		state.updateDraftTargets(
			option
				.map((candidate) => resolveTargetId(candidate))
				.filter((candidate): candidate is string => candidate !== null),
		)
		return
	}

	const targetId = resolveTargetId(option)
	state.updateDraftTargets(targetId ? [targetId] : [])
}

function summarizeRuleValue(value: unknown) {
	if (!state.activeDefinition) {
		return ''
	}

	return state.activeDefinition.summarizeValue(value as never)
}

function requestCreateRule() {
	if (!hasCreatableScope.value || saveStatus.value === 'saving') {
		return
	}

	if (state.editorDraft) {
		if (state.isDraftDirty) {
			pendingDiscardAction.value = 'cancel-create-rule'
			return
		}

		state.cancelEditor()
	}

	if (singleVisibleCreateScope.value) {
		selectCreateScope(singleVisibleCreateScope.value)
		return
	}

	selectedCreateScope.value = null
	showCreateScopeDialog.value = true
}

function cancelCreateScopeDialog() {
	state.cancelEditor()
	showCreateScopeDialog.value = false
	selectedCreateScope.value = null
}

function selectCreateScope(scope: CreateRuleScope) {
	if (scopeCreateDisabledReason(scope).length > 0) {
		return
	}

	selectedCreateScope.value = scope
	startCreateRuleForScope(scope)
}

function openRuleEditor(scope: CreateRuleScope, ruleId?: string) {
	state.startEditor(ruleId ? { scope, ruleId } : { scope })
}

function startCreateRuleForScope(scope: CreateRuleScope) {
	if (scopeCreateDisabledReason(scope).length > 0) {
		return
	}

	openRuleEditor(scope)
}

function requestBackToCreateScope() {
	if (saveStatus.value === 'saving') {
		return
	}

	if (state.isDraftDirty) {
		pendingDiscardAction.value = 'back-create-rule'
		return
	}

	state.cancelEditor()
	selectedCreateScope.value = null
}

function hasActiveOverrides(groupCount?: number, userCount?: number, everyoneCount?: number) {
	return (groupCount ?? 0) > 0 || (userCount ?? 0) > 0 || (everyoneCount ?? 0) > 0
}

function formatOverrideSummary(groupCount?: number, userCount?: number, policyKey?: string, everyoneCount?: number) {
	if ((groupCount ?? 0) === 0 && (userCount ?? 0) === 0 && (everyoneCount ?? 0) === 0) {
		if (policyKey === 'groups_request_sign') {
			// TRANSLATORS Summary for signature-request access policy when no explicit overrides are configured.
			return t('libresign', 'none configured')
		}

		return t('libresign', 'none')
	}

	if ((groupCount ?? 0) === 0 && (userCount ?? 0) === 0 && (everyoneCount ?? 0) > 0) {
		// TRANSLATORS Summary shown when only a system-level (everyone) rule is active with no group/user overrides.
		return t('libresign', 'everyone')
	}

	return t('libresign', '{groupCount} groups · {userCount} accounts', {
		groupCount: String(groupCount),
		userCount: String(userCount),
	})
}

function resolveDefaultStatLabel(policyKey: string) {
	if (policyKey === 'groups_request_sign') {
		// TRANSLATORS Statistics label for effective baseline access in signature-request access policy cards.
		return t('libresign', 'Default access')
	}

	// TRANSLATORS Generic statistics label for effective baseline value shown in policy cards.
	return t('libresign', 'Default')
}

function resolveOverridesStatLabel(policyKey: string) {
	if (policyKey === 'groups_request_sign') {
		// TRANSLATORS Statistics label counting scoped exceptions in signature-request access policy cards.
		return t('libresign', 'Custom overrides')
	}

	// TRANSLATORS Generic statistics label counting non-default policy rules in cards.
	return t('libresign', 'Custom rules')
}

function toggleCatalogLayout() {
	catalogState.toggleCatalogLayout()
}

function onSettingsFilterModelValueChange(value: string | number) {
	catalogState.onSettingsFilterChange(String(value))
}

function toggleCatalogCollapsed() {
	catalogState.toggleCatalogCollapsed()
}

function isCategoryExpandedForRender(category: CatalogCategoryKey): boolean {
	if (hasActiveFilter.value) {
		// Search results must be visible even when the persisted state is collapsed.
		return true
	}

	return catalogState.isCategoryExpanded(category)
}

function handleCategoryChipNavigation(category: CatalogCategoryKey, event?: MouseEvent) {
	if (!hasActiveFilter.value && !catalogState.isCategoryExpanded(category)) {
		catalogState.toggleCategoryCollapsed(category)
	}

	navigation.scrollToCategory(category, event)
}

function updateViewportMode() {
	isSmallViewport.value = window.innerWidth <= 960
	navigation.reconnectSectionObserver()
	navigation.requestCategoryNavigationSync()
}

async function handleSaveDraft() {
	if (!state.canSaveDraft || saveStatus.value === 'saving') {
		return
	}

	saveStatus.value = 'saving'
	await nextTick()
	await state.saveDraft()

	// When save succeeds, the editor draft is cleared by state.saveDraft().
	// Ensure the scope chooser does not remain visible behind the saved flow.
	if (!state.editorDraft) {
		showCreateScopeDialog.value = false
		selectedCreateScope.value = null
	}

	saveStatus.value = 'saved'

	if (saveFeedbackTimeout.value !== null) {
		window.clearTimeout(saveFeedbackTimeout.value)
	}

	saveFeedbackTimeout.value = window.setTimeout(() => {
		saveStatus.value = 'idle'
		saveFeedbackTimeout.value = null
	}, 1300)
}

function promptRuleRemoval(ruleId: string, scope: CreateRuleScope, targetLabel: string) {
	const help = scope === 'system'
		? t('libresign', 'Removing this custom default restores the default behavior for everyone.')
		: scope === 'group'
			? t('libresign', 'Removing this rule restores inherited behavior for this group.')
			: t('libresign', 'Removing this rule will restore inherited behavior for this account.')

	pendingRemoval.value = { ruleId, scope, targetLabel, help }
}

function promptBulkRuleRemoval() {
	if (crudSelectedRowsCount.value === 0) {
		return
	}

	pendingRemoval.value = {
		ruleIds: [...selectedCrudRuleIds.value],
		help: t('libresign', 'Removing these rules restores inherited behavior for the selected targets.'),
	}
}

function updateRuleActionsOpen(ruleKey: string, open: boolean) {
	openRuleActionsKey.value = open ? ruleKey : (openRuleActionsKey.value === ruleKey ? null : openRuleActionsKey.value)
}

function closeOpenActionsMenu() {
	openRuleActionsKey.value = null
	const activeElement = document.activeElement
	if (activeElement instanceof HTMLElement) {
		activeElement.blur()
	}
}

function handleEditRule(scope: CreateRuleScope, ruleId: string) {
	closeOpenActionsMenu()
	openRuleEditor(scope, ruleId)
}

function handlePromptRuleRemoval(ruleId: string, scope: CreateRuleScope, targetLabel: string) {
	closeOpenActionsMenu()
	promptRuleRemoval(ruleId, scope, targetLabel)
}

function handlePromptBulkRuleRemoval() {
	closeOpenActionsMenu()
	promptBulkRuleRemoval()
}

function onVisibleCrudRowsSelectionChange(selected: boolean) {
	toggleVisibleCrudRowsSelection(selected)
}

function onCrudRowSelectionChange(ruleId: string, selected: boolean) {
	toggleCrudRowSelection(ruleId, selected)
}

function cancelRuleRemoval() {
	pendingRemoval.value = null
}

function cancelDiscardDialog() {
	const action = pendingDiscardAction.value
	pendingDiscardAction.value = null

	if (
		(action === 'back-create-rule' || action === 'cancel-create-rule')
		&& state.editorDraft
	) {
		// Re-mount editor dialog to avoid stale hidden state when discard prompt
		// is dismissed via ESC while the editor close was already initiated.
		ruleDialogInstanceKey.value += 1
	}
}

function confirmDiscardDialog() {
	const action = pendingDiscardAction.value
	pendingDiscardAction.value = null

	if (action === 'back-create-rule') {
		state.cancelEditor()
		selectedCreateScope.value = null
		return
	}

	if (action === 'cancel-create-rule') {
		cancelCreateScopeDialog()
		return
	}

	if (action === 'cancel-editor') {
		state.cancelEditor()
		return
	}

	if (action === 'close-setting') {
		state.closeSetting()
		if (clearCatalogFocusOnClose.value) {
			void nextTick().then(() => {
				const activeElement = document.activeElement
				if (activeElement instanceof HTMLElement) {
					activeElement.blur()
				}
			})
		}
		clearCatalogFocusOnClose.value = false
	}

	openRuleActionsKey.value = null
}

async function confirmRuleRemoval() {
	if (!pendingRemoval.value || isRemovingRule.value) {
		return
	}

	const removalRequest = pendingRemoval.value

	isRemovingRule.value = true
	try {
		const ruleIds = removalRequest.ruleIds ?? (removalRequest.ruleId ? [removalRequest.ruleId] : [])
		await state.removeRules(ruleIds)
		if (removalRequest.ruleIds) {
			// TRANSLATORS {count} is the number of policy rules removed in bulk; after removal, inherited policy values apply.
			removalFeedback.value = t('libresign', '{count} rules removed. Inherited behavior is now active.', { count: String(ruleIds.length) })
		} else if (removalRequest.scope === 'system') {
			// TRANSLATORS Feedback shown after deleting a system-level custom default policy rule.
			removalFeedback.value = t('libresign', 'Custom default removed. The default behavior for everyone is active again.')
		} else if (removalRequest.scope === 'group') {
			// TRANSLATORS Feedback shown after deleting a group-level custom policy rule.
			removalFeedback.value = t('libresign', 'Group custom rule removed. Inherited behavior is now active.')
		} else {
			// TRANSLATORS Feedback shown after deleting an account-level custom policy rule.
			removalFeedback.value = t('libresign', 'Account custom rule removed. Inherited behavior is now active.')
		}
		clearCrudSelection()

		if (removalFeedbackTimeout.value !== null) {
			window.clearTimeout(removalFeedbackTimeout.value)
		}

		removalFeedbackTimeout.value = window.setTimeout(() => {
			removalFeedback.value = null
			removalFeedbackTimeout.value = null
		}, REMOVAL_FEEDBACK_DURATION_MS)

		pendingRemoval.value = null
	} finally {
		isRemovingRule.value = false
	}
}

function handleCrudTableScroll(event: Event) {
	if (state.rulesLoading || loadingMoreCrudRows.value || !hasMoreCrudRows.value) {
		return
	}

	const target = event.target as HTMLElement | null
	if (!target) {
		return
	}

	const distanceToBottom = target.scrollHeight - target.scrollTop - target.clientHeight
	if (distanceToBottom <= 160) {
		void loadMoreCrudRows()
	}
}

function requestCancelEditor() {
	if (saveStatus.value === 'saving') {
		return
	}

	if (state.isDraftDirty) {
		pendingDiscardAction.value = 'cancel-editor'
		return
	}

	state.cancelEditor()
}

function requestCloseRuleDialog() {
	if (saveStatus.value === 'saving') {
		return
	}

	if (!state.editorDraft) {
		cancelCreateScopeDialog()
		return
	}

	if (showCreateScopeDialog.value && state.editorMode === 'create') {
		if (state.isDraftDirty) {
			pendingDiscardAction.value = 'cancel-create-rule'
			return
		}

		cancelCreateScopeDialog()
		return
	}

	requestCancelEditor()
}

function requestCloseSetting() {
	if (saveStatus.value === 'saving' || isRemovingRule.value) {
		return
	}

	if (state.isDraftDirty) {
		pendingDiscardAction.value = 'close-setting'
		return
	}

	state.closeSetting()
	if (clearCatalogFocusOnClose.value) {
		void nextTick().then(() => {
			const activeElement = document.activeElement
			if (activeElement instanceof HTMLElement) {
				activeElement.blur()
			}
		})
	}
	clearCatalogFocusOnClose.value = false
}

onMounted(async () => {
	updateViewportMode()
	catalogState.catalogLayout.value = userConfigStore.policy_workbench_catalog_compact_view ? 'compact' : 'cards'
	catalogState.isCatalogCollapsed.value = Boolean(userConfigStore.policy_workbench_catalog_collapsed)
	catalogState.categoryCollapsedState.value = catalogState.normalizeCategoryCollapsedConfig(userConfigStore.policy_workbench_category_collapsed_state as Record<string, unknown> | undefined)
	if (!userConfigStore.policy_workbench_category_collapsed_state) {
		catalogState.setAllCategoriesCollapsed(catalogState.isCatalogCollapsed.value)
	}
	catalogState.syncCatalogCollapsedFromSections()
	isRtl.value = document.documentElement.dir.toLowerCase() === 'rtl'
	window.addEventListener('resize', updateViewportMode, { passive: true })
	await policiesStore.fetchEffectivePolicies()
	if (state.viewMode === 'group-admin') {
		void state.probeGroupAccess()
	}
	await nextTick()
	navigation.attachScrollListener()
	navigation.reconnectSectionObserver()
	navigation.updateBackToTopVisibility()
})

onBeforeUnmount(() => {
	window.removeEventListener('resize', updateViewportMode)
	if (saveFeedbackTimeout.value !== null) {
		window.clearTimeout(saveFeedbackTimeout.value)
	}

	if (removalFeedbackTimeout.value !== null) {
		window.clearTimeout(removalFeedbackTimeout.value)
	}

	pendingDiscardAction.value = null
	openRuleActionsKey.value = null
	showCreateScopeDialog.value = false
	selectedCreateScope.value = null
})

watch(
	() => [visibleCategorySections.value.map((section: { key: CatalogCategoryKey }) => section.key).join(','), effectiveCatalogLayout.value, catalogState.isCatalogCollapsed.value],
	() => {
		if (!navigation.activeCategory.value && visibleCategorySections.value.length > 0) {
			navigation.activeCategory.value = visibleCategorySections.value[0]?.key ?? null
		}

		void nextTick(() => {
			navigation.attachScrollListener()
			navigation.reconnectSectionObserver()
			navigation.updateBackToTopVisibility()
		})
	},
)
</script>

<style scoped lang="scss">
.policy-workbench__section {
	width: calc(100% - var(--default-grid-baseline, 8px) * 14);
	max-width: none;
}

.policy-workbench {
	&__category-sections {
		display: flex;
		flex-direction: column;
		gap: 2.4rem;
	}

	&__empty-state {
		display: flex;
		flex-direction: column;
		gap: 0.65rem;

		p {
			margin: 0;
		}
	}

	&__empty-state-actions {
		display: flex;
		gap: 0.6rem;
		flex-wrap: wrap;
	}

	&__editor-modal-body {
		width: min(100%, 42rem);
		margin: 0 auto;

		&--wide {
			width: min(100%, 64rem);
		}
	}

}

@media (max-width: 960px) {
	.policy-workbench {
		&__category-sections {
			gap: 1.8rem;
		}

		&__empty-state-actions {
			width: 100%;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}
	}
}
</style>

<!-- Global styles for teleported elements (outside scoped component tree) -->
<style>
.policy-workbench__back-to-top {
	position: fixed !important;
	inset-inline-end: 1.25rem;
	bottom: 1.25rem;
	z-index: 9997;
}

.policy-workbench__back-to-top .button-vue {
	border-radius: 999px;
	box-shadow: 0 6px 20px color-mix(in srgb, var(--color-box-shadow) 22%, transparent);
}

.policy-workbench-back-to-top-enter-active,
.policy-workbench-back-to-top-leave-active {
	transition: opacity 0.2s ease, transform 0.2s ease;
}

.policy-workbench-back-to-top-enter-from,
.policy-workbench-back-to-top-leave-to {
	opacity: 0;
	transform: translateY(0.5rem);
}
</style>
