<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="default-user-folder-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="customEnabled"
			@update:modelValue="onToggleCustom">
			{{ t('libresign', 'Customize default user folder') }}
		</NcCheckboxRadioSwitch>

		<NcTextField
			v-if="customEnabled"
			:model-value="folderName"
			:label="t('libresign', 'Folder name')"
			:placeholder="DEFAULT_USER_FOLDER"
			@update:modelValue="onFolderNameChange" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	DEFAULT_USER_FOLDER,
	isCustomDefaultUserFolder,
	normalizeDefaultUserFolder,
} from './model'

defineOptions({
	name: 'DefaultUserFolderRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const folderName = computed(() => normalizeDefaultUserFolder(props.modelValue))
const customEnabled = computed(() => isCustomDefaultUserFolder(props.modelValue))

function onToggleCustom(enabled: boolean): void {
	if (!enabled) {
		emit('update:modelValue', DEFAULT_USER_FOLDER)
		return
	}

	emit('update:modelValue', folderName.value)
}

function onFolderNameChange(nextValue: string | number): void {
	emit('update:modelValue', normalizeDefaultUserFolder(String(nextValue)))
}
</script>

<style scoped lang="scss">
.default-user-folder-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}
</style>
