/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { computed, nextTick, ref } from 'vue'

const { mockWorkbenchStateStore } = vi.hoisted(() => ({
	mockWorkbenchStateStore: {
		overrides: {} as Record<string, unknown>,
	},
}))

function resetWorkbenchStateOverrides() {
	mockWorkbenchStateStore.overrides = {}
}

function createMockWorkbenchState() {
	const overrides = mockWorkbenchStateStore.overrides as {
		activeDefinition?: Record<string, unknown>
		[key: string]: unknown
	}

	const baseState = {
		visibleSettingSummaries: [],
		rulesLoading: false,
		viewMode: 'system-admin',
		canManageGroups: true,
		activeDefinition: {
			key: 'signature_flow',
			title: 'Signature flow',
			description: 'Signature flow configuration',
			editor: null,
			supportedScopes: ['system', 'group', 'user'],
			normalizeAllowChildOverride: (_scope: string, value: boolean) => value,
		},
		editorDraft: null,
		editorInitialTargetIds: [],
		inheritedSystemRule: null,
		hasGlobalDefault: false,
		summary: null,
		visibleGroupRules: [],
		visibleUserRules: [],
		createGroupOverrideDisabledReason: '',
		createUserOverrideDisabledReason: '',
		availableTargets: [],
		loadingTargets: false,
		duplicateMessage: '',
		canSaveDraft: false,
		highlightedRuleId: null,
		editorMode: 'create',
		isSettingDialogOpen: false,
		settingsLoading: false,
		activeSettingKey: null,
		editorTouched: false,
		isEditingSystemRule: false,
		isEditingRule: false,
		searchAvailableTargets: vi.fn(),
		updateDraftTargets: vi.fn(),
		updateDraftValue: vi.fn(),
		markDraftTouched: vi.fn(),
		updateDraftAllowOverride: vi.fn(),
		openSetting: vi.fn(),
		startEditor: vi.fn(),
		cancelEditor: vi.fn(),
		closeSetting: vi.fn(),
		saveDraft: vi.fn().mockResolvedValue(undefined),
		removeRules: vi.fn().mockResolvedValue(undefined),
		probeGroupAccess: vi.fn(),
	}

	return {
		...baseState,
		...overrides,
		activeDefinition: {
			...baseState.activeDefinition,
			...(overrides.activeDefinition ?? {}),
		},
	}
}

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, params?: Record<string, string>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, value)
		}, text)
	},
	n: (_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural),
	translate: (_app: string, text: string) => text,
	translatePlural: (_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural),
	getLanguage: () => 'en',
	isRTL: () => false,
}))

vi.mock('../../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: vi.fn().mockResolvedValue(undefined),
		getPolicy: vi.fn().mockReturnValue(null),
	}),
}))

vi.mock('../../../../../store/userconfig.js', () => ({
	useUserConfigStore: () => ({
		policy_workbench_catalog_compact_view: false,
		policy_workbench_catalog_collapsed: false,
		policy_workbench_category_collapsed_state: {},
	}),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench', () => ({
	createRealPolicyWorkbenchState: () => createMockWorkbenchState(),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogState', () => ({
	useCatalogState: () => ({
		settingsFilter: ref(''),
		catalogLayout: ref<'cards' | 'compact'>('cards'),
		isCatalogCollapsed: ref(false),
		categoryCollapsedState: ref({}),
		clearSettingsFilter: vi.fn(),
		toggleCatalogLayout: vi.fn(),
		toggleCatalogCollapsed: vi.fn(),
		toggleCategoryCollapsed: vi.fn(),
		onSettingsFilterChange: vi.fn(),
		isCategoryExpanded: vi.fn().mockReturnValue(true),
		normalizeCategoryCollapsedConfig: vi.fn().mockReturnValue({}),
		setAllCategoriesCollapsed: vi.fn(),
		syncCatalogCollapsedFromSections: vi.fn(),
	}),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogPresentation', () => ({
	useCatalogPresentation: () => ({
		filteredSettingSummaries: computed(() => []),
		visibleCategorySections: computed(() => []),
		effectiveCatalogLayout: computed(() => 'cards'),
		hasActiveFilter: computed(() => false),
		hasVisibleCategorySections: computed(() => false),
		showCategoryNavigation: computed(() => false),
		catalogViewButtonLabel: computed(() => 'view'),
		catalogCollapseButtonLabel: computed(() => 'collapse'),
	}),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogInteractions', () => ({
	useCatalogInteractions: () => ({
		clearCatalogFocusOnClose: vi.fn(),
		markSelectionGesture: vi.fn(),
		trackPress: vi.fn(),
		openSettingFromPointer: vi.fn(),
		openSettingFromAction: vi.fn(),
		openSettingFromKeyboard: vi.fn(),
		highlightText: (value: string) => value,
	}),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useNavigation', () => ({
	useNavigation: () => ({
		catalogToolbarRef: ref<HTMLElement | null>(null),
		categoryChipsScroller: ref<HTMLElement | null>(null),
		activeCategory: ref<string | null>(null),
		showBackToTop: ref(false),
		setCategorySectionRef: () => vi.fn(),
		handleCategoryChipNavigation: vi.fn(),
		scrollToTop: vi.fn(),
		attachScrollListener: vi.fn(),
		reconnectSectionObserver: vi.fn(),
		updateBackToTopVisibility: vi.fn(),
		requestCategoryNavigationSync: vi.fn(),
		scrollToCategory: vi.fn(),
	}),
}))

vi.mock('../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogCrudTable', () => ({
	useCatalogCrudTable: () => ({
		crudSearch: ref(''),
		crudScopeFilter: ref<'all' | 'system' | 'group' | 'user'>('all'),
		scopeFilterOpen: ref(false),
		displayedCrudRows: computed(() => [
			{
				key: 'group-finance',
				ruleId: 'group-finance',
				scope: 'group',
				targetLabel: 'finance',
				valueLabel: 'parallel',
				canRemove: false,
			},
			{
				key: 'user-john',
				ruleId: 'user-john',
				scope: 'user',
				targetLabel: 'john',
				valueLabel: 'parallel',
				canRemove: true,
			},
		]),
		hasMoreCrudRows: computed(() => false),
		loadingMoreCrudRows: ref(false),
		selectedCrudRowsCount: computed(() => 0),
		allVisibleCrudRowsSelected: computed(() => false),
		hasSelectableVisibleCrudRows: computed(() => true),
		selectedCrudRuleIds: ref(new Set<string>()),
		isCrudRowSelected: () => false,
		toggleCrudRowSelection: vi.fn(),
		toggleVisibleCrudRowsSelection: vi.fn(),
		clearCrudSelection: vi.fn(),
		loadMoreCrudRows: vi.fn(),
		activeScopeFilterChip: computed(() => ''),
		crudScopeLabel: (scope: string) => scope,
		onCrudSearchChange: vi.fn(),
		setCrudScopeFilter: vi.fn(),
	}),
}))

import Catalog from '../../../../../views/Settings/PolicyWorkbench/Catalog/Catalog.vue'

afterEach(() => {
	resetWorkbenchStateOverrides()
})

describe('Catalog.vue CRUD permissions rendering', () => {
	it('hides remove action and row checkbox for non-removable rules', async () => {
		const wrapper = shallowMount(Catalog, {
			global: {
				stubs: {
					teleport: true,
					transition: true,
					NcSettingsSection: {
						template: '<section class="nc-settings-section"><slot /></section>',
					},
					NcDialog: {
						template: '<div class="nc-dialog"><slot /></div>',
					},
					NcActions: {
						template: '<div class="nc-actions"><slot /></div>',
					},
					NcActionButton: {
						template: '<button class="nc-action-button"><slot /></button>',
					},
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<input class="nc-checkbox-radio-switch" type="checkbox" :checked="modelValue" />',
					},
				},
			},
		})

		await nextTick()

		const rows = wrapper.findAll('tbody tr')
		expect(rows).toHaveLength(2)

		const nonRemovableRow = rows.find((row) => row.text().includes('finance'))
		expect(nonRemovableRow).toBeDefined()
		expect(nonRemovableRow?.findAll('.nc-checkbox-radio-switch')).toHaveLength(0)
		expect(nonRemovableRow?.find('.policy-workbench__table-select-placeholder').exists()).toBe(true)
		expect(nonRemovableRow?.findAll('.nc-action-button').some((button) => button.text().includes('Remove'))).toBe(false)

		const removableRow = rows.find((row) => row.text().includes('john'))
		expect(removableRow).toBeDefined()
		expect(removableRow?.findAll('.nc-checkbox-radio-switch')).toHaveLength(1)
		expect(removableRow?.findAll('.nc-action-button').some((button) => button.text().includes('Remove'))).toBe(true)
	})

	it('hides scope group selector when request-sign rules derive scope from allow and deny groups', async () => {
		mockWorkbenchStateStore.overrides = {
			activeDefinition: {
				key: 'groups_request_sign',
				title: 'Authorized requester groups',
				description: 'Delegated requester group management',
				editor: {},
				supportedScopes: ['group'],
				extractScopeTargets: () => ['board'],
				normalizeAllowChildOverride: (_scope: string, value: boolean) => value,
			},
			editorMode: 'create',
			editorDraft: {
				scope: 'group',
				value: '{"allowGroups":["board"],"denyGroups":[]}',
				allowChildOverride: true,
				targetIds: ['board'],
			},
			editorInitialTargetIds: ['board'],
			availableTargets: [
				{ id: 'board', displayName: 'Board' },
				{ id: 'company', displayName: 'Company' },
			],
		}

		const wrapper = shallowMount(Catalog, {
			global: {
				stubs: {
					teleport: true,
					transition: true,
					NcSettingsSection: {
						template: '<section class="nc-settings-section"><slot /></section>',
					},
					NcDialog: {
						template: '<div class="nc-dialog"><slot /></div>',
					},
					PolicyRuleEditorPanel: {
						props: ['hideTargetSelector'],
						template: '<div class="policy-rule-editor-panel" :data-hide-target-selector="String(hideTargetSelector)" />',
					},
				},
			},
		})

		await nextTick()

		expect(wrapper.find('.policy-rule-editor-panel').attributes('data-hide-target-selector')).toBe('true')
	})
})
