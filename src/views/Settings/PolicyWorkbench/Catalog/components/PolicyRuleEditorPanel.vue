<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="policy-workbench__editor-panel">
		<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
			<div class="policy-workbench__editor-header">
				<p v-if="editorHelp">{{ editorHelp }}</p>
				<!-- TRANSLATORS Precedence hint for policy scopes from most specific to least specific. -->
				<p class="policy-workbench__precedence-hint">{{ t('libresign', 'Priority: Account > Group > Default') }}</p>
			</div>

			<div v-if="editorDraft.scope !== 'system'" class="policy-workbench__field">
				<label class="policy-workbench__label">
					{{ targetScopeLabel }}
				</label>
				<NcSelectUsers
					:model-value="selectedTargetOptions"
					:options="availableTargets"
					:aria-label="targetScopeLabel"
					:placeholder="targetScopeSearchPlaceholder"
					:loading="loadingTargets"
					:multiple="true"
					:keep-open="true"
					:disabled="saveStatus === 'saving'"
					@search="$emit('search-targets', $event)"
					@update:modelValue="$emit('update-targets', $event)" />
			</div>

			<div v-if="activeEditor" class="policy-workbench__setting-surface">
				<component
					:is="activeEditor"
					:model-value="editorDraft.value"
					v-bind="editorProps"
					:editor-scope="editorDraft.scope"
					:editor-mode="editorMode"
					:has-selected-targets="editorDraft.targetIds.length > 0"
					@template-changed="$emit('template-changed')"
					@update:modelValue="$emit('update-value', $event)" />
			</div>

			<NcCheckboxRadioSwitch
				v-if="showAllowOverrideSwitch && allowOverrideMutable"
				type="switch"
				:model-value="editorDraft.allowChildOverride"
				:disabled="saveStatus === 'saving' || !allowOverrideMutable"
				@update:modelValue="$emit('update-allow-override', $event)">
				<div class="policy-workbench__switch-copy">
					<span>{{ allowOverrideTitle }}</span>
					<p>
						{{ allowOverrideDescription }}
					</p>
				</div>
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="duplicateMessage" type="error">
				{{ duplicateMessage }}
			</NcNoteCard>

			<div v-if="showInlineActions" class="policy-workbench__editor-actions" :class="{ 'policy-workbench__editor-actions--sticky-mobile': stickyActions }">
				<NcButton v-if="showBackButton" variant="tertiary" :aria-label="goBackToRuleTypeSelectionLabel" :disabled="saveStatus === 'saving'" @click="$emit('back')">
					{{ t('libresign', '← Back') }}
				</NcButton>
				<NcButton variant="primary" :aria-label="savePolicyRuleActionLabel" :loading="saveStatus === 'saving'" :disabled="!canSaveDraft" @click="$emit('save')">
					{{ editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule') }}
				</NcButton>
				<NcButton variant="secondary" :aria-label="cancelEditingLabel" :disabled="saveStatus === 'saving'" @click="$emit('cancel')">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</div>
		</div>

		<div v-if="saveStatus === 'saving'" class="policy-workbench__saving-overlay" aria-live="polite" aria-busy="true">
			<div class="policy-workbench__saving-spinner" aria-hidden="true"></div>
		</div>
	</section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import type { EffectivePolicyValue } from '@/types'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'

interface EditorDraft {
	scope: 'system' | 'group' | 'user'
	value: EffectivePolicyValue
	allowChildOverride: boolean
}

interface TargetOption {
	id: string
	displayName: string
	subname?: string
	user?: string
	isNoUser?: boolean
}

const props = withDefaults(defineProps<{
	editorDraft: EditorDraft
	editorMode: 'create' | 'edit' | null
	editorTitle: string
	editorHelp: string
	activeEditor: unknown
	editorProps?: Record<string, unknown>
	selectedTargetOptions: TargetOption[]
	availableTargets: TargetOption[]
	loadingTargets: boolean
	duplicateMessage: string | null
	canSaveDraft: boolean
	saveStatus: 'idle' | 'saving' | 'saved'
	showInlineActions?: boolean
	stickyActions?: boolean
	showBackButton?: boolean
	showAllowOverrideSwitch?: boolean
	allowOverrideMutable?: boolean
}>(), {
	showInlineActions: true,
	stickyActions: false,
	showBackButton: false,
	showAllowOverrideSwitch: true,
	allowOverrideMutable: true,
	editorProps: () => ({}),
})

defineEmits<{
	(e: 'search-targets', value: string): void
	(e: 'update-targets', value: { id: string } | Array<{ id: string }> | null): void
	(e: 'update-value', value: EffectivePolicyValue): void
	(e: 'template-changed'): void
	(e: 'update-allow-override', value: boolean): void
	(e: 'back'): void
	(e: 'save'): void
	(e: 'cancel'): void
}>()

// TRANSLATORS Label for selecting groups in the current policy scope.
const targetGroupsLabel = t('libresign', 'Scope groups')
// TRANSLATORS Label for selecting accounts in the current policy scope.
const targetAccountsLabel = t('libresign', 'Scope accounts')
const targetScopeLabel = computed(() => props.editorDraft.scope === 'group'
	? targetGroupsLabel
	: targetAccountsLabel)

// TRANSLATORS Placeholder text for searching scope groups in policy picker.
const searchGroupsPlaceholder = t('libresign', 'Search scope groups')
// TRANSLATORS Placeholder text for searching scope accounts in policy picker.
const searchAccountsPlaceholder = t('libresign', 'Search scope accounts')
const targetScopeSearchPlaceholder = computed(() => props.editorDraft.scope === 'group'
	? searchGroupsPlaceholder
	: searchAccountsPlaceholder)

// TRANSLATORS Button label to return to policy rule type selection step.
const goBackToRuleTypeSelectionLabel = t('libresign', 'Go back to rule type selection')
// TRANSLATORS Button label to cancel rule editing without saving.
const cancelEditingLabel = t('libresign', 'Cancel editing')
// TRANSLATORS Primary action aria-label in edit mode to persist changes to an existing policy rule.
const savePolicyRuleChangesAriaLabel = t('libresign', 'Save policy rule changes')
// TRANSLATORS Primary action aria-label in create mode to create a new policy rule.
const createPolicyRuleAriaLabel = t('libresign', 'Create policy rule')
const savePolicyRuleActionLabel = computed(() => props.editorMode === 'edit'
	? savePolicyRuleChangesAriaLabel
	: createPolicyRuleAriaLabel)

// TRANSLATORS User-scope description when account-level customization is allowed.
const accountCustomizationAllowedDescription = t('libresign', 'This account can customize personal defaults and request-specific values.')
// TRANSLATORS User-scope description when value is mandatory and cannot be customized.
const accountCustomizationLockedDescription = t('libresign', 'This value is mandatory for this account.')
// TRANSLATORS Parent-scope description when groups/accounts may define more specific values.
const childScopesCanOverrideDescription = t('libresign', 'Groups and accounts can define a more specific value.')
// TRANSLATORS Parent-scope description when groups/accounts must inherit parent value.
const childScopesMustInheritDescription = t('libresign', 'Groups and accounts must inherit this value.')

const allowOverrideDescription = computed(() => {
	if (props.editorDraft.scope === 'user') {
		return props.editorDraft.allowChildOverride
			? accountCustomizationAllowedDescription
			: accountCustomizationLockedDescription
	}

	return props.editorDraft.allowChildOverride
		? childScopesCanOverrideDescription
		: childScopesMustInheritDescription
})

const allowOverrideTitle = computed(() => {
	if (props.editorDraft.scope === 'user') {
		// TRANSLATORS Switch title that allows account-level personal customization.
		return t('libresign', 'Allow this account to customize')
	}

	// TRANSLATORS Switch title that allows child scopes to override parent policy values.
	return t('libresign', 'Allow lower-level customization')
})
</script>

<style scoped lang="scss">
.policy-workbench__editor-panel {
	padding: 0 !important;
	border: none !important;
	border-radius: 0 !important;
	background: transparent !important;
	box-shadow: none !important;
	position: static !important;
	overflow: visible !important;
}

.policy-workbench__editor-panel-content {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;
	overflow: visible;
}

.policy-workbench__editor-header {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;

	p {
		margin: 0;
	}
}

.policy-workbench__label {
	font-size: 0.78rem;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--color-text-maxcontrast);
}

.policy-workbench__field {
	display: flex;
	flex-direction: column;
	gap: 0.45rem;
}

.policy-workbench__setting-surface {
	padding: 0;
	border: none;
	border-radius: 0;
	background: transparent;
	box-shadow: none;
}

.policy-workbench__editor-actions {
	display: flex;
	flex-direction: row;
	justify-content: flex-end;
	align-items: center;
	gap: 0.75rem;
	flex-wrap: wrap;

	:deep(.button-vue) {
		width: auto;
		justify-content: center;
		flex-shrink: 0;
	}
}

.policy-workbench__switch-copy {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;

	p {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}
}

.policy-workbench__precedence-hint {
	margin: 0.2rem 0 0;
	font-size: 0.82rem;
	font-weight: 600;
	color: var(--color-main-text);
}

@media (max-width: 640px) {
	.policy-workbench__editor-actions {
		justify-content: flex-start;
	}
}
</style>
