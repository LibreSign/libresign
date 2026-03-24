<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="policy-workbench__editor-panel">
		<div class="policy-workbench__editor-panel-content" :class="{ 'policy-workbench__editor-panel-content--saving': saveStatus === 'saving' }">
			<div class="policy-workbench__editor-header">
				<p class="policy-workbench__eyebrow">
					{{ editorMode === 'edit' ? t('libresign', 'Edit rule') : t('libresign', 'Create rule') }}
				</p>
				<h3>{{ editorTitle }}</h3>
				<p>{{ editorHelp }}</p>
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
				v-if="editorDraft.scope !== 'user'"
				type="switch"
				:model-value="editorDraft.allowChildOverride"
				:disabled="saveStatus === 'saving'"
				@update:modelValue="$emit('update-allow-override', $event)">
				<span>{{ t('libresign', 'Allow lower layers to override this rule') }}</span>
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="duplicateMessage" type="error">
				{{ duplicateMessage }}
			</NcNoteCard>

			<div class="policy-workbench__editor-actions" :class="{ 'policy-workbench__editor-actions--sticky-mobile': stickyActions }">
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
}>(), {
	stickyActions: false,
})

defineEmits<{
	(e: 'search-targets', value: string): void
	(e: 'update-targets', value: { id: string } | Array<{ id: string }> | null): void
	(e: 'update-value', value: EffectivePolicyValue): void
	(e: 'update-allow-override', value: boolean): void
	(e: 'save'): void
	(e: 'cancel'): void
}>()
</script>
