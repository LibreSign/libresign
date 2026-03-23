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

				<div v-if="state.summary" class="policy-workbench__summary-wrap" :aria-label="t('libresign', 'Current policy state')">
					<NcNoteCard type="info">
						<div class="policy-workbench__summary-line">
							<p class="policy-workbench__summary-primary">
								{{ t('libresign', 'Current value: {value}', { value: state.summary.currentBaseValue }) }}
							</p>
							<p class="policy-workbench__summary-source">
								{{ t('libresign', 'Source: {source}', { source: state.summary.baseSource }) }}
							</p>
							<p class="policy-workbench__summary-secondary">
								<span>{{ state.summary.activeGroupExceptions }} {{ t('libresign', 'group exception', 'group exceptions', state.summary.activeGroupExceptions) }}</span>
								<span class="policy-workbench__summary-divider">·</span>
								<span>{{ state.summary.activeUserExceptions }} {{ t('libresign', 'user exception', 'user exceptions', state.summary.activeUserExceptions) }}</span>
							</p>
							<NcButton
								variant="tertiary"
								size="small"
								class="policy-workbench__summary-learn-link"
								:aria-label="t('libresign', 'Learn how inheritance works')"
								:aria-expanded="showPrecedenceExplanation ? 'true' : 'false'"
								@click="showPrecedenceExplanation = !showPrecedenceExplanation">
								<template #icon>
									<NcIconSvgWrapper :path="showPrecedenceExplanation ? mdiChevronUp : mdiChevronDown" :size="16" />
								</template>
								{{ t('libresign', 'How it works') }}
							</NcButton>
						</div>

						<section v-show="showPrecedenceExplanation" class="policy-workbench__precedence-explanation" :aria-hidden="showPrecedenceExplanation ? 'false' : 'true'">
							<div class="policy-workbench__precedence-header">
								<h3>{{ t('libresign', 'Inheritance order') }}</h3>
								<NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="18" />
							</div>
							<ul class="policy-workbench__precedence-list">
								<li>{{ t('libresign', 'User rule overrides group rule') }}</li>
								<li>{{ t('libresign', 'Group rule overrides global default') }}</li>
								<li>{{ t('libresign', 'Global default overrides system default') }}</li>
								<li>{{ t('libresign', 'System default is used if no global default exists') }}</li>
							</ul>
						</section>
					</NcNoteCard>
				</div>

				<div class="policy-workbench__content">
					<!-- Default for this instance section -->
					<section class="policy-workbench__section" role="region" :aria-label="t('libresign', 'Default for this instance')">
						<h3 class="policy-workbench__section-title">{{ t('libresign', 'Default for this instance') }}</h3>
						<p class="policy-workbench__default-value">{{ state.summary?.currentBaseValue || '—' }}</p>
						<p class="policy-workbench__default-reason">
							{{ state.hasGlobalDefault
								? t('libresign', 'This instance has a configured global default.')
								: t('libresign', 'Using system default because no global default is configured.') }}
						</p>

						<!-- Edit or create global default -->
						<div v-if="state.inheritedSystemRule && state.hasGlobalDefault" class="policy-workbench__default-actions">
							<NcButton
								v-if="state.viewMode === 'system-admin'"
								variant="primary"
								size="small"
								:aria-label="t('libresign', 'Edit global default')"
								@click="state.startEditor({ scope: 'system', ruleId: state.inheritedSystemRule.id })">
								{{ t('libresign', 'Edit') }}
							</NcButton>
							<NcButton
								v-if="state.viewMode === 'system-admin'"
								variant="secondary"
								size="small"
								:aria-label="t('libresign', 'Remove global default')"
								@click="promptRuleRemoval(state.inheritedSystemRule.id, 'system', t('libresign', 'Global default rule'))">
								{{ t('libresign', 'Remove') }}
							</NcButton>
						</div>

						<NcButton
							v-else-if="state.viewMode === 'system-admin'"
							variant="primary"
							size="small"
							:aria-label="t('libresign', 'Set global default')"
							@click="state.startEditor({ scope: 'system' })">
							{{ t('libresign', 'Set global default') }}
						</NcButton>
					</section>

					<section class="policy-workbench__section" role="region" :aria-label="t('libresign', 'Exceptions')">
						<h3 class="policy-workbench__section-title">{{ t('libresign', 'Exceptions') }}</h3>

						<div class="policy-workbench__tabs" role="tablist" :aria-label="t('libresign', 'Exception type')">
							<NcButton
								role="tab"
								variant="tertiary"
								:aria-selected="activeExceptionTab === 'group'"
								:class="['policy-workbench__tab', { 'policy-workbench__tab--active': activeExceptionTab === 'group' }]"
								@click="setExceptionTab('group')">
								{{ t('libresign', 'Group exceptions ({count})', { count: String(state.visibleGroupRules.length) }) }}
							</NcButton>
							<NcButton
								role="tab"
								variant="tertiary"
								:aria-selected="activeExceptionTab === 'user'"
								:class="['policy-workbench__tab', { 'policy-workbench__tab--active': activeExceptionTab === 'user' }]"
								@click="setExceptionTab('user')">
								{{ t('libresign', 'User exceptions ({count})', { count: String(state.visibleUserRules.length) }) }}
							</NcButton>
						</div>

						<div v-if="activeExceptionTab === 'group'" class="policy-workbench__table-section">
							<h4 class="policy-workbench__table-heading">{{ t('libresign', 'Group exceptions') }}</h4>
							<div class="policy-workbench__table-toolbar">
								<NcTextField
									:model-value="groupExceptionSearch"
									:label="t('libresign', 'Search group exceptions')"
									:placeholder="t('libresign', 'Search by group or value')"
									@update:modelValue="onGroupSearchChange" />
								<div class="policy-workbench__table-filter">
									<span>{{ t('libresign', 'Users can override') }}</span>
									<NcActions :aria-label="t('libresign', 'Users can override filter')">
										<template #icon>
											<NcIconSvgWrapper :path="mdiFilter" :size="18" />
										</template>
										<NcActionButton type="radio" :model-value="groupOverrideFilter === 'all'" @update:modelValue="setGroupOverrideFilter('all', $event)">
											{{ t('libresign', 'All') }}
										</NcActionButton>
										<NcActionButton type="radio" :model-value="groupOverrideFilter === 'allowed'" @update:modelValue="setGroupOverrideFilter('allowed', $event)">
											{{ t('libresign', 'Users may override') }}
										</NcActionButton>
										<NcActionButton type="radio" :model-value="groupOverrideFilter === 'blocked'" @update:modelValue="setGroupOverrideFilter('blocked', $event)">
											{{ t('libresign', 'Users must inherit') }}
										</NcActionButton>
									</NcActions>
								</div>
								<div class="policy-workbench__table-toolbar-action">
									<NcButton
										v-if="state.viewMode === 'system-admin'"
										variant="secondary"
										size="small"
										@click="state.startEditor({ scope: 'group' })">
										{{ t('libresign', 'Add group exception') }}
									</NcButton>
								</div>
							</div>

							<div class="policy-workbench__table-scroll">
								<table class="policy-workbench__table">
									<thead>
										<tr>
											<th>{{ t('libresign', 'Group') }}</th>
											<th>{{ t('libresign', 'Value') }}</th>
											<th>{{ t('libresign', 'Users can override') }}</th>
											<th>{{ t('libresign', 'Actions') }}</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="rule in pagedGroupExceptions" :key="rule.id">
											<td>{{ state.resolveTargetLabel('group', rule.targetId || '') }}</td>
											<td>{{ summarizeRuleValue(rule.value) }}</td>
											<td>
												<span :class="['policy-workbench__row-badge', rule.allowChildOverride ? 'policy-workbench__row-badge--ok' : 'policy-workbench__row-badge--muted']">
													{{ rule.allowChildOverride ? t('libresign', 'Can override') : t('libresign', 'Must inherit') }}
												</span>
											</td>
											<td class="policy-workbench__table-actions">
												<NcActions :force-name="true" :inline="2">
													<NcActionButton @click="state.startEditor({ scope: 'group', ruleId: rule.id })">
														<template #icon>
															<NcIconSvgWrapper :path="mdiPencil" :size="16" />
														</template>
														{{ t('libresign', 'Edit') }}
													</NcActionButton>
													<NcActionButton @click="promptRuleRemoval(rule.id, 'group', state.resolveTargetLabel('group', rule.targetId || ''))">
														<template #icon>
															<NcIconSvgWrapper :path="mdiDelete" :size="16" />
														</template>
														{{ t('libresign', 'Remove') }}
													</NcActionButton>
												</NcActions>
											</td>
										</tr>
										<tr v-if="pagedGroupExceptions.length === 0">
											<td colspan="4" class="policy-workbench__table-empty">{{ t('libresign', 'No group exceptions match the current filters.') }}</td>
										</tr>
									</tbody>
								</table>
							</div>

							<div v-if="groupPageCount > 1" class="policy-workbench__pagination">
								<NcButton variant="tertiary" size="small" :disabled="groupPage <= 1" @click="groupPage -= 1">{{ t('libresign', 'Previous') }}</NcButton>
								<span>{{ t('libresign', 'Page {current} of {total}', { current: String(groupPage), total: String(groupPageCount) }) }}</span>
								<NcButton variant="tertiary" size="small" :disabled="groupPage >= groupPageCount" @click="groupPage += 1">{{ t('libresign', 'Next') }}</NcButton>
							</div>
						</div>

						<div v-else class="policy-workbench__table-section">
							<h4 class="policy-workbench__table-heading">{{ t('libresign', 'User exceptions') }}</h4>
							<div class="policy-workbench__table-toolbar">
								<NcTextField
									:model-value="userExceptionSearch"
									:label="t('libresign', 'Search user exceptions')"
									:placeholder="t('libresign', 'Search by user or value')"
									@update:modelValue="onUserSearchChange" />
								<div class="policy-workbench__table-filter">
									<span>{{ t('libresign', 'Value') }}</span>
									<NcActions :aria-label="t('libresign', 'User value filter')">
										<template #icon>
											<NcIconSvgWrapper :path="mdiFilter" :size="18" />
										</template>
										<NcActionButton type="radio" :model-value="userValueFilter === 'all'" @update:modelValue="setUserValueFilter('all', $event)">
											{{ t('libresign', 'All') }}
										</NcActionButton>
										<NcActionButton type="radio" :model-value="userValueFilter === 'parallel'" @update:modelValue="setUserValueFilter('parallel', $event)">
											{{ t('libresign', 'Simultaneous (Parallel)') }}
										</NcActionButton>
										<NcActionButton type="radio" :model-value="userValueFilter === 'ordered_numeric'" @update:modelValue="setUserValueFilter('ordered_numeric', $event)">
											{{ t('libresign', 'Sequential') }}
										</NcActionButton>
									</NcActions>
								</div>
								<div class="policy-workbench__table-toolbar-action">
									<NcButton
										variant="secondary"
										size="small"
										@click="state.startEditor({ scope: 'user' })">
										{{ t('libresign', 'Add user exception') }}
									</NcButton>
								</div>
							</div>

							<NcEmptyContent
								v-if="state.visibleUserRules.length === 0 && !userExceptionSearch && userValueFilter === 'all'"
								:name="t('libresign', 'No user exceptions are configured.')"
								:description="t('libresign', 'User exceptions can be created unless a group rule requires inheritance.')" />

							<p v-if="state.createUserOverrideDisabledReason" class="policy-workbench__table-note">
								{{ t('libresign', 'Some users may not allow user exceptions because their group rule requires inheritance.') }}
							</p>

							<div class="policy-workbench__table-scroll">
								<table class="policy-workbench__table">
									<thead>
										<tr>
											<th>{{ t('libresign', 'User') }}</th>
											<th>{{ t('libresign', 'Value') }}</th>
											<th>{{ t('libresign', 'Type') }}</th>
											<th>{{ t('libresign', 'Actions') }}</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="rule in pagedUserExceptions" :key="rule.id">
											<td>{{ state.resolveTargetLabel('user', rule.targetId || '') }}</td>
											<td>{{ summarizeRuleValue(rule.value) }}</td>
											<td>{{ t('libresign', 'Final') }}</td>
											<td class="policy-workbench__table-actions">
												<NcActions :force-name="true" :inline="2">
													<NcActionButton @click="state.startEditor({ scope: 'user', ruleId: rule.id })">
														<template #icon>
															<NcIconSvgWrapper :path="mdiPencil" :size="16" />
														</template>
														{{ t('libresign', 'Edit') }}
													</NcActionButton>
													<NcActionButton @click="promptRuleRemoval(rule.id, 'user', state.resolveTargetLabel('user', rule.targetId || ''))">
														<template #icon>
															<NcIconSvgWrapper :path="mdiDelete" :size="16" />
														</template>
														{{ t('libresign', 'Remove') }}
													</NcActionButton>
												</NcActions>
											</td>
										</tr>
										<tr v-if="pagedUserExceptions.length === 0">
											<td colspan="4" class="policy-workbench__table-empty">{{ t('libresign', 'No user exceptions match the current filters.') }}</td>
										</tr>
									</tbody>
								</table>
							</div>

							<div v-if="userPageCount > 1" class="policy-workbench__pagination">
								<NcButton variant="tertiary" size="small" :disabled="userPage <= 1" @click="userPage -= 1">{{ t('libresign', 'Previous') }}</NcButton>
								<span>{{ t('libresign', 'Page {current} of {total}', { current: String(userPage), total: String(userPageCount) }) }}</span>
								<NcButton variant="tertiary" size="small" :disabled="userPage >= userPageCount" @click="userPage += 1">{{ t('libresign', 'Next') }}</NcButton>
							</div>
						</div>
					</section>
				</div>

				<!-- Editor panel side-by-side (desktop) -->
				<div v-if="state.editorDraft && !shouldUseEditorModal" class="policy-workbench__editor-aside">
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
									<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="requestCancelEditor()">
										{{ t('libresign', 'Cancel') }}
									</NcButton>
								</div>
								<p v-if="saveStatus !== 'idle'" class="policy-workbench__save-feedback" aria-live="polite">
									{{ saveStatus === 'saving' ? t('libresign', 'Saving...') : t('libresign', 'Changes saved') }}
								</p>

								<div v-if="saveStatus === 'saving'" class="policy-workbench__saving-overlay" aria-live="polite" aria-busy="true">
									<div class="policy-workbench__saving-spinner" aria-hidden="true"></div>
									<p>{{ t('libresign', 'Saving...') }}</p>
								</div>
							</div>
						</section>

						<section v-else-if="state.editorDraft && shouldUseEditorModal" class="policy-workbench__editor-mobile-hint">
							<p class="policy-workbench__eyebrow">{{ t('libresign', 'Editing') }}</p>
							<h3>{{ t('libresign', 'Editor open in modal') }}</h3>
							<p>
								{{ t('libresign', 'Your form is displayed in the overlay.') }}
							</p>
						</section>
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
				<section class="policy-workbench__editor-panel">
					<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
						<div class="policy-workbench__editor-header">
							<p class="policy-workbench__eyebrow">
								{{ state.editorMode === 'edit' ? t('libresign', 'Edit rule') : t('libresign', 'Create rule') }}
							</p>
							<h3>{{ editorTitle }}</h3>
							<p>{{ editorHelp }}</p>
						</div>

						<div v-if="state.editorDraft && state.editorDraft.scope !== 'system'" class="policy-workbench__field">
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
							v-if="state.editorDraft && state.editorDraft.scope !== 'user'"
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
							<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="requestCancelEditor()">
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
	mdiChevronDown,
	mdiChevronUp,
	mdiDelete,
	mdiFilter,
	mdiFormatListBulletedSquare,
	mdiHelpCircleOutline,
	mdiPencil,
	mdiViewGridOutline,
} from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { usePoliciesStore } from '../../../store/policies'
import { useUserConfigStore } from '../../../store/userconfig.js'
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
const isRemovingRule = ref(false)
const removalFeedback = ref<string | null>(null)
const removalFeedbackTimeout = ref<number | null>(null)
const lastPress = ref<{ surface: 'cards' | 'list', key: string, x: number, y: number } | null>(null)
const recentSelectionGesture = ref<{ surface: 'cards' | 'list', key: string, at: number } | null>(null)
const showPrecedenceExplanation = ref(false)
const activeExceptionTab = ref<'group' | 'user'>('group')
const groupExceptionSearch = ref('')
const userExceptionSearch = ref('')
const groupOverrideFilter = ref<'all' | 'allowed' | 'blocked'>('all')
const userValueFilter = ref<'all' | 'parallel' | 'ordered_numeric'>('all')
const groupPage = ref(1)
const userPage = ref(1)
const EXCEPTIONS_PAGE_SIZE = 20

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

const filteredGroupExceptions = computed(() => {
	const normalized = groupExceptionSearch.value.trim().toLowerCase()

	return state.visibleGroupRules.filter((rule) => {
		if (groupOverrideFilter.value === 'allowed' && !rule.allowChildOverride) {
			return false
		}

		if (groupOverrideFilter.value === 'blocked' && rule.allowChildOverride) {
			return false
		}

		if (!normalized) {
			return true
		}

		const label = state.resolveTargetLabel('group', rule.targetId || '').toLowerCase()
		const value = summarizeRuleValue(rule.value).toLowerCase()
		return label.includes(normalized) || value.includes(normalized)
	})
})

const filteredUserExceptions = computed(() => {
	const normalized = userExceptionSearch.value.trim().toLowerCase()

	return state.visibleUserRules.filter((rule) => {
		if (userValueFilter.value !== 'all') {
			const mode = typeof rule.value === 'string' ? rule.value : ''
			if (mode !== userValueFilter.value) {
				return false
			}
		}

		if (!normalized) {
			return true
		}

		const label = state.resolveTargetLabel('user', rule.targetId || '').toLowerCase()
		const value = summarizeRuleValue(rule.value).toLowerCase()
		return label.includes(normalized) || value.includes(normalized)
	})
})

const groupPageCount = computed(() => Math.max(1, Math.ceil(filteredGroupExceptions.value.length / EXCEPTIONS_PAGE_SIZE)))
const userPageCount = computed(() => Math.max(1, Math.ceil(filteredUserExceptions.value.length / EXCEPTIONS_PAGE_SIZE)))

const pagedGroupExceptions = computed(() => {
	if (groupPage.value > groupPageCount.value) {
		groupPage.value = groupPageCount.value
	}

	const start = (groupPage.value - 1) * EXCEPTIONS_PAGE_SIZE
	return filteredGroupExceptions.value.slice(start, start + EXCEPTIONS_PAGE_SIZE)
})

const pagedUserExceptions = computed(() => {
	if (userPage.value > userPageCount.value) {
		userPage.value = userPageCount.value
	}

	const start = (userPage.value - 1) * EXCEPTIONS_PAGE_SIZE
	return filteredUserExceptions.value.slice(start, start + EXCEPTIONS_PAGE_SIZE)
})

const resolutionModeLabel = computed(() => {
	if (state.policyResolutionMode === 'merge') {
		return t('libresign', 'Merged values from multiple groups')
	}

	if (state.policyResolutionMode === 'conflict_requires_selection') {
		return t('libresign', 'Conflict may require explicit user selection')
	}

	return t('libresign', 'Single value by precedence')
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

function setExceptionTab(tab: 'group' | 'user') {
	activeExceptionTab.value = tab
}

function onGroupSearchChange(value: string | number) {
	groupExceptionSearch.value = String(value ?? '')
	groupPage.value = 1
}

function onUserSearchChange(value: string | number) {
	userExceptionSearch.value = String(value ?? '')
	userPage.value = 1
}

function setGroupOverrideFilter(value: 'all' | 'allowed' | 'blocked', selected: boolean) {
	if (!selected) {
		return
	}

	groupOverrideFilter.value = value
	groupPage.value = 1
}

function setUserValueFilter(value: 'all' | 'parallel' | 'ordered_numeric', selected: boolean) {
	if (!selected) {
		return
	}

	userValueFilter.value = value
	userPage.value = 1
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

	if (state.isDraftDirty && !window.confirm(t('libresign', 'Discard unsaved changes?'))) {
		return
	}

	state.cancelEditor()
}

function requestCloseSetting() {
	if (saveStatus.value === 'saving' || isRemovingRule.value) {
		return
	}

	if (state.isDraftDirty && !window.confirm(t('libresign', 'Discard unsaved changes?'))) {
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
		&__editor-aside {
			display: flex;
			flex-direction: column;
			gap: 0.75rem;
			position: sticky;
			top: 0;
			max-height: 90vh;
			overflow-y: auto;
			padding-left: 1rem;
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
	}

	&__summary-wrap {
		:deep(.notecard) {
			margin: 0;
		}
	}

	&__summary-primary {
		margin: 0;
		font-size: 1.02rem;
		font-weight: 700;
		line-height: 1.35;
	}

	&__summary-source {
		margin: 0;
		font-size: 0.88rem;
		font-weight: 500;
		color: var(--color-main-text);
	}

	&__summary-secondary {
		margin: 0;
		display: flex;
		align-items: center;
		gap: 0.4rem;
		font-size: 0.88rem;
		color: var(--color-text-maxcontrast);
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

	&__help-close {
		background: none;
		border: none;
		color: var(--color-primary-element);
		cursor: pointer;
		font-size: 0.84rem;
		padding: 0.4rem 0;
		text-decoration: underline;
		margin-top: 0.5rem;

		&:hover {
			opacity: 0.8;
		}

		&:focus {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
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

	&__tab {
		:deep(.button-vue) {
			border-radius: 999px;
			font-size: 0.84rem;
			border: 1px solid var(--color-border-maxcontrast);
		}

		&--active {
			:deep(.button-vue) {
				border-color: var(--color-primary-element);
				background: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-main-background));
				font-weight: 600;
			}
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

	&__table-filter {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		font-size: 0.8rem;
		color: var(--color-text-maxcontrast);

		:deep(.button-vue) {
			min-width: 180px;
			justify-content: flex-start;
		}
	}

	&__table-scroll {
		overflow-x: auto;
		border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
		border-radius: 8px;
	}

	&__table {
		width: 100%;
		border-collapse: collapse;
		font-size: 0.88rem;

		th,
		td {
			text-align: left;
			padding: 0.48rem 0.65rem;
			border-bottom: 1px solid var(--color-border);
			vertical-align: middle;
		}

		th {
			font-size: 0.8rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.02em;
			color: var(--color-text-maxcontrast);
			background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-dark));
		}

		tr:last-child td {
			border-bottom: none;
		}

		tbody tr:hover td {
			background: color-mix(in srgb, var(--color-main-background) 90%, var(--color-background-dark));
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

	&__row-badge {
		display: inline-flex;
		align-items: center;
		padding: 0.14rem 0.45rem;
		border-radius: 999px;
		font-size: 0.78rem;
		font-weight: 600;

		&--ok {
			color: color-mix(in srgb, var(--color-success) 88%, var(--color-main-text));
			background: color-mix(in srgb, var(--color-success) 14%, transparent);
		}

		&--muted {
			color: var(--color-main-text);
			background: color-mix(in srgb, var(--color-border-maxcontrast) 16%, transparent);
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
			gap: 0.85rem;
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
