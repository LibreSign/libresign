<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- Rename input -->
	<form
		v-if="isRenaming"
		ref="renameForm"
		class="files-list__row-rename"
		@submit.prevent.stop="onRename"
		@click.stop>
		<NcTextField
			ref="renameInput"
			v-model="newName"
			:label="t('libresign', 'File name')"
			:autofocus="true"
			:minlength="1"
			:maxlength="255"
			:required="true"
			enterkeyhint="done"
			@keyup.esc="stopRenaming"
			@blur="onRename" />
	</form>

	<!-- Display name -->
	<component v-else
		:is="linkTo.is"
		ref="basename"
		class="files-list__row-name-link"
		v-bind="linkTo.params"
		dir="auto">
		<!-- Filename -->
		<span class="files-list__row-name-text">
			<!-- Keep the filename stuck to the extension to avoid whitespace rendering issues-->
			<span class="files-list__row-name-" v-text="basename" />
			<span class="files-list__row-name-ext" v-text="extension" />
		</span>
	</component>
</template>

<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import { computed, nextTick, ref, watch } from 'vue'

import NcTextField from '@nextcloud/vue/components/NcTextField'

defineOptions({
	name: 'FileEntryName',
})

type RenameInputRef = {
	$el?: {
		querySelector: (selector: string) => HTMLInputElement | null
	}
}

const props = defineProps<{
	basename: string
	extension: string
}>()

const emit = defineEmits<{
	rename: [name: string]
	'update:basename': [name: string]
	renaming: [value: boolean]
}>()

const renameInput = ref<RenameInputRef | null>(null)
const isRenaming = ref(false)
const newName = ref('')

const linkTo = computed(() => ({
	is: 'button',
	params: {
		'aria-label': props.basename,
		title: props.basename,
		tabindex: '0',
	},
}))

watch(() => props.basename, (newValue) => {
	if (!isRenaming.value) {
		newName.value = newValue
	}
})

async function startRenaming() {
	isRenaming.value = true
	emit('renaming', true)
	newName.value = props.basename
	await nextTick()
	const input = renameInput.value?.$el?.querySelector('input')
	if (input) {
		input.focus()
		input.setSelectionRange(0, props.basename.length)
	}
}

function stopRenaming() {
	isRenaming.value = false
	emit('renaming', false)
	newName.value = ''
}

async function onRename() {
	const trimmedName = newName.value.trim()

	if (!trimmedName || trimmedName.length < 1) {
		stopRenaming()
		return
	}

	if (trimmedName === props.basename) {
		stopRenaming()
		return
	}

	emit('rename', trimmedName)
}

defineExpose({
	isRenaming,
	newName,
	linkTo,
	startRenaming,
	stopRenaming,
	onRename,
	renameInput,
})
</script>

<style scoped lang="scss">
button.files-list__row-name-link {
	background-color: unset;
	border: none;
	font-weight: normal;

	&:active {
		// No active styles - handled by the row entry
		background-color: unset !important;
	}
}

.files-list__row-rename {
	display: contents;

	:deep(input) {
		padding: 4px 8px;
		border: 2px solid var(--color-primary-element);
		border-radius: var(--border-radius-large);
	}
}
</style>
