<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Policy configuration')"
		:description="t('libresign', 'Manage policy settings with global defaults, group overrides, and user overrides.')">

		<div class="policy-workbench__catalog-toolbar">
			<div class="policy-workbench__catalog-search">
				<NcTextField
					:model-value="settingsFilter"
					:label="t('libresign', 'Search settings')"
					:placeholder="t('libresign', 'Search by setting name, summary, or hint')"
					@keydown.esc.prevent="clearSettingsFilter"
					@update:modelValue="onSettingsFilterChange" />
				<div class="policy-workbench__catalog-foot">
					<p class="policy-workbench__catalog-meta" aria-live="polite">
						{{ t('libresign', '{shown} of {total} settings visible', {
							shown: String(filteredSettingSummaries.length),
							total: String(state.visibleSettingSummaries.length),
						}) }}
					</p>
					<NcButton
						variant="tertiary"
						class="policy-workbench__clear-filter-button"
						:class="{ 'policy-workbench__clear-filter-button--hidden': !hasActiveFilter }"
						:aria-label="t('libresign', 'Clear settings filter')"
						:disabled="!hasActiveFilter"
						:tabindex="hasActiveFilter ? undefined : -1"
						@click="clearSettingsFilter">
						{{ t('libresign', 'Clear filter') }}
					</NcButton>
				</div>
			</div>

			<div class="policy-workbench__catalog-view-switch" role="group" :aria-label="t('libresign', 'Select settings layout')">
				<NcButton
					:aria-label="catalogViewButtonLabel"
					:title="catalogViewButtonLabel"
					:disabled="isSmallViewport"
					class="policy-workbench__catalog-view-button"
					@click="toggleCatalogLayout">
					<template #icon>
						<NcIconSvgWrapper v-if="effectiveCatalogLayout === 'cards'" :path="mdiFormatListBulletedSquare" />
						<NcIconSvgWrapper v-else :path="mdiViewGridOutline" />
					</template>
				</NcButton>
			</div>
		</div>

		<div v-if="effectiveCatalogLayout === 'cards'" class="policy-workbench__settings-grid">
			<article
				v-for="summary in filteredSettingSummaries"
				:key="summary.key"
				class="policy-workbench__setting-tile"
				tabindex="0"
				role="button"
				@pointerdown="trackPress('cards', summary.key, $event)"
				@mouseup="markSelectionGesture('cards', summary.key)"
				@click="openSettingFromPointer('cards', summary.key, $event)"
				@keydown.enter.prevent="state.openSetting(summary.key)"
				@keydown.space.prevent="state.openSetting(summary.key)">
				<div class="policy-workbench__setting-header">
					<div>
						<h3 v-html="highlightText(summary.title)"></h3>
						<p v-html="highlightText(summary.description)"></p>
					</div>
					<NcButton variant="secondary" class="policy-workbench__manage-button" :aria-label="t('libresign', 'Open setting policy')" @click.stop="state.openSetting(summary.key)">
						{{ t('libresign', 'Open policy') }}
					</NcButton>
				</div>

				<p class="policy-workbench__setting-hint">
					<span v-html="highlightText(summary.menuHint)"></span>
				</p>

				<p class="policy-workbench__origin-badge">
					{{ resolveSettingOrigin(summary.groupCount, summary.userCount) }}
				</p>

				<ul class="policy-workbench__setting-stats">
					<li>
						<strong>{{ t('libresign', 'Global default') }}:</strong>
						<span :title="summary.defaultSummary" v-html="highlightText(summary.defaultSummary)"></span>
					</li>
					<li>
						<strong>{{ t('libresign', 'Group overrides') }}:</strong>
						{{ summary.groupCount }}
					</li>
					<li>
						<strong>{{ t('libresign', 'User overrides') }}:</strong>
						{{ summary.userCount }}
					</li>
				</ul>
			</article>
		</div>

		<div v-else class="policy-workbench__settings-list" role="list">
			<article
				v-for="summary in filteredSettingSummaries"
				:key="summary.key"
				class="policy-workbench__settings-row"
				role="button"
				tabindex="0"
				@pointerdown="trackPress('list', summary.key, $event)"
				@mouseup="markSelectionGesture('list', summary.key)"
				@click="openSettingFromPointer('list', summary.key, $event)"
				@keydown.enter.prevent="state.openSetting(summary.key)"
				@keydown.space.prevent="state.openSetting(summary.key)">
				<div class="policy-workbench__settings-row-main">
					<h3 v-html="highlightText(summary.title)"></h3>
					<p v-html="highlightText(summary.description)"></p>
					<p class="policy-workbench__origin-badge policy-workbench__origin-badge--inline">
						{{ resolveSettingOrigin(summary.groupCount, summary.userCount) }}
					</p>
				</div>

				<div class="policy-workbench__settings-row-stats">
					<span class="policy-workbench__settings-row-stat policy-workbench__settings-row-stat--default" :title="summary.defaultSummary">
						<strong>{{ t('libresign', 'Global default') }}:</strong>
						<span v-html="highlightText(summary.defaultSummary)"></span>
					</span>
					<span class="policy-workbench__settings-row-stat policy-workbench__settings-row-stat--count"><strong>{{ t('libresign', 'Group overrides') }}:</strong> {{ summary.groupCount }}</span>
					<span class="policy-workbench__settings-row-stat policy-workbench__settings-row-stat--count"><strong>{{ t('libresign', 'User overrides') }}:</strong> {{ summary.userCount }}</span>
				</div>

				<NcButton variant="secondary" class="policy-workbench__manage-button" :aria-label="t('libresign', 'Open setting policy')" @click.stop="state.openSetting(summary.key)">
					{{ t('libresign', 'Open policy') }}
				</NcButton>
			</article>
		</div>

		<NcNoteCard v-if="filteredSettingSummaries.length === 0" type="info">
			<div class="policy-workbench__empty-state">
				<p>{{ t('libresign', 'No settings match this search. Try fewer keywords or clear the filter.') }}</p>
				<div class="policy-workbench__empty-state-actions">
					<NcButton variant="secondary" :aria-label="t('libresign', 'Clear settings filter')" :disabled="!hasActiveFilter" @click="clearSettingsFilter">
						{{ t('libresign', 'Clear filter') }}
					</NcButton>
					<NcButton variant="tertiary" :aria-label="t('libresign', 'Show all settings')" @click="clearSettingsFilter">
						{{ t('libresign', 'Show all settings') }}
					</NcButton>
				</div>
			</div>
		</NcNoteCard>

		<NcDialog
			v-if="state.activeDefinition"
			:name="state.activeDefinition.title"
			size="full"
			:can-close="true"
			@closing="requestCloseSetting()">
			<div class="policy-workbench__dialog">
				<div class="policy-workbench__main">
					<header class="policy-workbench__dialog-header">
						<h2>{{ state.activeDefinition.title }}</h2>
						<p class="policy-workbench__dialog-description">{{ state.activeDefinition.description }}</p>
					</header>

					<p
						v-if="removalFeedback"
						class="policy-workbench__removal-feedback"
						aria-live="polite">
						{{ removalFeedback }}
					</p>

					<div v-if="state.summary" class="policy-workbench__summary-line policy-workbench__summary-line--crud">
						<span class="policy-workbench__summary-caption">{{ t('libresign', 'Effective baseline') }}</span>
						<strong>{{ state.summary.currentBaseValue }}</strong>
						<span class="policy-workbench__value-source">
							{{ state.hasGlobalDefault ? t('libresign', 'Custom default') : t('libresign', 'System default') }}
						</span>
					</div>

					<div class="policy-workbench__table-toolbar-row policy-workbench__table-toolbar-row--crud">
						<NcTextField
							:model-value="crudSearch"
							:label="t('libresign', 'Search rules')"
							:placeholder="t('libresign', 'Search by scope, target, or value')"
							@update:modelValue="onCrudSearchChange" />
						<NcPopover :boundary="popoverBoundary">
							<template #trigger>
								<NcButton :aria-label="t('libresign', 'Filters')" :pressed="crudScopeFilter !== 'all'" variant="tertiary">
									<template #icon>
										<NcIconSvgWrapper :path="mdiFilterVariant" />
									</template>
									{{ t('libresign', 'Filters') }}
								</NcButton>
							</template>
							<template #default>
								<div class="policy-workbench__crud-filter-popover">
									<p class="policy-workbench__crud-filter-title">{{ t('libresign', 'Scope') }}</p>
									<div class="policy-workbench__crud-filter-options">
										<label class="policy-workbench__filter-option">
											<input type="radio" name="crudScope" :checked="crudScopeFilter === 'all'" @change="setCrudScopeFilter('all', true)" />
											<span>{{ t('libresign', 'All scopes') }}</span>
										</label>
										<label class="policy-workbench__filter-option">
											<input type="radio" name="crudScope" :checked="crudScopeFilter === 'system'" @change="setCrudScopeFilter('system', true)" />
											<span>{{ t('libresign', 'Instance') }}</span>
										</label>
										<label class="policy-workbench__filter-option">
											<input type="radio" name="crudScope" :checked="crudScopeFilter === 'group'" @change="setCrudScopeFilter('group', true)" />
											<span>{{ t('libresign', 'Group') }}</span>
										</label>
										<label class="policy-workbench__filter-option">
											<input type="radio" name="crudScope" :checked="crudScopeFilter === 'user'" @change="setCrudScopeFilter('user', true)" />
											<span>{{ t('libresign', 'User') }}</span>
										</label>
									</div>
									<NcButton v-if="crudScopeFilter !== 'all'" variant="tertiary" @click="setCrudScopeFilter('all', true)">
										{{ t('libresign', 'Clear filter') }}
									</NcButton>
								</div>
							</template>
						</NcPopover>
						<div v-if="state.viewMode === 'system-admin'" class="policy-workbench__crud-create">
							<NcButton variant="primary" size="small" :disabled="!hasCreatableScope" :title="createRuleDisabledReason || undefined" @click="requestCreateRule()">
								{{ t('libresign', 'Create rule') }}
							</NcButton>
							<p v-if="createRuleDisabledReason" class="policy-workbench__table-note policy-workbench__table-note--align-right">
								{{ createRuleDisabledReason }}
							</p>
						</div>
					</div>

					<div v-if="activeScopeFilterChip" class="policy-workbench__crud-filter-chips">
						<NcChip :aria-label-close="t('libresign', 'Remove filter')" :text="activeScopeFilterChip" @close="setCrudScopeFilter('all', true)" />
					</div>

					<p v-if="state.createUserOverrideDisabledReason" class="policy-workbench__table-note">
						{{ t('libresign', 'Some users may not allow user overrides because their group rule requires inheritance.') }}
					</p>

					<NcNoteCard v-if="!state.inheritedSystemRule && state.viewMode === 'system-admin'" type="info">
						<div class="policy-workbench__empty-state policy-workbench__empty-state--compact">
							<p>{{ t('libresign', 'No global default rule is defined yet. The instance is currently using the platform fallback.') }}</p>
							<NcButton variant="secondary" size="small" @click="state.startEditor({ scope: 'system' })">
								{{ t('libresign', 'Set global default') }}
							</NcButton>
						</div>
					</NcNoteCard>

					<div class="policy-workbench__table-scroll">
						<table class="policy-workbench__table">
							<thead>
								<tr>
									<th>{{ t('libresign', 'Scope') }}</th>
									<th>{{ t('libresign', 'Target') }}</th>
									<th>{{ t('libresign', 'Value') }}</th>
									<th>{{ t('libresign', 'Inheritance') }}</th>
									<th>{{ t('libresign', 'Actions') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="row in pagedCrudRows" :key="row.key">
									<td>{{ crudScopeLabel(row.scope) }}</td>
									<td>{{ row.targetLabel }}</td>
									<td>{{ row.valueLabel }}</td>
									<td class="policy-workbench__status">
										<small :class="{ 'policy-workbench__status--inherit': row.inheritanceLabel === t('libresign', 'Must inherit') }">
											{{ row.inheritanceLabel }}
										</small>
									</td>
									<td class="policy-workbench__table-actions">
										<template v-if="row.ruleId">
											<NcActions :force-name="true" :inline="2">
												<NcActionButton @click="state.startEditor({ scope: row.scope, ruleId: row.ruleId })">
													<template #icon>
														<NcIconSvgWrapper :path="mdiPencil" :size="16" />
													</template>
													{{ t('libresign', 'Edit') }}
												</NcActionButton>
												<NcActionButton v-if="row.canRemove" @click="promptRuleRemoval(row.ruleId, row.scope, row.targetLabel)">
													<template #icon>
														<NcIconSvgWrapper :path="mdiDelete" :size="16" />
													</template>
													{{ t('libresign', 'Remove') }}
												</NcActionButton>
											</NcActions>
										</template>
										<span v-else class="policy-workbench__table-note">{{ t('libresign', 'Read only') }}</span>
									</td>
								</tr>
								<tr v-if="pagedCrudRows.length === 0">
									<td colspan="5" class="policy-workbench__table-empty">{{ t('libresign', 'No rules match the current filters.') }}</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div v-if="crudPageCount > 1" class="policy-workbench__pagination">
						<NcButton variant="tertiary" size="small" :disabled="crudPage <= 1" @click="crudPage -= 1">{{ t('libresign', 'Previous') }}</NcButton>
						<span>{{ t('libresign', 'Page {current} of {total}', { current: String(crudPage), total: String(crudPageCount) }) }}</span>
						<NcButton variant="tertiary" size="small" :disabled="crudPage >= crudPageCount" @click="crudPage += 1">{{ t('libresign', 'Next') }}</NcButton>
					</div>
				</div>

				<!-- Editor panel side-by-side (desktop) -->
				<div v-if="state.editorDraft && !shouldUseEditorModal" class="policy-workbench__editor-aside">
					<PolicyRuleEditorPanel
						v-if="state.editorDraft"
						:editor-draft="state.editorDraft"
						:editor-mode="state.editorMode"
						:editor-title="editorTitle"
						:editor-help="editorHelp"
						:active-editor="activeEditor"
						:selected-target-options="selectedTargetOptions"
						:available-targets="state.availableTargets"
						:loading-targets="state.loadingTargets"
						:duplicate-message="state.duplicateMessage"
						:can-save-draft="state.canSaveDraft"
						:save-status="saveStatus"
						@search-targets="state.searchAvailableTargets"
						@update-targets="onTargetChange"
						@update-value="state.updateDraftValue"
						@update-allow-override="state.updateDraftAllowOverride"
						@save="handleSaveDraft()"
						@cancel="requestCancelEditor()" />
					</div>
				</div>

			</NcDialog>

			<NcDialog
				v-if="state.editorDraft && shouldUseEditorModal"
			:name="editorTitle || t('libresign', 'Rule editor')"
			size="full"
			:can-close="true"
			@closing="requestCancelEditor()">
			<div class="policy-workbench__editor-modal-body">
				<PolicyRuleEditorPanel
					v-if="state.editorDraft"
					:editor-draft="state.editorDraft"
					:editor-mode="state.editorMode"
					:editor-title="editorTitle"
					:editor-help="editorHelp"
					:active-editor="activeEditor"
					:selected-target-options="selectedTargetOptions"
					:available-targets="state.availableTargets"
					:loading-targets="state.loadingTargets"
					:duplicate-message="state.duplicateMessage"
					:can-save-draft="state.canSaveDraft"
					:save-status="saveStatus"
					:sticky-actions="true"
					@search-targets="state.searchAvailableTargets"
					@update-targets="onTargetChange"
					@update-value="state.updateDraftValue"
					@update-allow-override="state.updateDraftAllowOverride"
					@save="handleSaveDraft()"
					@cancel="requestCancelEditor()" />
			</div>
		</NcDialog>

		<NcDialog
			v-if="showCreateScopeDialog"
			:name="t('libresign', 'What do you want to create?')"
			size="normal"
			:can-close="true"
			@closing="cancelCreateScopeDialog">
			<div class="policy-workbench__create-scope-dialog">
				<p>{{ t('libresign', 'Choose the level where the new rule should be created.') }}</p>
				<div class="policy-workbench__create-scope-actions">
					<NcButton variant="secondary" :disabled="scopeCreateDisabledReason('system').length > 0" @click="startCreateRuleForScope('system')">
						{{ t('libresign', 'Instance') }}
					</NcButton>
					<NcButton variant="secondary" :disabled="scopeCreateDisabledReason('group').length > 0" @click="startCreateRuleForScope('group')">
						{{ t('libresign', 'Group') }}
					</NcButton>
					<NcButton variant="secondary" :disabled="scopeCreateDisabledReason('user').length > 0" @click="startCreateRuleForScope('user')">
						{{ t('libresign', 'User') }}
					</NcButton>
				</div>
				<ul class="policy-workbench__create-scope-notes">
					<li v-if="scopeCreateDisabledReason('group')">{{ t('libresign', 'Group') }}: {{ scopeCreateDisabledReason('group') }}</li>
					<li v-if="scopeCreateDisabledReason('user')">{{ t('libresign', 'User') }}: {{ scopeCreateDisabledReason('user') }}</li>
				</ul>
			</div>
		</NcDialog>

		<NcDialog
			v-if="pendingDiscardAction"
			:name="t('libresign', 'Discard unsaved changes?')"
			:message="t('libresign', 'You have unsaved changes in this editor. If you continue, your changes will be lost.')"
			:buttons="discardDialogButtons"
			size="normal"
			:can-close="true"
			@closing="cancelDiscardDialog" />

		<NcDialog
			v-if="pendingRemoval"
			:name="t('libresign', 'Confirm rule removal')"
			:message="pendingRemovalMessage"
			:buttons="removalDialogButtons"
			size="normal"
			:can-close="!isRemovingRule"
			@closing="cancelRuleRemoval" />
	</NcSettingsSection>
</template>

<script setup lang="ts">
import {
	mdiDelete,
	mdiFilterVariant,
	mdiFormatListBulletedSquare,
	mdiPencil,
	mdiViewGridOutline,
} from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { usePoliciesStore } from '../../../store/policies'
import { useUserConfigStore } from '../../../store/userconfig.js'
import PolicyRuleEditorPanel from './PolicyRuleEditorPanel.vue'
import { createRealPolicyWorkbenchState } from './useRealPolicyWorkbench'

defineOptions({
	name: 'RealPolicyWorkbench',
})

const policiesStore = usePoliciesStore()
const userConfigStore = useUserConfigStore()
const state = reactive(createRealPolicyWorkbenchState())
const settingsFilter = ref('')
const isSmallViewport = ref(false)
const catalogLayout = ref<'cards' | 'compact'>('cards')
const saveStatus = ref<'idle' | 'saving' | 'saved'>('idle')
const saveFeedbackTimeout = ref<number | null>(null)
const pendingRemoval = ref<{ ruleId: string, scope: 'system' | 'group' | 'user', targetLabel: string, help: string } | null>(null)
const pendingDiscardAction = ref<'cancel-editor' | 'close-setting' | null>(null)
const showCreateScopeDialog = ref(false)
const isRemovingRule = ref(false)
const removalFeedback = ref<string | null>(null)
const removalFeedbackTimeout = ref<number | null>(null)
const lastPress = ref<{ surface: 'cards' | 'list', key: string, x: number, y: number } | null>(null)
const recentSelectionGesture = ref<{ surface: 'cards' | 'list', key: string, at: number } | null>(null)
const crudSearch = ref('')
const crudScopeFilter = ref<'all' | 'system' | 'group' | 'user'>('all')
const crudPage = ref(1)
const popoverBoundary = document.getElementById('app-content-vue') ?? document.body
const CRUD_PAGE_SIZE = 20

const DRAG_OPEN_THRESHOLD_PX = 6
const SELECTION_GUARD_WINDOW_MS = 400
const CATALOG_LAYOUT_CONFIG_KEY = 'policy_workbench_catalog_compact_view'

const filteredSettingSummaries = computed(() => {
	const normalized = settingsFilter.value.trim().toLowerCase()
	if (!normalized) {
		return state.visibleSettingSummaries
	}

	return state.visibleSettingSummaries.filter((summary) => {
		return [summary.title, summary.description, summary.menuHint, summary.defaultSummary]
			.some((value) => value.toLowerCase().includes(normalized))
	})
})

const shouldUseEditorModal = computed(() => isSmallViewport.value)
const activeEditor = computed(() => state.activeDefinition?.editor ?? null)
const effectiveCatalogLayout = computed(() => isSmallViewport.value ? 'cards' : catalogLayout.value)
const hasActiveFilter = computed(() => settingsFilter.value.trim().length > 0)
const catalogViewButtonLabel = computed(() => {
	return effectiveCatalogLayout.value === 'cards'
		? t('libresign', 'Switch to compact view')
		: t('libresign', 'Switch to card view')
})

const selectedTargetOptions = computed(() => {
	if (!state.editorDraft) {
		return []
	}

	return state.availableTargets.filter((option) => state.editorDraft?.targetIds.includes(option.id))
})

type CrudScope = 'system' | 'group' | 'user'
type CrudRow = {
	key: string,
	ruleId: string | null,
	scope: CrudScope,
	targetLabel: string,
	valueLabel: string,
	inheritanceLabel: string,
	canRemove: boolean,
}

const filteredCrudRows = computed<CrudRow[]>(() => {
	const rows: CrudRow[] = []
	const systemRule = state.inheritedSystemRule
	if (systemRule) {
		rows.push({
			key: systemRule.id,
			ruleId: systemRule.id,
			scope: 'system',
			targetLabel: t('libresign', 'Instance default'),
			valueLabel: state.summary?.currentBaseValue ?? t('libresign', 'Not configured'),
			inheritanceLabel: systemRule.allowChildOverride === false ? t('libresign', 'Must inherit') : t('libresign', 'Can override'),
			canRemove: Boolean(systemRule.id && state.hasGlobalDefault),
		})
	}

	for (const rule of state.visibleGroupRules) {
		rows.push({
			key: rule.id,
			ruleId: rule.id,
			scope: 'group',
			targetLabel: state.resolveTargetLabel('group', rule.targetId || ''),
			valueLabel: summarizeRuleValue(rule.value),
			inheritanceLabel: rule.allowChildOverride ? t('libresign', 'Can override') : t('libresign', 'Must inherit'),
			canRemove: true,
		})
	}

	for (const rule of state.visibleUserRules) {
		rows.push({
			key: rule.id,
			ruleId: rule.id,
			scope: 'user',
			targetLabel: state.resolveTargetLabel('user', rule.targetId || ''),
			valueLabel: summarizeRuleValue(rule.value),
			inheritanceLabel: t('libresign', 'Final'),
			canRemove: true,
		})
	}

	const normalized = crudSearch.value.trim().toLowerCase()

	return rows.filter((row) => {
		if (crudScopeFilter.value !== 'all' && row.scope !== crudScopeFilter.value) {
			return false
		}

		if (!normalized) {
			return true
		}

		const scope = crudScopeLabel(row.scope).toLowerCase()
		return [scope, row.targetLabel.toLowerCase(), row.valueLabel.toLowerCase(), row.inheritanceLabel.toLowerCase()]
			.some((value) => value.includes(normalized))
	})
})

const crudPageCount = computed(() => Math.max(1, Math.ceil(filteredCrudRows.value.length / CRUD_PAGE_SIZE)))
const pagedCrudRows = computed(() => {
	if (crudPage.value > crudPageCount.value) {
		crudPage.value = crudPageCount.value
	}

	const start = (crudPage.value - 1) * CRUD_PAGE_SIZE
	return filteredCrudRows.value.slice(start, start + CRUD_PAGE_SIZE)
})

const editorTitle = computed(() => {
	if (!state.editorDraft) {
		return ''
	}

	if (state.editorDraft.scope === 'system') {
		return t('libresign', 'Global default rule')
	}

	return state.draftTargetLabel || t('libresign', 'Select one or more targets')
})

const editorHelp = computed(() => {
	if (!state.editorDraft) {
		return ''
	}

	if (state.editorDraft.scope === 'system') {
		return t('libresign', 'This rule becomes the baseline inherited by groups and users unless another override is set.')
	}

	if (state.editorDraft.scope === 'group') {
		return t('libresign', 'A group override replaces the global default and can still allow lower layers to diverge.')
	}

	return t('libresign', 'A user override is the most specific layer and takes priority over inherited defaults.')
})

function scopeCreateDisabledReason(scope: 'system' | 'group' | 'user') {
	if (scope === 'group') {
		return state.createGroupOverrideDisabledReason || ''
	}

	if (scope === 'user') {
		return state.createUserOverrideDisabledReason || ''
	}

	return ''
}

const hasCreatableScope = computed(() => {
	return ['system', 'group', 'user']
		.some((scope) => scopeCreateDisabledReason(scope as 'system' | 'group' | 'user').length === 0)
})

const createRuleDisabledReason = computed(() => {
	if (!hasCreatableScope.value) {
		return t('libresign', 'A higher-level rule is blocking new exceptions in all scopes.')
	}

	return ''
})

const activeScopeFilterChip = computed(() => {
	if (crudScopeFilter.value === 'all') {
		return ''
	}

	return t('libresign', 'Scope: {scope}', {
		scope: crudScopeLabel(crudScopeFilter.value),
	})
})

const pendingRemovalMessage = computed(() => {
	if (!pendingRemoval.value) {
		return ''
	}

	return t('libresign', 'You are about to remove the rule for {target}. {help}', {
		target: pendingRemoval.value.targetLabel,
		help: pendingRemoval.value.help,
	})
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

function onTargetChange(option: { id: string } | Array<{ id: string }> | null) {
	if (Array.isArray(option)) {
		state.updateDraftTargets(option.map(({ id }) => id))
		return
	}

	state.updateDraftTargets(option?.id ? [option.id] : [])
}

function summarizeRuleValue(value: unknown) {
	if (!state.activeDefinition) {
		return ''
	}

	return state.activeDefinition.summarizeValue(value as never)
}

function crudScopeLabel(scope: CrudScope) {
	if (scope === 'system') {
		return t('libresign', 'Instance')
	}

	if (scope === 'group') {
		return t('libresign', 'Group')
	}

	return t('libresign', 'User')
}

function onCrudSearchChange(value: string | number) {
	crudSearch.value = String(value ?? '')
	crudPage.value = 1
}

function setCrudScopeFilter(value: 'all' | 'system' | 'group' | 'user', selected: boolean) {
	if (!selected) {
		return
	}

	crudScopeFilter.value = value
	crudPage.value = 1
}

function requestCreateRule() {
	if (!hasCreatableScope.value) {
		return
	}

	showCreateScopeDialog.value = true
}

function cancelCreateScopeDialog() {
	showCreateScopeDialog.value = false
}

function startCreateRuleForScope(scope: 'system' | 'group' | 'user') {
	if (scopeCreateDisabledReason(scope).length > 0) {
		return
	}

	showCreateScopeDialog.value = false
	state.startEditor({ scope })
}

function onSettingsFilterChange(value: string | number) {
	settingsFilter.value = String(value ?? '')
}

function hasActiveTextSelection() {
	const selection = window.getSelection()
	return !!selection && selection.type === 'Range' && selection.toString().trim().length > 0
}

function markSelectionGesture(surface: 'cards' | 'list', key: string) {
	if (!hasActiveTextSelection()) {
		return
	}

	recentSelectionGesture.value = {
		surface,
		key,
		at: Date.now(),
	}
}

function shouldIgnoreDueToRecentSelection(surface: 'cards' | 'list', key: string) {
	const gesture = recentSelectionGesture.value
	if (!gesture) {
		return false
	}

	const expired = (Date.now() - gesture.at) > SELECTION_GUARD_WINDOW_MS
	const matchesTarget = gesture.surface === surface && gesture.key === key
	if (expired || !matchesTarget) {
		return false
	}

	recentSelectionGesture.value = null
	return true
}

function isPlainPrimaryClick(event: MouseEvent) {
	const button = typeof event.button === 'number' ? event.button : 0
	const hasModifier = Boolean(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
	return button === 0 && !hasModifier
}

function trackPress(surface: 'cards' | 'list', key: string, event: PointerEvent) {
	if (event.button !== 0) {
		lastPress.value = null
		return
	}

	lastPress.value = {
		surface,
		key,
		x: event.clientX,
		y: event.clientY,
	}
}

function movedBeyondThreshold(event: MouseEvent, press: { x: number, y: number }) {
	const deltaX = Math.abs(event.clientX - press.x)
	const deltaY = Math.abs(event.clientY - press.y)
	return deltaX > DRAG_OPEN_THRESHOLD_PX || deltaY > DRAG_OPEN_THRESHOLD_PX
}

function openSettingFromPointer(surface: 'cards' | 'list', key: string, event: MouseEvent) {
	if (!isPlainPrimaryClick(event)) {
		return
	}

	if (shouldIgnoreDueToRecentSelection(surface, key)) {
		return
	}

	if (hasActiveTextSelection()) {
		return
	}

	const press = lastPress.value
	if (press && press.surface === surface && press.key === key && movedBeyondThreshold(event, press)) {
		return
	}

	state.openSetting(key as never)
}

function clearSettingsFilter() {
	settingsFilter.value = ''
}

function escapeRegExp(value: string) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function escapeHtml(value: string) {
	return value
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#39;')
}

function highlightText(value: string) {
	const query = settingsFilter.value.trim()
	const safeValue = escapeHtml(value)
	if (!query) {
		return safeValue
	}

	const matcher = new RegExp(`(${escapeRegExp(query)})`, 'ig')
	return safeValue.replace(matcher, '<mark>$1</mark>')
}

function resolveSettingOrigin(groupCount: number, userCount: number) {
	if (userCount > 0) {
		return t('libresign', 'User overrides active')
	}

	if (groupCount > 0) {
		return t('libresign', 'Group overrides active')
	}

	return t('libresign', 'Using global default only')
}

function toggleCatalogLayout() {
	if (isSmallViewport.value) {
		catalogLayout.value = 'cards'
		return
	}

	const nextLayout = effectiveCatalogLayout.value === 'cards' ? 'compact' : 'cards'
	catalogLayout.value = nextLayout
	void userConfigStore.update(CATALOG_LAYOUT_CONFIG_KEY, nextLayout === 'compact')
}

function updateViewportMode() {
	isSmallViewport.value = window.innerWidth <= 960
}

async function handleSaveDraft() {
	if (!state.canSaveDraft || saveStatus.value === 'saving') {
		return
	}

	saveStatus.value = 'saving'
	await nextTick()
	await state.saveDraft()
	saveStatus.value = 'saved'

	if (saveFeedbackTimeout.value !== null) {
		window.clearTimeout(saveFeedbackTimeout.value)
	}

	saveFeedbackTimeout.value = window.setTimeout(() => {
		saveStatus.value = 'idle'
		saveFeedbackTimeout.value = null
	}, 1300)
}

function promptRuleRemoval(ruleId: string, scope: 'system' | 'group' | 'user', targetLabel: string) {
	const help = scope === 'system'
		? t('libresign', 'Removing this global default makes the instance use the system default again.')
		: scope === 'group'
			? t('libresign', 'Removing this rule will restore inherited behavior from the global default for this group.')
			: t('libresign', 'Removing this rule will restore inherited behavior for this user.')

	pendingRemoval.value = { ruleId, scope, targetLabel, help }
}

function cancelRuleRemoval() {
	pendingRemoval.value = null
}

function cancelDiscardDialog() {
	pendingDiscardAction.value = null
}

function confirmDiscardDialog() {
	const action = pendingDiscardAction.value
	pendingDiscardAction.value = null

	if (action === 'cancel-editor') {
		state.cancelEditor()
		return
	}

	if (action === 'close-setting') {
		state.closeSetting()
	}
}

async function confirmRuleRemoval() {
	if (!pendingRemoval.value) {
		return
	}

	isRemovingRule.value = true
	try {
		const scope = pendingRemoval.value.scope
		await state.removeRule(pendingRemoval.value.ruleId)
		removalFeedback.value = scope === 'system'
			? t('libresign', 'Global default removed. The instance now uses the system default.')
			: scope === 'group'
				? t('libresign', 'Group exception removed. Inherited behavior from the global default is now active.')
				: t('libresign', 'User exception removed. Inherited behavior is now active.')

		if (removalFeedbackTimeout.value !== null) {
			window.clearTimeout(removalFeedbackTimeout.value)
		}

		removalFeedbackTimeout.value = window.setTimeout(() => {
			removalFeedback.value = null
			removalFeedbackTimeout.value = null
		}, 2200)

		pendingRemoval.value = null
	} finally {
		isRemovingRule.value = false
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

function requestCloseSetting() {
	if (saveStatus.value === 'saving' || isRemovingRule.value) {
		return
	}

	if (state.isDraftDirty) {
		pendingDiscardAction.value = 'close-setting'
		return
	}

	state.closeSetting()
}

onMounted(async () => {
	updateViewportMode()
	catalogLayout.value = userConfigStore.policy_workbench_catalog_compact_view ? 'compact' : 'cards'
	window.addEventListener('resize', updateViewportMode, { passive: true })
	await policiesStore.fetchEffectivePolicies()
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
	showCreateScopeDialog.value = false
})
</script>

<style scoped lang="scss">
.policy-workbench {
	&__catalog-toolbar {
		margin-top: 1.1rem;
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 0.75rem;
		align-items: start;
	}

	&__catalog-search {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__catalog-foot {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.75rem;

		:deep(.button-vue) {
			white-space: nowrap;
		}
	}

	&__clear-filter-button {
		&--hidden {
			visibility: hidden;
			pointer-events: none;
		}
	}

	&__catalog-view-switch {
		display: flex;
		gap: 0.6rem;
		flex-wrap: wrap;
		justify-content: flex-end;

		:deep(.button-vue) {
			min-width: var(--clickable-area-small);
		}
	}

	&__catalog-view-button {
		:deep(.button-vue__text) {
			display: none;
		}
	}

	&__catalog-meta {
		margin: 0;
		font-size: 0.86rem;
		color: var(--color-text-maxcontrast);
	}

	&__crud-filter-popover {
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) / 2);
		padding: calc(var(--default-grid-baseline) / 2);
		min-width: calc(7 * var(--default-clickable-area));
	}

	&__crud-filter-title {
		margin: 0;
		font-size: 0.78rem;
		font-weight: 600;
		letter-spacing: 0.01em;
		text-transform: uppercase;
		color: var(--color-text-maxcontrast);
	}

	&__crud-filter-options {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__crud-filter-chips {
		display: flex;
		align-items: center;
		margin-top: 0.5rem;
	}

	&__settings-grid {
		margin-top: 1rem;
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
		gap: 1rem;
	}

	&__settings-list {
		margin-top: 1rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__settings-row {
		display: grid;
		grid-template-columns: minmax(220px, 1.2fr) minmax(0, 1fr) auto;
		gap: 1rem;
		align-items: center;
		padding: 0.9rem 1rem;
		border-radius: 14px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 12%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-main-background) 94%, white);
		transition: border-color 0.15s ease, box-shadow 0.15s ease;

		&:hover,
		&:focus-within {
			border-color: color-mix(in srgb, var(--color-primary-element) 40%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 12%, transparent);
		}

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__settings-row-main {
		min-width: 0;

		h3,
		p {
			margin: 0;
		}

		h3 {
			overflow-wrap: anywhere;
		}

		p:not(.policy-workbench__origin-badge) {
			margin-top: 0.25rem;
			color: var(--color-text-maxcontrast);
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;
		}
	}

	&__origin-badge {
		margin: 0;
		display: inline-flex;
		align-self: flex-start;
		padding: 0.2rem 0.55rem;
		border-radius: 999px;
		font-size: 0.76rem;
		font-weight: 600;
		color: var(--color-primary-element);
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 28%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-primary-element) 16%, var(--color-main-background));

		&--inline {
			margin-top: 0.5rem;
		}
	}

	&__settings-row-stats {
		display: flex;
		flex-wrap: wrap;
		gap: 0.55rem 0.8rem;
		align-items: baseline;
		font-size: 0.9rem;
		color: var(--color-text-maxcontrast);
		min-width: 0;
	}

	&__settings-row-stat {
		min-width: 0;
		white-space: normal;
		overflow-wrap: anywhere;

		&--default {
			display: flex;
			align-items: baseline;
			gap: 0.25rem;
			flex: 1 1 260px;
			min-width: 0;

			strong {
				white-space: nowrap;
				flex-shrink: 0;
			}

			span {
				display: block;
				min-width: 0;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&--count {
			white-space: nowrap;
			flex: 0 0 auto;
		}
	}

	&__setting-tile {
		text-align: left;
		padding: 1.25rem;
		border-radius: 20px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 14%, var(--color-border-maxcontrast));
		background:
			radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary-element) 14%, transparent), transparent 45%),
			linear-gradient(180deg, color-mix(in srgb, var(--color-main-background) 92%, white), var(--color-main-background));
		display: flex;
		flex-direction: column;
		gap: 1rem;
		cursor: pointer;
		transition: border-color 0.15s ease, box-shadow 0.15s ease;

		&:hover,
		&:focus-visible,
		&:focus-within {
			border-color: color-mix(in srgb, var(--color-primary-element) 46%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 14%, transparent);
		}

		h3,
		p {
			margin: 0;
		}

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__setting-header {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 1rem;
		align-items: flex-start;

		> div {
			min-width: 0;
		}

		h3 {
			overflow-wrap: anywhere;
			word-break: break-word;
		}
	}

	&__setting-hint {
		margin: 0;
		font-size: 0.86rem;
		color: var(--color-text-maxcontrast);
		word-break: break-word;
	}

	&__setting-stats {
		margin: 0;
		padding: 0;
		list-style: none;
		display: flex;
		flex-direction: column;
		gap: 0.35rem;

		li {
			display: flex;
			gap: 0.3rem;
			align-items: baseline;

			strong {
				white-space: nowrap;
				flex-shrink: 0;
			}

			span {
				min-width: 0;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}

	&__manage-button {
		:deep(.button-vue) {
			transition: transform 0.12s ease;
		}
	}

	&__setting-tile:hover &__manage-button,
	&__setting-tile:focus-within &__manage-button,
	&__settings-row:hover &__manage-button,
	&__settings-row:focus-within &__manage-button {
		:deep(.button-vue) {
			transform: translateY(-1px);
		}
	}

	&__empty-state {
		display: flex;
		flex-direction: column;
		gap: 0.65rem;

		p {
			margin: 0;
		}

		&--compact {
			gap: 0.5rem;
		}
	}

	&__empty-state-actions {
		display: flex;
		gap: 0.6rem;
		flex-wrap: wrap;
	}

	&__dialog {
		width: min(1480px, 100%);
		min-height: min(820px, calc(100vh - 7rem));
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		gap: 0;
	}

	&__dialog-header {
		display: flex;
		justify-content: flex-start;
		flex-direction: column;
		gap: 1rem;
		align-items: flex-start;

		h2,
		p {
			margin: 0;
		}

		h2 {
			word-break: break-word;
		}
	}

	&__dialog-description {
		max-width: 72ch;
		color: var(--color-text-maxcontrast);
	}

	&__dialog-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;

		:deep(.button-vue) {
			max-width: 100%;
		}
	}

	&__main {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	&__tier {
		padding: 1.25rem 0;
		border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);

		&:last-child {
			border-bottom: none;
		}
	}

	&__tier-head {
		display: flex;
		align-items: flex-start;
		gap: 1rem;
		margin-bottom: 0.75rem;

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__tier-info {
		flex: 1;
		min-width: 0;
	}

	&__tier-title {
		margin: 0;
		font-size: 0.95rem;
		font-weight: 600;
	}

	&__tier-desc {
		margin: 0.25rem 0 0;
		font-size: 0.85rem;
		color: var(--color-text-maxcontrast);
		max-width: 72ch;
	}

	&__tier-actions {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-shrink: 0;
	}

	&__tier-value {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		margin-bottom: 0.5rem;
	}

	&__current-value {
		font-size: 1.1rem;
		font-weight: 700;
		color: var(--color-main-text);
	}

	&__value-source {
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
		padding: 0.1rem 0.45rem;
		border-radius: 9px;
		background: color-mix(in srgb, var(--color-background-dark) 60%, transparent);
	}

	&__tier-empty {
		margin: 0.5rem 0 0;
		font-size: 0.88rem;
		color: var(--color-text-maxcontrast);
		font-style: italic;
	}

	&__table-toolbar-row {
		display: flex;
		align-items: center;
		gap: 0.65rem;
		flex-wrap: wrap;
		margin-bottom: 0.75rem;

		&--crud {
			align-items: flex-end;
			justify-content: space-between;

			:deep(.text-field) {
				flex: 1 1 280px;
			}
		}
	}

	&__crud-create {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 0.45rem;
	}


	&__workspace {
		display: grid;
		grid-template-columns: minmax(300px, 0.82fr) minmax(560px, 1.18fr);
		gap: 1.25rem;
		align-items: start;
		flex: 1;
	}

	&__rules-column,
	&__editor-column {
		display: flex;
		flex-direction: column;
		gap: 1rem;
		min-width: 0;
	}

	&__group,
	&__editor-panel,
	&__editor-mobile-hint,
	&__editor-empty {
		padding: 1.25rem;
		border-radius: 18px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 8%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-background-dark) 8%, var(--color-main-background));
		display: flex;
		flex-direction: column;
		gap: 0.85rem;
		overflow: hidden;
	}

	&__editor-panel-content {
		display: flex;
		flex-direction: column;
		gap: 0.85rem;

		&--saving {
			opacity: 0.5;
			pointer-events: none;
			user-select: none;
		}
	}

	&__editor-panel,
	&__editor-mobile-hint,
	&__editor-empty {
		position: sticky;
		top: 0;
	}

	&__editor-mobile-hint {
		p,
		h3 {
			margin: 0;
		}

		p:last-of-type {
			margin-top: 0.25rem;
			color: var(--color-text-maxcontrast);
		}

		:deep(.button-vue) {
			margin-top: 0.65rem;
			width: 100%;
			justify-content: center;
		}
	}

	// Editor aside panel (desktop)
	&__editor-aside {
		display: none;
	}

	@media (min-width: 961px) {
		&__dialog {
			flex-direction: row;
			align-items: flex-start;
		}

		&__main {
			flex: 1;
			min-width: 0;
			overflow-y: auto;
			max-height: calc(min(820px, 100vh - 7rem) - 2rem);
		}

		&__editor-aside {
			display: flex;
			flex-direction: column;
			gap: 0.75rem;
			position: sticky;
			top: 0;
			width: 380px;
			flex-shrink: 0;
			max-height: 90vh;
			overflow-y: auto;
			padding-left: 1.5rem;
			border-left: 1px solid color-mix(in srgb, var(--color-border-maxcontrast) 50%, transparent);
		}

		&__editor-mobile-hint {
			display: none;
		}
	}

	&__editor-panel {
		position: relative;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__editor-panel-content {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
		position: relative;

		&--saving {
			opacity: 0.6;
			pointer-events: none;
		}
	}

	&__editor-header {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;

		h3,
		p {
			margin: 0;
		}

		p:last-child {
			margin-top: 0.35rem;
			color: var(--color-text-maxcontrast);
		}
	}

	&__eyebrow {
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--color-text-maxcontrast);
	}

	&__label {
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--color-text-maxcontrast);
	}

	// New compact summary line
	&__summary-line {
		display: flex;
		align-items: flex-start;
		flex-direction: column;
		gap: 0.35rem;
		padding: 0.25rem 0;
		color: var(--color-main-text);

		&--crud {
			margin: 0.5rem 0 0.75rem;
		}
	}

	&__summary-caption {
		font-size: 0.82rem;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		color: var(--color-text-maxcontrast);
	}

	&__summary-wrap {
		:deep(.notecard) {
			margin: 0;
		}
	}

	&__summary-header {
		margin-bottom: 1.5rem;
	}

	&__summary-top {
		display: flex;
		align-items: baseline;
		gap: 0.8rem;
		margin-bottom: 0.4rem;
	}

	&__summary-value {
		margin: 0;
		font-size: 1.8rem;
		font-weight: 700;
	}

	&__summary-meta {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		font-size: 0.88rem;
		color: var(--color-text-maxcontrast);
	}

	&__summary-source {
		margin: 0;
	}

	&__summary-counts {
		margin: 0;
	}

	&__summary-divider {
		opacity: 0.5;
	}

	&__learn-link {
		background: none;
		border: none;
		color: var(--color-text-maxcontrast);
		cursor: pointer;
		font-size: 0.8rem;
		font-weight: 500;
		padding: 0;
		text-decoration: underline;

		&:hover {
			opacity: 0.8;
		}

		&:focus {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}
	}

	&__summary-learn-link {
		margin-left: auto;
		white-space: nowrap;
		:deep(.button-vue__text) {
			font-size: 0.88rem;
		}
	}

	// Collapsible inheritance help section
	&__inheritance-help {
		padding: 0.9rem;
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 18%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-primary-element) 8%, var(--color-main-background));
		margin-bottom: 0.5rem;

		ul {
			margin: 0;
			padding-left: 1.2rem;
			display: flex;
			flex-direction: column;
			gap: 0.3rem;
			font-size: 0.86rem;
			color: var(--color-text-maxcontrast);

			li {
				line-height: 1.4;
			}
		}
	}

	&__help-toggle {
		background: none;
		border: none;
		color: var(--color-text-maxcontrast);
		cursor: pointer;
		font-size: 0.85rem;
		font-weight: 400;
		padding: 0;
		text-decoration: underline;
		transition: color 0.15s ease;

		&:hover {
			color: var(--color-main-text);
		}

		&:focus {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}
	}

	&__inheritance-note {
		margin-top: 0.6rem;
		padding: 0.6rem 0;
		font-size: 0.85rem;
		color: var(--color-text-maxcontrast);

		ul {
			margin: 0;
			padding-left: 1.2rem;
			display: flex;
			flex-direction: column;
			gap: 0.25rem;
		}

		li {
			line-height: 1.4;
		}
	}

	// Single-column content wrapper
	&__content {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}

	// Main sections (Default for this instance, Exceptions)
	&__section {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__section-title {
		margin: 0;
		font-size: 0.92rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	// Nested subsections (Groups, Users under Exceptions)
	&__subsection {
		display: flex;
		flex-direction: column;
		gap: 0.6rem;
	}

	&__subsection-title {
		margin: 0;
		margin-top: 0.5rem;
		font-size: 0.84rem;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.02em;
	}

	// Default for instance section styling
	&__default-value {
		margin: 0;
		font-size: 1.2rem;
		font-weight: 700;
		color: var(--color-main-text);
		line-height: 1.3;
	}

	&__default-reason {
		margin: 0;
		font-size: 0.88rem;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	&__default-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.6rem;

		:deep(.button-vue) {
			max-width: 100%;
		}
	}

	// Exception list styling
	&__exception-list {
		display: flex;
		flex-direction: column;
		gap: 0.65rem;
	}

	&__tabs {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}

	&__tab-link {
		background: none;
		border: none;
		color: var(--color-text-maxcontrast);
		cursor: pointer;
		font-size: 0.9rem;
		font-weight: 400;
		padding: 0.4rem 0;
		text-decoration: none;
		border-bottom: 2px solid transparent;
		transition: all 0.15s ease;

		&:hover {
			color: var(--color-main-text);
			border-bottom-color: var(--color-border);
		}

		&--active {
			color: var(--color-main-text);
			font-weight: 600;
			border-bottom-color: var(--color-primary-element);
		}
	}

	&__table-section {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__table-heading {
		margin: 0;
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__table-toolbar {
		display: grid;
		grid-template-columns: minmax(220px, 1fr) auto auto;
		gap: 0.65rem;
		align-items: center;
	}

	&__table-toolbar-action {
		display: flex;
		justify-content: flex-end;
		align-items: center;
	}

	&__filter-inline {
		display: flex;
		align-items: center;
		gap: 0.6rem;
		font-size: 0.85rem;
	}

	&__filter-option {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		cursor: pointer;
		white-space: nowrap;

		input[type='radio'] {
			cursor: pointer;
		}

		span {
			color: var(--color-main-text);
		}
	}

	&__table-scroll {
		overflow-x: auto;
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
			padding: 0.6rem 0.8rem;
			vertical-align: middle;
		}

		th {
			font-size: 0.78rem;
			font-weight: 600;
			color: var(--color-text-maxcontrast);
			border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);
			background: transparent;
		}

		tbody td {
			border-bottom: 1px solid color-mix(in srgb, var(--color-border) 40%, transparent);
		}

		tr:last-child td {
			border-bottom: none;
		}

		tbody tr:hover td {
			background: color-mix(in srgb, var(--color-main-text) 2%, transparent);
		}
	}

	&__table-actions {
		white-space: nowrap;

		:deep(.actions) {
			justify-content: flex-start;
		}

		:deep(.action-item) {
			font-size: 0.84rem;
		}
	}

	&__table-empty {
		text-align: center;
		color: var(--color-text-maxcontrast);
		font-style: italic;
	}

	&__table-note {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);

		&--compact {
			max-width: 26ch;
		}

		&--align-right {
			text-align: right;
		}
	}

	&__create-scope-dialog {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;

		p {
			margin: 0;
		}
	}

	&__create-scope-actions {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	&__create-scope-notes {
		margin: 0;
		padding-inline-start: 1.1rem;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);

		li {
			margin: 0;
		}
	}

	&__table-empty-state {
		display: flex;
		flex-direction: column;
		gap: 0.2rem;

		p {
			margin: 0;
			font-size: 0.86rem;
			color: var(--color-main-text);
		}

		p:last-child {
			color: var(--color-text-maxcontrast);
		}
	}

	&__row-status {
		font-size: 0.86rem;
		color: var(--color-text-maxcontrast);

		&--inherit {
			color: var(--color-main-text);
		}
	}

	&__pagination {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 0.65rem;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}

	// Empty state for subsections
	&__empty-subsection {
		margin: 0;
		font-size: 0.88rem;
		color: var(--color-text-maxcontrast);
		font-style: italic;
	}

	// Inline blocker reason message
	&__blocker-reason {
		margin: 0.5rem 0 0;
		padding: 0.6rem 0.75rem;
		border-radius: 9px;
		border: 1px solid color-mix(in srgb, var(--color-warning) 36%, transparent);
		background: color-mix(in srgb, var(--color-warning) 14%, var(--color-main-background));
		font-size: 0.85rem;
		line-height: 1.4;
		color: var(--color-text-maxcontrast);
	}

	// Collapsible precedence explanation
	&__precedence-explanation {
		margin-top: 0.65rem;
		padding: 0.9rem;
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 22%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-main-background) 72%, white);
	}

	&__precedence-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 0.6rem;

		h3 {
			margin: 0;
			font-size: 0.92rem;
		}

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__precedence-list {
		margin: 0;
		padding-left: 1.2rem;
		display: flex;
		flex-direction: column;
		gap: 0.3rem;
		font-size: 0.86rem;
		color: var(--color-text-maxcontrast);

		li {
			line-height: 1.4;
		}
	}

	// Base rule section
	&__base-rule-info {
		padding: 0.8rem;
		border-radius: 10px;
		background: color-mix(in srgb, var(--color-background-dark) 8%, var(--color-main-background));
		margin-bottom: 0.6rem;
	}

	&__base-rule-current {
		p {
			margin: 0;
		}

		p:not(:last-child) {
			margin-bottom: 0.2rem;
		}
	}

	&__base-rule-label {
		font-size: 0.78rem;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.02em;
	}

	&__base-rule-value {
		font-size: 0.95rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__base-rule-source {
		font-size: 0.8rem;
		color: var(--color-text-maxcontrast);
	}

	&__base-rule-no-global {
		display: flex;
		flex-direction: column;
		gap: 0.6rem;

		p {
			margin: 0;
			font-size: 0.88rem;
		}

		:deep(.button-vue) {
			max-width: 100%;
		}
	}

	&__base-rule-fallback {
		padding: 0.55rem;
		border-radius: 8px;
		background: color-mix(in srgb, var(--color-primary-element) 6%, var(--color-main-background));
		border-left: 2px solid color-mix(in srgb, var(--color-primary-element) 24%, transparent);
	}

	&__base-rule-label--small {
		font-size: 0.76rem;
		color: var(--color-text-maxcontrast);
		margin: 0;
		text-transform: uppercase;
		letter-spacing: 0.02em;
	}

	&__base-rule-value--small {
		font-size: 0.88rem;
		font-weight: 600;
		color: var(--color-main-text);
		margin: 0.15rem 0 0;
	}

	&__table-like {
		display: flex;
		flex-direction: column;
		gap: 0.65rem;
	}

	&__section-blocker {
		padding: 0.6rem 0.75rem;
		border-radius: 9px;
		border: 1px solid color-mix(in srgb, var(--color-warning) 36%, transparent);
		background: color-mix(in srgb, var(--color-warning) 14%, var(--color-main-background));
		font-size: 0.85rem;
		line-height: 1.4;

		p {
			margin: 0;
		}
	}

	&__stack {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 0.45rem;
	}

	&__inline-note-actions {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		justify-content: flex-start;
		gap: 0.75rem;

		p {
			margin: 0;
		}

		:deep(.button-vue) {
			max-width: 100%;
		}
	}

	&__setting-surface {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
		padding: 0.8rem;
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 45%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-primary-element) 5%, var(--color-main-background));
		box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary-element) 8%, transparent);
	}

	&__editor-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;

		:deep(.button-vue) {
			max-width: 100%;
		}
	}

	&__editor-actions--sticky-mobile {
		position: sticky;
		bottom: 0;
		padding-top: 0.55rem;
	}

	&__save-feedback {
		margin: 0;
		font-size: 0.84rem;
		font-weight: 600;
		color: color-mix(in srgb, var(--color-primary-element-text) 78%, var(--color-success));
	}

	&__removal-feedback {
		margin: 0;
		padding: 0.65rem 0.8rem;
		border: 1px solid color-mix(in srgb, var(--color-success) 36%, transparent);
		border-radius: 10px;
		background: color-mix(in srgb, var(--color-success) 12%, var(--color-main-background));
		color: var(--color-main-text);
		font-size: 0.88rem;
	}

	&__saving-overlay {
		position: absolute;
		inset: 0;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 0.55rem;
		padding: 1rem;
		background: color-mix(in srgb, var(--color-main-background) 78%, transparent);
		backdrop-filter: blur(1px);
		z-index: 2;

		p {
			margin: 0;
			font-weight: 600;
			color: var(--color-text-maxcontrast);
		}
	}

	&__saving-spinner {
		width: 1.4rem;
		height: 1.4rem;
		border-radius: 999px;
		border: 2px solid color-mix(in srgb, var(--color-primary-element) 22%, var(--color-border-maxcontrast));
		border-top-color: var(--color-primary-element);
		animation: policy-workbench-spin 0.8s linear infinite;
	}

	:deep(mark) {
		background: color-mix(in srgb, var(--color-warning) 35%, transparent);
		color: inherit;
		padding: 0 0.1rem;
		border-radius: 3px;
	}
}

@keyframes policy-workbench-spin {
	to {
		transform: rotate(360deg);
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__catalog-toolbar {
			display: flex;
			flex-direction: column;
			align-items: stretch;
		}

		&__catalog-view-switch {
			justify-content: flex-start;

			:deep(.button-vue) {
				justify-content: center;
			}
		}

		&__catalog-meta {
			margin-top: -0.15rem;
		}

		&__catalog-foot {
			flex-direction: column;
			align-items: flex-start;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__empty-state-actions {
			width: 100%;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__workspace,
		&__dialog-header,
		&__setting-header {
			display: flex;
			flex-direction: column;
		}

		&__summary-line {
			gap: 0.25rem;
		}

		&__table-toolbar {
			grid-template-columns: 1fr;
		}

		&__table-filter {
			:deep(.button-vue) {
				min-width: 0;
				width: 100%;
			}
		}

		&__pagination {
			justify-content: flex-start;
		}

		&__summary-learn-link {
			margin-left: 0;
			margin-top: 0.35rem;
		}

		&__precedence-explanation {
			margin-top: 0.6rem;
		}

		&__removal-feedback {
			margin: 0 0 0.2rem;
		}

		&__settings-row {
			grid-template-columns: 1fr;
			align-items: stretch;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__dialog {
			width: 100%;
			min-height: auto;
			gap: 0;
		}

		&__tier {
			padding: 1rem 0;
		}

		&__tier-head {
			flex-direction: column;
			align-items: stretch;
			gap: 0.65rem;
		}

		&__table-toolbar-row {
			flex-direction: column;
			align-items: stretch;
		}

		&__crud-create {
			align-items: stretch;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__editor-panel,
		&__editor-mobile-hint {
			position: static;
		}

		&__dialog-actions,
		&__editor-actions {
			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__inline-note-actions {
			align-items: stretch;
			justify-content: flex-start;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__setting-header {
			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__group,
		&__editor-panel,
		&__editor-mobile-hint,
		&__setting-tile {
			padding: 1rem;
		}

		&__base-rule-info {
			margin-bottom: 0.5rem;
		}
	}
}

@media (min-width: 961px) and (max-width: 1280px) {
	.policy-workbench {
		&__workspace {
			grid-template-columns: minmax(280px, 0.9fr) minmax(460px, 1.1fr);
		}
	}
}

@media (max-width: 640px) {
	.policy-workbench {
		&__settings-grid {
			gap: 0.75rem;
		}

		&__dialog {
			gap: 0.6rem;
		}

		&__setting-stats {
			gap: 0.5rem;

			li {
				word-break: break-word;
			}
		}

		&__workspace,
		&__rules-column,
		&__editor-column,
		&__stack {
			gap: 0.75rem;
		}

		&__dialog-header,
		&__group-header,
		&__editor-header {
			h2,
			h3,
			p {
				word-break: break-word;
			}
		}

		&__settings-row-stats {
			grid-template-columns: 1fr;
		}

		&__summary-line {
			font-size: 0.82rem;
			gap: 0.3rem;
		}

		&__precedence-list {
			font-size: 0.8rem;
		}
	}
}
</style>
