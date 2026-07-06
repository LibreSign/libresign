<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="showToolbar" class="policy-workbench__table-toolbar-row policy-workbench__table-toolbar-row--crud">
		<div class="policy-workbench__search-with-chips">
			<NcAppNavigationSearch
				:model-value="crudSearch"
				:label="searchRulesLabel"
				:placeholder="searchRulesLabel"
				@update:modelValue="handleCrudSearchChange">
				<template #actions>
					<NcActions
						:open="scopeFilterOpen"
						:aria-label="filterRulesByScopeAriaLabel"
						:title="filterByScopeTitle"
						@update:open="emit('update:scopeFilterOpen', $event)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFilterVariant" :size="20" :title="filterByScopeTitle" />
						</template>
						<NcActionButton :model-value="crudScopeFilter === 'all'" @click="emitScopeFilter('all')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFilterVariant" :size="16" />
							</template>
							{{ allScopesLabel }}
						</NcActionButton>
						<NcActionButton :model-value="crudScopeFilter === 'system'" @click="emitScopeFilter('system')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiOfficeBuildingOutline" :size="16" />
							</template>
							{{ everyoneScopeLabel }}
						</NcActionButton>
						<NcActionButton :model-value="crudScopeFilter === 'group'" @click="emitScopeFilter('group')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiAccountMultipleOutline" :size="16" />
							</template>
							{{ groupScopeLabel }}
						</NcActionButton>
						<NcActionButton :model-value="crudScopeFilter === 'user'" @click="emitScopeFilter('user')">
							<template #icon>
								<NcIconSvgWrapper :path="mdiAccountOutline" :size="16" />
							</template>
							{{ accountScopeLabel }}
						</NcActionButton>
					</NcActions>
				</template>
			</NcAppNavigationSearch>

			<div v-if="activeScopeFilterChip" class="policy-workbench__crud-filter-chips">
				<NcChip :aria-label-close="removeFilterAriaLabel" :text="activeScopeFilterChip" @close="emitScopeFilter('all')" />
			</div>
		</div>

		<div v-if="crudSelectedRowsCount > 0" class="policy-workbench__bulk-actions">
			<NcButton
				variant="error"
				size="small"
				:disabled="isRemovingRule || rulesLoading"
				:aria-label="deleteSelectedRulesAriaLabel"
				@click="emit('prompt-bulk-rule-removal')">
				{{ deleteSelectedRulesButtonLabel(crudSelectedRowsCount) }}
			</NcButton>
			<NcButton
				variant="tertiary"
				size="small"
				:disabled="isRemovingRule || rulesLoading"
				:aria-label="clearSelectedRulesAriaLabel"
				@click="emit('clear-selection')">
				{{ clearSelectionButtonLabel }}
			</NcButton>
		</div>

		<NcButton
			variant="primary"
			size="small"
			:disabled="!hasCreatableScope"
			:title="createRuleDisabledReason || undefined"
			:aria-label="createRuleButtonLabel"
			class="policy-workbench__crud-create-cta"
			@click="emit('request-create-rule')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" :size="20" />
			</template>
			{{ createRuleButtonLabel }}
		</NcButton>
	</div>

	<p v-if="displayedCrudRows.length > 0 && createRuleDisabledReason" class="policy-workbench__table-note policy-workbench__table-note--align-right">
		{{ createRuleDisabledReason }}
	</p>

	<p v-if="displayedCrudRows.length > 0 && createUserOverrideDisabledReason && crudScopeFilter === 'user'" class="policy-workbench__table-note">
		{{ personalRulesInheritanceNotice }}
	</p>

	<div v-if="rulesLoading" class="policy-workbench__table-loading" aria-live="polite" aria-busy="true">
		<NcLoadingIcon :size="32" />
		<p>{{ loadingRulesLabel }}</p>
	</div>

	<div v-else class="policy-workbench__table-scroll" @scroll.passive="emit('table-scroll', $event)">
		<template v-if="displayedCrudRows.length > 0">
			<table class="policy-workbench__table">
				<thead>
					<tr>
						<th class="policy-workbench__table-select-col">
							<NcCheckboxRadioSwitch
								:aria-label="selectAllVisibleRulesAriaLabel"
								:disabled="!hasSelectableVisibleCrudRows"
								:model-value="crudAllVisibleRowsSelected"
								@update:modelValue="handleVisibleSelectionChange" />
						</th>
						<th>{{ typeColumnLabel }}</th>
						<th>{{ targetColumnLabel }}</th>
						<th>{{ valueColumnLabel }}</th>
						<th>{{ actionsColumnLabel }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in displayedCrudRows" :key="row.key" :class="{ 'policy-workbench__table-row--selected': isCrudRowSelected(row.ruleId ?? row.key) }">
						<td class="policy-workbench__table-select-col">
							<template v-if="row.canRemove">
								<NcCheckboxRadioSwitch
									:aria-label="selectRuleForBulkDeleteAriaLabel"
									:model-value="isCrudRowSelected(row.ruleId ?? row.key)"
									@update:modelValue="handleRowSelectionChange(row.ruleId ?? row.key, $event)" />
							</template>
							<span v-else class="policy-workbench__table-select-placeholder" aria-hidden="true">-</span>
						</td>
						<td>{{ crudScopeLabel(row.scope) }}</td>
						<td>{{ row.targetLabel }}</td>
						<td>{{ row.valueLabel }}</td>
						<td class="policy-workbench__table-actions">
							<template v-if="row.ruleId">
								<NcActions
									:aria-label="ruleActionsAriaLabel"
									:open="openRuleActionsKey === row.key"
									@update:open="emit('update-rule-actions-open', { ruleKey: row.key, open: $event })">
									<NcActionButton @click="emit('edit-rule', { scope: row.scope, ruleId: row.ruleId as string })">
										<template #icon>
											<NcIconSvgWrapper :path="mdiPencil" :size="16" />
										</template>
										{{ editActionLabel }}
									</NcActionButton>
									<NcActionButton v-if="row.canRemove" @click="emit('prompt-rule-removal', { ruleId: row.ruleId as string, scope: row.scope, targetLabel: row.targetLabel })">
										<template #icon>
											<NcIconSvgWrapper :path="mdiDelete" :size="16" />
										</template>
										{{ removeActionLabel }}
									</NcActionButton>
								</NcActions>
							</template>
							<span v-else class="policy-workbench__table-note">{{ readOnlyLabel }}</span>
						</td>
					</tr>
				</tbody>
			</table>
			<div v-if="loadingMoreCrudRows" class="policy-workbench__table-loading-more" aria-live="polite" aria-busy="true">
				<NcLoadingIcon :size="20" />
				<span>{{ loadingMoreRulesLabel }}</span>
			</div>
		</template>
		<template v-else>
			<div class="policy-workbench__table-empty-state">
				<NcEmptyContent
					class="policy-workbench__table-empty-content"
					:name="crudEmptyStateName"
					:description="crudEmptyStateDescription">
					<template #icon>
						<NcIconSvgWrapper :path="crudEmptyStateIconPath" :size="20" />
					</template>
					<template v-if="!hasActiveCrudFilters" #action>
						<NcButton
							variant="primary"
							:aria-label="createRuleButtonLabel"
							@click="emit('request-create-rule')">
							{{ createRuleButtonLabel }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import {
	mdiAccountMultipleOutline,
	mdiAccountOutline,
	mdiDelete,
	mdiFilterVariant,
	mdiOfficeBuildingOutline,
	mdiPencil,
	mdiPlus,
} from '@mdi/js'
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import type { CatalogCrudRow, CatalogCrudScope } from '../composables/useCatalogCrudTable'

defineOptions({
	name: 'CatalogCrudRulesTable',
})

const emit = defineEmits<{
	'crud-search-change': [value: string]
	'update:scopeFilterOpen': [open: boolean]
	'crud-scope-filter': [payload: { value: 'all' | CatalogCrudScope, selected: boolean }]
	'visible-selection-change': [selected: boolean]
	'row-selection-change': [payload: { ruleId: string, selected: boolean }]
	'update-rule-actions-open': [payload: { ruleKey: string, open: boolean }]
	'edit-rule': [payload: { scope: CatalogCrudScope, ruleId: string }]
	'prompt-rule-removal': [payload: { ruleId: string, scope: CatalogCrudScope, targetLabel: string }]
	'prompt-bulk-rule-removal': []
	'clear-selection': []
	'request-create-rule': []
	'table-scroll': [event: Event]
}>()

const props = defineProps<{
	crudSearch: string
	crudScopeFilter: 'all' | CatalogCrudScope
	scopeFilterOpen: boolean
	displayedCrudRows: CatalogCrudRow[]
	loadingMoreCrudRows: boolean
	crudSelectedRowsCount: number
	crudAllVisibleRowsSelected: boolean
	hasSelectableVisibleCrudRows: boolean
	isRemovingRule: boolean
	rulesLoading: boolean
	hasCreatableScope: boolean
	createRuleDisabledReason: string
	createUserOverrideDisabledReason?: string
	hasActiveCrudFilters: boolean
	crudEmptyStateName: string
	crudEmptyStateDescription: string
	crudEmptyStateIconPath: string
	activeScopeFilterChip: string
	openRuleActionsKey: string | null
	crudScopeLabel: (scope: CatalogCrudScope) => string
	isCrudRowSelected: (ruleId: string) => boolean
}>()

// TRANSLATORS Label and placeholder for the CRUD table search field used to filter policy rules.
const searchRulesLabel = t('libresign', 'Search rules')
// TRANSLATORS Aria label for the rules scope filter menu button.
const filterRulesByScopeAriaLabel = t('libresign', 'Filter rules by scope')
// TRANSLATORS Tooltip and icon title for the rules scope filter menu.
const filterByScopeTitle = t('libresign', 'Filter by scope')
// TRANSLATORS Filter option label that shows rules from every scope.
const allScopesLabel = t('libresign', 'All scopes')
// TRANSLATORS Scope label representing rules that apply to everyone.
const everyoneScopeLabel = t('libresign', 'Everyone')
// TRANSLATORS Scope label representing group-level rules.
const groupScopeLabel = t('libresign', 'Group')
// TRANSLATORS Scope label representing account-level rules.
const accountScopeLabel = t('libresign', 'Account')
// TRANSLATORS Aria label for removing the active scope filter chip.
const removeFilterAriaLabel = t('libresign', 'Remove filter')
// TRANSLATORS Aria label for the bulk action that deletes currently selected rules.
const deleteSelectedRulesAriaLabel = t('libresign', 'Delete selected rules')
// TRANSLATORS Bulk action label; {count} is the number of currently selected rules.
const deleteSelectedRulesButtonLabel = (count: number) => t('libresign', 'Delete selected ({count})', { count: String(count) })
// TRANSLATORS Aria label for the bulk action that clears the current rule selection.
const clearSelectedRulesAriaLabel = t('libresign', 'Clear selected rules')
// TRANSLATORS Bulk action label that clears the current rule selection.
const clearSelectionButtonLabel = t('libresign', 'Clear selection')
// TRANSLATORS Action label for creating a new policy rule.
const createRuleButtonLabel = t('libresign', 'Create rule')
// TRANSLATORS Helper text warning that some account rules are blocked by inherited group requirements.
const personalRulesInheritanceNotice = t('libresign', 'Some accounts may not allow personal rules because their group rule requires inheritance.')
// TRANSLATORS Loading state shown while the rules table data is being fetched.
const loadingRulesLabel = t('libresign', 'Loading rules…')
// TRANSLATORS Aria label for the checkbox that selects all currently visible rules.
const selectAllVisibleRulesAriaLabel = t('libresign', 'Select all visible rules')
// TRANSLATORS Table column heading for the rule scope/type.
const typeColumnLabel = t('libresign', 'Type')
// TRANSLATORS Table column heading for the rule target entity.
const targetColumnLabel = t('libresign', 'Target')
// TRANSLATORS Table column heading for the configured rule value.
const valueColumnLabel = t('libresign', 'Value')
// TRANSLATORS Table column heading for the available rule actions.
const actionsColumnLabel = t('libresign', 'Actions')
// TRANSLATORS Aria label for a checkbox that selects one rule for bulk deletion.
const selectRuleForBulkDeleteAriaLabel = t('libresign', 'Select rule for bulk delete')
// TRANSLATORS Aria label for the actions menu attached to one rule row.
const ruleActionsAriaLabel = t('libresign', 'Rule actions')
// TRANSLATORS Action label for editing an existing policy rule.
const editActionLabel = t('libresign', 'Edit')
// TRANSLATORS Action label for removing an existing policy rule.
const removeActionLabel = t('libresign', 'Remove')
// TRANSLATORS Status text shown for rows that cannot be modified.
const readOnlyLabel = t('libresign', 'Read only')
// TRANSLATORS Loading state shown while more rows are appended to the rules table.
const loadingMoreRulesLabel = t('libresign', 'Loading more rules…')

const showToolbar = computed(() => {
	return props.displayedCrudRows.length > 0 || props.crudSearch.trim().length > 0 || props.crudScopeFilter !== 'all'
})

function emitScopeFilter(value: 'all' | CatalogCrudScope) {
	emit('crud-scope-filter', { value, selected: true })
}

function handleCrudSearchChange(value: string | number) {
	emit('crud-search-change', String(value ?? ''))
}

function handleVisibleSelectionChange(selected: boolean) {
	emit('visible-selection-change', selected)
}

function handleRowSelectionChange(ruleId: string, selected: boolean) {
	emit('row-selection-change', { ruleId, selected })
}
</script>

<style scoped lang="scss">
.policy-workbench {
	&__table-toolbar-row {
		display: flex;
		align-items: center;
		gap: 0.65rem;
		flex-wrap: wrap;
		margin-bottom: 0.75rem;

		&--crud {
			align-items: flex-start;
			gap: 0.85rem;

			.policy-workbench__search-with-chips {
				flex: 1 1 360px;
				min-width: min(100%, 420px);
				display: flex;
				flex-direction: column;
				gap: 0.35rem;

				:deep(.app-navigation-search) {
					width: 100%;
				}
			}

			:deep(.app-navigation-search__actions) {
				display: inline-flex;
				align-items: center;
				gap: 0.25rem;
			}

			:deep(.app-navigation-search__actions--hidden) {
				margin-inline-start: 0;
			}

			:deep(.app-navigation-search__actions.hidden-visually) {
				position: static;
				width: auto;
				height: auto;
				margin: 0;
				overflow: visible;
				clip: auto;
				clip-path: none;
				white-space: normal;
			}
		}
	}

	&__crud-filter-chips {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		flex-wrap: wrap;
	}

	&__bulk-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}

	&__crud-create-cta {
		:deep(.button-vue) {
			white-space: nowrap;
			font-weight: 600;
		}
	}

	&__table-scroll {
		flex: 1;
		min-height: 0;
		overflow-x: auto;
		overflow-y: auto;
		border: none;
		border-radius: 0;
	}

	&__table {
		width: 100%;
		border-collapse: collapse;
		font-size: 0.87rem;

		th,
		td {
			text-align: left;
			padding: 0.48rem 0.72rem;
			vertical-align: middle;
		}

		th {
			position: sticky;
			top: 0;
			z-index: 1;
			font-size: 0.78rem;
			font-weight: 600;
			color: var(--color-text-maxcontrast);
			border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);
			background: var(--color-main-background);
		}

		tbody td {
			border-bottom: 1px solid color-mix(in srgb, var(--color-border) 40%, transparent);
		}

		tr:last-child td {
			border-bottom: none;
		}

		tbody tr:hover td {
			background: color-mix(in srgb, var(--color-main-text) 1%, transparent);
		}
	}

	&__table-select-col {
		width: 1%;
		white-space: nowrap;
	}

	&__table-row--selected td {
		background: color-mix(in srgb, var(--color-primary-element) 8%, var(--color-main-background));
	}

	&__table-actions {
		white-space: nowrap;

		:deep(.actions) {
			justify-content: flex-start;
		}

		:deep(.actions__primary) {
			opacity: 0.8;
		}

		:deep(.actions__primary:hover),
		:deep(.actions__primary:focus-visible) {
			opacity: 1;
		}

		:deep(.action-item) {
			font-size: 0.84rem;
		}
	}

	&__table-select-placeholder {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 1.2rem;
		color: var(--color-text-maxcontrast);
		opacity: 0.7;
	}

	&__table-empty-state {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 8rem;
		padding-block: 0.75rem;
	}

	&__table-empty-content {
		padding-block: 0.45rem;

		:deep(.empty-content__name),
		:deep(.empty-content__description) {
			margin: 0;
		}

		:deep(.empty-content__name) {
			font-size: 0.86rem;
		}

		:deep(.empty-content__description) {
			font-size: 0.82rem;
			color: var(--color-text-maxcontrast);
		}
	}

	&__table-note {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);

		&--align-right {
			text-align: right;
		}
	}

	&__table-loading,
	&__table-loading-more {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.55rem;
		color: var(--color-text-maxcontrast);
	}

	&__table-loading {
		padding: 1rem 0;
		flex-direction: column;

		p {
			margin: 0;
		}
	}

	&__table-loading-more {
		padding-top: 0.75rem;
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__table-toolbar-row {
			flex-direction: column;
			align-items: stretch;
		}
	}
}

@media (max-width: 720px) {
	.policy-workbench {
		&__table-toolbar-row--crud {
			align-items: stretch;
		}

		&__crud-create-cta {
			width: 100%;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__crud-filter-chips {
			justify-content: flex-start;
		}

		&__table-note--align-right {
			text-align: left;
		}
	}
}
</style>
