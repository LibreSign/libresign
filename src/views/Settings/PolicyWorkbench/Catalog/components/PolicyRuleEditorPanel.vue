<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="policy-workbench__editor-panel">
		<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
			<div class="policy-workbench__editor-header">
				<p v-if="editorHelp">{{ editorHelp }}</p>
				<PolicyPrecedenceHint :scopes="precedenceScopes" variant="editor" />
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
					{{ backButtonLabel }}
				</NcButton>
				<NcButton variant="primary" :aria-label="savePolicyRuleActionLabel" :loading="saveStatus === 'saving'" :disabled="!canSaveDraft" @click="$emit('save')">
					{{ saveActionButtonLabel }}
				</NcButton>
				<NcButton variant="secondary" :aria-label="cancelEditingLabel" :disabled="saveStatus === 'saving'" @click="$emit('cancel')">
					{{ cancelButtonLabel }}
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
import PolicyPrecedenceHint from '../../components/PolicyPrecedenceHint.vue'

interface EditorDraft {
	scope: 'system' | 'group' | 'user'
	value: EffectivePolicyValue
	allowChildOverride: boolean
	targetIds: string[]
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
	precedenceScopes?: string[]
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
	precedenceScopes: () => [],
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

// TRANSLATORS Secondary button label in the policy rule editor that returns to the previous step where the user chooses the rule type.
const backButtonLabel = t('libresign', '← Back')
// TRANSLATORS Primary button label in edit mode of the policy rule editor. It saves changes to the current rule.
const saveChangesButtonLabel = t('libresign', 'Save changes')
// TRANSLATORS Primary button label in create mode of the policy rule editor. It creates a new policy rule.
const createRuleButtonLabel = t('libresign', 'Create rule')
// TRANSLATORS Secondary button label in the policy rule editor that closes the panel without saving the current changes.
const cancelButtonLabel = t('libresign', 'Cancel')

// TRANSLATORS Label shown above the target selector in the policy rule editor when the rule applies to groups.
const targetGroupsLabel = t('libresign', 'Scope groups')
// TRANSLATORS Label shown above the target selector in the policy rule editor when the rule applies to individual accounts.
const targetAccountsLabel = t('libresign', 'Scope accounts')
const targetScopeLabel = computed(() => props.editorDraft.scope === 'group'
	? targetGroupsLabel
	: targetAccountsLabel)

// TRANSLATORS Search placeholder in the policy rule editor for finding groups that this rule should target.
const searchGroupsPlaceholder = t('libresign', 'Search scope groups')
// TRANSLATORS Search placeholder in the policy rule editor for finding individual accounts that this rule should target.
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
const saveActionButtonLabel = computed(() => props.editorMode === 'edit'
	? saveChangesButtonLabel
	: createRuleButtonLabel)

// TRANSLATORS Help text below the customization switch for an account-scoped rule, explaining that this account may save personal defaults and choose request-specific values.
const accountCustomizationAllowedDescription = t('libresign', 'This account can customize personal defaults and request-specific values.')
// TRANSLATORS Help text below the customization switch for an account-scoped rule, explaining that the effective value is mandatory and cannot be customized by this account.
const accountCustomizationLockedDescription = t('libresign', 'This value is mandatory for this account.')
// TRANSLATORS Help text below the customization switch for a parent policy scope, explaining that child scopes such as groups or accounts may override this value with a more specific one.
const childScopesCanOverrideDescription = t('libresign', 'Groups and accounts can define a more specific value.')
// TRANSLATORS Help text below the customization switch for a parent policy scope, explaining that child scopes such as groups or accounts must inherit this value unchanged.
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
		// TRANSLATORS Title of the switch in the policy rule editor that allows a specific account to customize its own effective value.
		return t('libresign', 'Allow this account to customize')
	}

	// TRANSLATORS Title of the switch in the policy rule editor that allows lower scopes, such as groups or accounts, to override the parent policy value.
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
