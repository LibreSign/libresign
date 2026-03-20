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
					<span class="policy-workbench__settings-row-stat"><strong>{{ t('libresign', 'Group overrides') }}:</strong> {{ summary.groupCount }}</span>
					<span class="policy-workbench__settings-row-stat"><strong>{{ t('libresign', 'User overrides') }}:</strong> {{ summary.userCount }}</span>
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
			@closing="state.closeSetting()">
			<div class="policy-workbench__dialog">
				<header class="policy-workbench__dialog-header">
					<div>
						<p class="policy-workbench__eyebrow">
							{{ state.viewMode === 'system-admin' ? t('libresign', 'System admin workspace') : t('libresign', 'Group admin workspace') }}
						</p>
						<h2>{{ state.activeDefinition.title }}</h2>
						<p>{{ state.activeDefinition.description }}</p>
					</div>
					<div class="policy-workbench__dialog-actions">
						<NcButton
							v-if="state.viewMode === 'system-admin'"
							variant="secondary"
							:aria-label="t('libresign', 'New group override')"
							@click="state.startEditor({ scope: 'group' })">
							{{ t('libresign', 'New group override') }}
						</NcButton>
						<NcButton variant="primary" :aria-label="t('libresign', 'New user override')" @click="state.startEditor({ scope: 'user' })">
							{{ t('libresign', 'New user override') }}
						</NcButton>
					</div>
				</header>

				<p
					v-if="removalFeedback"
					class="policy-workbench__removal-feedback"
					aria-live="polite">
					{{ removalFeedback }}
				</p>

				<div class="policy-workbench__workspace">
					<div class="policy-workbench__rules-column">
						<section class="policy-workbench__group" role="region" :aria-label="state.viewMode === 'system-admin' ? t('libresign', 'Global default rules') : t('libresign', 'Inherited global default')">
							<div class="policy-workbench__group-header">
								<h3>{{ state.viewMode === 'system-admin' ? t('libresign', 'Global default') : t('libresign', 'Inherited global default') }}</h3>
								<p>
									{{ state.viewMode === 'system-admin'
										? t('libresign', 'This rule is the baseline for the whole instance.')
										: t('libresign', 'Group admins can see the inherited default but do not edit it from this view.') }}
								</p>
							</div>

							<PolicyRuleCard
								v-if="state.inheritedSystemRule"
								:eyebrow="t('libresign', 'System')"
								:title="t('libresign', 'Global default rule')"
								:summary="summarizeRuleValue(state.inheritedSystemRule.value)"
								:description="t('libresign', 'Applied when no group or user override matches.')"
								:badges="systemRuleBadges"
								:highlighted="state.highlightedRuleId === state.inheritedSystemRule.id"
								:show-edit-action="true"
								:show-remove-action="state.viewMode === 'system-admin'"
								:edit-label="t('libresign', 'Edit global default')"
								:remove-label="t('libresign', 'Reset global default')"
								@edit="state.startEditor({ scope: 'system', ruleId: state.inheritedSystemRule.id })"
								@remove="promptRuleRemoval(state.inheritedSystemRule.id, 'system', t('libresign', 'Global default rule'))" />

							<NcNoteCard v-else type="info">
								<div class="policy-workbench__inline-note-actions">
									<p>{{ t('libresign', 'No global default rule is set for this setting.') }}</p>
									<NcButton
										v-if="state.viewMode === 'system-admin'"
										variant="secondary"
										:aria-label="t('libresign', 'Create global default rule')"
										@click="state.startEditor({ scope: 'system' })">
										{{ t('libresign', 'Create global default rule') }}
									</NcButton>
								</div>
							</NcNoteCard>
						</section>

						<section class="policy-workbench__group" role="region" :aria-label="t('libresign', 'Group overrides')">
							<div class="policy-workbench__group-header">
								<h3>{{ t('libresign', 'Group overrides') }}</h3>
								<p>
									{{ t('libresign', 'These overrides replace the global default for selected groups.') }}
								</p>
							</div>

							<div v-if="state.visibleGroupRules.length > 0" class="policy-workbench__stack">
								<PolicyRuleCard
									v-for="rule in state.visibleGroupRules"
									:key="rule.id"
									:eyebrow="t('libresign', 'Group')"
									:title="state.resolveTargetLabel('group', rule.targetId || '')"
									:summary="summarizeRuleValue(rule.value)"
									:description="t('libresign', 'Replaces inherited behavior for this group.')"
									:badges="groupRuleBadges(rule.allowChildOverride)"
									:highlighted="state.highlightedRuleId === rule.id"
									:edit-label="t('libresign', 'Edit group override')"
									:remove-label="t('libresign', 'Delete group override')"
									@edit="state.startEditor({ scope: 'group', ruleId: rule.id })"
									@remove="promptRuleRemoval(rule.id, 'group', state.resolveTargetLabel('group', rule.targetId || ''))" />
							</div>

							<NcNoteCard v-else type="info">
								<div class="policy-workbench__inline-note-actions">
									<p>{{ t('libresign', 'No group overrides are set for this setting.') }}</p>
									<NcButton
										v-if="state.viewMode === 'system-admin'"
										variant="secondary"
										:aria-label="t('libresign', 'New group override')"
										@click="state.startEditor({ scope: 'group' })">
										{{ t('libresign', 'New group override') }}
									</NcButton>
								</div>
							</NcNoteCard>
						</section>

						<section class="policy-workbench__group" role="region" :aria-label="t('libresign', 'User overrides')">
							<div class="policy-workbench__group-header">
								<h3>{{ t('libresign', 'User overrides') }}</h3>
								<p>
									{{ t('libresign', 'Use these only when one signer needs behavior different from inherited defaults.') }}
								</p>
							</div>

							<div v-if="state.visibleUserRules.length > 0" class="policy-workbench__stack">
								<PolicyRuleCard
									v-for="rule in state.visibleUserRules"
									:key="rule.id"
									:eyebrow="t('libresign', 'User')"
									:title="state.resolveTargetLabel('user', rule.targetId || '')"
									:summary="summarizeRuleValue(rule.value)"
									:description="t('libresign', 'Applies only to this signer.')"
									:badges="[t('libresign', 'Final override')]"
									:highlighted="state.highlightedRuleId === rule.id"
									:edit-label="t('libresign', 'Edit user override')"
									:remove-label="t('libresign', 'Delete user override')"
									@edit="state.startEditor({ scope: 'user', ruleId: rule.id })"
									@remove="promptRuleRemoval(rule.id, 'user', state.resolveTargetLabel('user', rule.targetId || ''))" />
							</div>

							<NcNoteCard v-else type="info">
								<div class="policy-workbench__inline-note-actions">
									<p>{{ t('libresign', 'No user overrides are set for this setting.') }}</p>
									<NcButton
										variant="secondary"
										:aria-label="t('libresign', 'New user override')"
										@click="state.startEditor({ scope: 'user' })">
										{{ t('libresign', 'New user override') }}
									</NcButton>
								</div>
							</NcNoteCard>
						</section>
					</div>

					<div class="policy-workbench__editor-column">
						<section v-if="state.editorDraft && !shouldUseEditorModal" class="policy-workbench__editor-panel">
							<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
								<div class="policy-workbench__editor-header">
									<p class="policy-workbench__eyebrow">
										{{ state.editorMode === 'edit' ? t('libresign', 'Edit rule') : t('libresign', 'Create rule') }}
									</p>
									<h3>{{ editorTitle }}</h3>
									<p>{{ editorHelp }}</p>
								</div>

								<div v-if="state.editorDraft.scope !== 'system'" class="policy-workbench__field">
									<label class="policy-workbench__label">
										{{ state.editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users') }}
									</label>
									<NcSelectUsers
										:model-value="selectedTargetOptions"
										:options="state.availableTargets"
										:aria-label="state.editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users')"
										:placeholder="state.editorDraft.scope === 'group' ? t('libresign', 'Search groups') : t('libresign', 'Search users')"
										:loading="state.loadingTargets"
										:multiple="true"
										:keep-open="true"
										:disabled="saveStatus === 'saving'"
										@search="state.searchAvailableTargets"
										@update:modelValue="onTargetChange" />
								</div>

								<div v-if="activeEditor" class="policy-workbench__setting-surface">
									<component
										:is="activeEditor"
										:model-value="state.editorDraft.value"
										@update:modelValue="state.updateDraftValue" />
								</div>

								<NcCheckboxRadioSwitch
									v-if="state.editorDraft.scope !== 'user'"
									type="switch"
									:model-value="state.editorDraft.allowChildOverride"
									:disabled="saveStatus === 'saving'"
									@update:modelValue="state.updateDraftAllowOverride">
									<span>{{ t('libresign', 'Allow lower layers to override this rule') }}</span>
								</NcCheckboxRadioSwitch>

								<NcNoteCard v-if="state.duplicateMessage" type="error">
									{{ state.duplicateMessage }}
								</NcNoteCard>

								<div class="policy-workbench__editor-actions">
									<NcButton variant="primary" :aria-label="state.editorMode === 'edit' ? t('libresign', 'Save policy rule changes') : t('libresign', 'Create policy rule')" :disabled="!state.canSaveDraft || saveStatus === 'saving'" @click="handleSaveDraft()">
										{{ state.editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule') }}
									</NcButton>
									<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="state.cancelEditor()">
										{{ t('libresign', 'Cancel') }}
									</NcButton>
								</div>
								<p v-if="saveStatus !== 'idle'" class="policy-workbench__save-feedback" aria-live="polite">
									{{ saveStatus === 'saving' ? t('libresign', 'Saving...') : t('libresign', 'Changes saved') }}
								</p>
							</div>

							<div v-if="saveStatus === 'saving'" class="policy-workbench__saving-overlay" aria-live="polite" aria-busy="true">
								<div class="policy-workbench__saving-spinner" aria-hidden="true"></div>
								<p>{{ t('libresign', 'Saving...') }}</p>
							</div>
						</section>

						<section v-else-if="state.editorDraft && shouldUseEditorModal" class="policy-workbench__editor-mobile-hint">
							<p class="policy-workbench__eyebrow">{{ t('libresign', 'Editing surface') }}</p>
							<h3>{{ t('libresign', 'Editor opened in full-screen modal') }}</h3>
							<p>
								{{ t('libresign', 'On smaller screens, editing opens in a focused full-screen modal to keep forms readable.') }}
							</p>
							<NcButton
								variant="secondary"
								:aria-label="t('libresign', 'Cancel editing')"
								@click="state.cancelEditor()">
								{{ t('libresign', 'Cancel') }}
							</NcButton>
						</section>

						<section v-else class="policy-workbench__editor-empty">
							<p class="policy-workbench__eyebrow">{{ t('libresign', 'Editing surface') }}</p>
							<h3>{{ t('libresign', 'Choose an action to start editing') }}</h3>
							<p>
								{{ t('libresign', 'Click a card above or use the buttons to create or edit policy rules.') }}
							</p>
						</section>
					</div>
				</div>
			</div>
		</NcDialog>

		<NcDialog
			v-if="state.editorDraft && shouldUseEditorModal"
			:name="editorTitle || t('libresign', 'Rule editor')"
			size="full"
			:can-close="true"
			@closing="state.cancelEditor()">
			<div class="policy-workbench__editor-modal-body">
				<section class="policy-workbench__editor-panel">
					<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
						<div class="policy-workbench__editor-header">
							<p class="policy-workbench__eyebrow">
								{{ state.editorMode === 'edit' ? t('libresign', 'Edit rule') : t('libresign', 'Create rule') }}
							</p>
							<h3>{{ editorTitle }}</h3>
							<p>{{ editorHelp }}</p>
						</div>

						<div v-if="state.editorDraft.scope !== 'system'" class="policy-workbench__field">
							<label class="policy-workbench__label">
								{{ state.editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users') }}
							</label>
							<NcSelectUsers
								:model-value="selectedTargetOptions"
								:options="state.availableTargets"
								:aria-label="state.editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users')"
								:placeholder="state.editorDraft.scope === 'group' ? t('libresign', 'Search groups') : t('libresign', 'Search users')"
								:loading="state.loadingTargets"
								:multiple="true"
								:keep-open="true"
								:disabled="saveStatus === 'saving'"
								@search="state.searchAvailableTargets"
								@update:modelValue="onTargetChange" />
						</div>

						<div v-if="activeEditor" class="policy-workbench__setting-surface">
							<component
								:is="activeEditor"
								:model-value="state.editorDraft.value"
								@update:modelValue="state.updateDraftValue" />
						</div>

						<NcCheckboxRadioSwitch
							v-if="state.editorDraft.scope !== 'user'"
							type="switch"
							:model-value="state.editorDraft.allowChildOverride"
							:disabled="saveStatus === 'saving'"
							@update:modelValue="state.updateDraftAllowOverride">
							<span>{{ t('libresign', 'Allow lower layers to override this rule') }}</span>
						</NcCheckboxRadioSwitch>

						<NcNoteCard v-if="state.duplicateMessage" type="error">
							{{ state.duplicateMessage }}
						</NcNoteCard>

						<div class="policy-workbench__editor-actions policy-workbench__editor-actions--sticky-mobile">
							<NcButton variant="primary" :aria-label="state.editorMode === 'edit' ? t('libresign', 'Save policy rule changes') : t('libresign', 'Create policy rule')" :disabled="!state.canSaveDraft || saveStatus === 'saving'" @click="handleSaveDraft()">
								{{ state.editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule') }}
							</NcButton>
							<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="state.cancelEditor()">
								{{ t('libresign', 'Cancel') }}
							</NcButton>
						</div>
						<p v-if="saveStatus !== 'idle'" class="policy-workbench__save-feedback" aria-live="polite">
							{{ saveStatus === 'saving' ? t('libresign', 'Saving...') : t('libresign', 'Changes saved') }}
						</p>
					</div>

					<div v-if="saveStatus === 'saving'" class="policy-workbench__saving-overlay" aria-live="polite" aria-busy="true">
						<div class="policy-workbench__saving-spinner" aria-hidden="true"></div>
						<p>{{ t('libresign', 'Saving...') }}</p>
					</div>
				</section>
			</div>
		</NcDialog>

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
	mdiFormatListBulletedSquare,
	mdiViewGridOutline,
} from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import PolicyRuleCard from './PolicyRuleCard.vue'
import { usePoliciesStore } from '../../../store/policies'
import { createRealPolicyWorkbenchState } from './useRealPolicyWorkbench'

defineOptions({
	name: 'RealPolicyWorkbench',
})

const policiesStore = usePoliciesStore()
const state = reactive(createRealPolicyWorkbenchState())
const settingsFilter = ref('')
const isSmallViewport = ref(false)
const catalogLayout = ref<'cards' | 'compact'>('cards')
const saveStatus = ref<'idle' | 'saving' | 'saved'>('idle')
const saveFeedbackTimeout = ref<number | null>(null)
const pendingRemoval = ref<{ ruleId: string, scope: 'system' | 'group' | 'user', targetLabel: string, help: string } | null>(null)
const isRemovingRule = ref(false)
const removalFeedback = ref<string | null>(null)
const removalFeedbackTimeout = ref<number | null>(null)
const lastPress = ref<{ surface: 'cards' | 'list', key: string, x: number, y: number } | null>(null)
const recentSelectionGesture = ref<{ surface: 'cards' | 'list', key: string, at: number } | null>(null)

const DRAG_OPEN_THRESHOLD_PX = 6
const SELECTION_GUARD_WINDOW_MS = 400

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

const systemRuleBadges = computed(() => {
	if (!state.inheritedSystemRule || !state.activeDefinition) {
		return []
	}

	const allowOverrideBadge = state.activeDefinition.formatAllowOverride(state.inheritedSystemRule.allowChildOverride)

	return allowOverrideBadge ? [allowOverrideBadge] : []
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

const pendingRemovalMessage = computed(() => {
	if (!pendingRemoval.value) {
		return ''
	}

	return t('libresign', 'You are about to remove the rule for {target}. {help}', {
		target: pendingRemoval.value.targetLabel,
		help: pendingRemoval.value.help,
	})
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
			label: isRemovingRule.value ? t('libresign', 'Removing rule...') : t('libresign', 'Remove rule'),
			variant: 'error' as const,
			disabled: isRemovingRule.value,
			callback: () => {
				void confirmRuleRemoval()
			},
		},
	]
})

function groupRuleBadges(allowChildOverride: boolean) {
	if (!state.activeDefinition) {
		return []
	}

	const allowOverrideBadge = state.activeDefinition.formatAllowOverride(allowChildOverride)
	return allowOverrideBadge ? [allowOverrideBadge] : []
}

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

	catalogLayout.value = effectiveCatalogLayout.value === 'cards' ? 'compact' : 'cards'
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
		? t('libresign', 'Removing this rule will make all groups and users inherit the platform default.')
		: scope === 'group'
			? t('libresign', 'Removing this rule will restore inherited behavior from the global default for this group.')
			: t('libresign', 'Removing this rule will restore inherited behavior for this user.')

	pendingRemoval.value = { ruleId, scope, targetLabel, help }
}

function cancelRuleRemoval() {
	pendingRemoval.value = null
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
			? t('libresign', 'Global default reset. Inherited behavior is now active.')
			: scope === 'group'
				? t('libresign', 'Group override removed. Inherited behavior from the global default is now active.')
				: t('libresign', 'User override removed. Inherited behavior is now active.')

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

onMounted(async () => {
	updateViewportMode()
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
		grid-template-columns: minmax(240px, 1.2fr) minmax(290px, 1fr) auto;
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
		display: grid;
		grid-template-columns: minmax(0, 1.8fr) auto auto;
		gap: 0.65rem;
		font-size: 0.9rem;
		color: var(--color-text-maxcontrast);
		min-width: 0;
	}

	&__settings-row-stat {
		min-width: 0;
		white-space: nowrap;

		&--default {
			display: flex;
			align-items: baseline;
			gap: 0.25rem;
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
		gap: 1.25rem;
	}

	&__dialog-header {
		display: flex;
		justify-content: space-between;
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

	&__dialog-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;

		:deep(.button-vue) {
			max-width: 100%;
		}
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

	&__editor-empty {
		p,
		h3 {
			margin: 0;
		}

		p:last-of-type {
			margin-top: 0.25rem;
			color: var(--color-text-maxcontrast);
		}
	}

	&__editor-modal-body {
		width: min(860px, 100%);
		margin: 0 auto;
	}

	&__group-header,
	&__editor-header {
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
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
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

		&__removal-feedback {
			margin: 0 0 0.8rem;
			padding: 0.65rem 0.8rem;
			border: 1px solid color-mix(in srgb, var(--color-success) 36%, transparent);
			border-radius: 10px;
			background: color-mix(in srgb, var(--color-success) 12%, var(--color-main-background));
			color: var(--color-main-text);
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
			gap: 1rem;
		}

		&__editor-panel,
		&__editor-mobile-hint,
		&__editor-empty {
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
		&__editor-empty,
		&__setting-tile {
			padding: 1rem;
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
			gap: 0.75rem;
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

	}
}
</style>
