<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="policy-workbench__editor-panel">
		<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
			<div class="policy-workbench__editor-header">
				<p v-if="editorHelp">{{ editorHelp }}</p>
				<p class="policy-workbench__precedence-hint">{{ t('libresign', 'Priority: User > Group > Default') }}</p>
			</div>

			<div v-if="editorDraft.scope !== 'system'" class="policy-workbench__field">
				<label class="policy-workbench__label">
					{{ editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users') }}
				</label>
				<NcSelectUsers
					:model-value="selectedTargetOptions"
					:options="availableTargets"
					:aria-label="editorDraft.scope === 'group' ? t('libresign', 'Target groups') : t('libresign', 'Target users')"
					:placeholder="editorDraft.scope === 'group' ? t('libresign', 'Search groups') : t('libresign', 'Search users')"
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
					@template-changed="$emit('template-changed')"
					@update:modelValue="$emit('update-value', $event)" />
			</div>

			<div
				v-if="showAllowOverrideSwitch && allowOverrideMutable"
				class="policy-workbench__global-behavior"
				:class="{ 'policy-workbench__global-behavior--identify-methods': isIdentifyMethodsEditor }">
				<NcCheckboxRadioSwitch
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
			</div>

			<NcNoteCard v-if="duplicateMessage" type="error">
				{{ duplicateMessage }}
			</NcNoteCard>

			<div v-if="showInlineActions" class="policy-workbench__editor-actions" :class="{ 'policy-workbench__editor-actions--sticky-mobile': stickyActions }">
				<NcButton v-if="showBackButton" variant="tertiary" :aria-label="t('libresign', 'Go back to rule type selection')" :disabled="saveStatus === 'saving'" @click="$emit('back')">
					{{ t('libresign', '← Back') }}
				</NcButton>
				<NcButton variant="primary" :aria-label="editorMode === 'edit' ? t('libresign', 'Save policy rule changes') : t('libresign', 'Create policy rule')" :loading="saveStatus === 'saving'" :disabled="!canSaveDraft" @click="$emit('save')">
					{{ editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule') }}
				</NcButton>
				<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="$emit('cancel')">
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
import type { EffectivePolicyValue } from '../../../types/index'

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

const allowOverrideDescription = computed(() => {
	if (props.editorDraft.scope === 'user') {
		return props.editorDraft.allowChildOverride
			? t('libresign', 'This user can customize personal defaults and request-specific values.')
			: t('libresign', 'This value is mandatory for this user.')
	}

	return props.editorDraft.allowChildOverride
		? t('libresign', 'Groups and users can define a more specific value.')
		: t('libresign', 'Groups and users must inherit this value.')
})

const allowOverrideTitle = computed(() => {
	if (props.editorDraft.scope === 'user') {
		return t('libresign', 'Allow this user to customize')
	}

	return t('libresign', 'Allow lower-level customization')
})

const isIdentifyMethodsEditor = computed(() => {
	if (!props.activeEditor || typeof props.activeEditor !== 'object') {
		return false
	}

	const editorName = (props.activeEditor as { name?: string }).name
	return editorName === 'IdentifyMethodsRuleEditor'
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
	gap: 0.5rem;
	overflow: visible;
}

.policy-workbench__editor-header {
	display: flex;
	flex-direction: column;
	gap: 0.22rem;

	p {
		margin: 0;
		font-size: 0.85rem;
	}
}

.policy-workbench__label {
	font-size: 0.74rem;
	font-weight: 500;
	text-transform: none;
	letter-spacing: 0;
	color: var(--color-text-maxcontrast);
	opacity: 0.85;
}

.policy-workbench__field {
	display: flex;
	flex-direction: column;
	gap: 0.28rem;
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
	gap: 0.5rem;
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
	gap: 0.12rem;

	span {
		font-size: 0.82rem;
		font-weight: 500;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.77rem;
		color: var(--color-text-maxcontrast);
		opacity: 0.8;
	}
}

.policy-workbench__global-behavior {
	margin-top: 0.28rem;
	padding-top: 0.48rem;
	border-top: 1px solid color-mix(in srgb, var(--color-border) 48%, transparent);
	display: flex;
	flex-direction: column;
	gap: 0.16rem;
}

.policy-workbench__global-behavior--identify-methods {
	margin-top: 0.08rem;
	padding-top: 0;
	border-top: 0;
}

.policy-workbench__global-behavior-label {
	margin: 0;
	font-size: 0.74rem;
	font-weight: 600;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.policy-workbench__precedence-hint {
	margin: 0.08rem 0 0;
	font-size: 0.75rem;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
	opacity: 0.8;
}

@media (max-width: 640px) {
	.policy-workbench__editor-actions {
		justify-content: flex-start;
	}
}
</style>
