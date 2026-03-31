<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="policy-workbench__editor-panel">
		<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
			<div class="policy-workbench__editor-header">
				<p>{{ editorHelp }}</p>
				<p class="policy-workbench__precedence-hint">{{ t('libresign', 'Priority: User overrides Group, which overrides Default') }}</p>
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
					@update:modelValue="$emit('update-value', $event)" />
			</div>

			<NcCheckboxRadioSwitch
				v-if="editorDraft.scope !== 'user' && showAllowOverrideSwitch"
				type="switch"
				:model-value="editorDraft.allowChildOverride"
				:disabled="saveStatus === 'saving'"
				@update:modelValue="$emit('update-allow-override', $event)">
				<div class="policy-workbench__switch-copy">
					<span>{{ t('libresign', 'Require this signing order') }}</span>
					<p>
						{{ editorDraft.allowChildOverride ? t('libresign', 'All users must follow this signing order.') : t('libresign', 'Users can choose their preferred signing order.') }}
					</p>
				</div>
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="duplicateMessage" type="error">
				{{ duplicateMessage }}
			</NcNoteCard>

			<div class="policy-workbench__editor-actions" :class="{ 'policy-workbench__editor-actions--sticky-mobile': stickyActions }">
				<NcButton v-if="showBackButton" variant="tertiary" :aria-label="t('libresign', 'Go back to rule type selection')" :disabled="saveStatus === 'saving'" @click="$emit('back')">
					{{ t('libresign', '← Back') }}
				</NcButton>
				<NcButton variant="primary" :aria-label="editorMode === 'edit' ? t('libresign', 'Save policy rule changes') : t('libresign', 'Create policy rule')" :disabled="!canSaveDraft || saveStatus === 'saving'" @click="$emit('save')">
					{{ editorMode === 'edit' ? t('libresign', 'Save changes') : t('libresign', 'Create rule') }}
				</NcButton>
				<NcButton variant="secondary" :aria-label="t('libresign', 'Cancel editing')" :disabled="saveStatus === 'saving'" @click="$emit('cancel')">
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
</template>

<script setup lang="ts">
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

withDefaults(defineProps<{
	editorDraft: EditorDraft
	editorMode: 'create' | 'edit' | null
	editorTitle: string
	editorHelp: string
	activeEditor: unknown
	selectedTargetOptions: TargetOption[]
	availableTargets: TargetOption[]
	loadingTargets: boolean
	duplicateMessage: string | null
	canSaveDraft: boolean
	saveStatus: 'idle' | 'saving' | 'saved'
	stickyActions?: boolean
	showBackButton?: boolean
	showAllowOverrideSwitch?: boolean
}>(), {
	stickyActions: false,
	showBackButton: false,
	showAllowOverrideSwitch: true,
})

defineEmits<{
	(e: 'search-targets', value: string): void
	(e: 'update-targets', value: { id: string } | Array<{ id: string }> | null): void
	(e: 'update-value', value: EffectivePolicyValue): void
	(e: 'update-allow-override', value: boolean): void
	(e: 'back'): void
	(e: 'save'): void
	(e: 'cancel'): void
}>()
</script>

<style scoped lang="scss">
.policy-workbench__editor-panel {
	padding: 0 !important;
	border: none !important;
	border-radius: 0 !important;
	background: transparent !important;
	box-shadow: none !important;
}

.policy-workbench__editor-panel-content {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;
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
