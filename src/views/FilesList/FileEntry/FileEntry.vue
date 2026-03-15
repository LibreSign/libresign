<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr class="files-list__row"
		@contextmenu="onRightClick">
		<!-- Checkbox -->
		<FileEntryCheckbox :is-loading="filesStore.loading || renamingSaving"
			:source="source" />

		<td class="files-list__row-name"
			@click="openDetailsIfAvailable">
			<FileEntryPreview :source="source" />
			<FileEntryName ref="name"
				:basename="source.name"
				:extension="fileExtension"
				@rename="onRename"
				@renaming="onFileRenaming" />
		</td>

		<!-- Actions -->
		<FileEntryActions ref="actions"
			:class="`files-list__row-actions-${source.id}`"
			v-model:opened="openedMenu"
			:source="source"
			:loading="loading"
			@start-rename="onStartRename" />

		<!-- Status -->
		<td class="files-list__row-status"
			@click="openDetailsIfAvailable">
			<FileEntryStatus :status="source.status"
				:status-text="source.statusText"
				:signers="source.signers || []" />
		</td>

		<!-- Signers Count -->
		<td class="files-list__row-signers"
			@click="openDetailsIfAvailable">
			<FileEntrySigners :signers-count="source.signersCount || 0"
				:signers="source.signers || []" />
		</td>

		<!-- Mtime -->
		<td :style="mtimeOpacity"
			class="files-list__row-mtime"
			@click="openDetailsIfAvailable">
			<NcDateTime v-if="source.created_at" :timestamp="mtime" :ignore-seconds="true" />
		</td>
	</tr>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'

import { showSuccess } from '@nextcloud/dialogs'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'

import FileEntryActions from './FileEntryActions.vue'
import FileEntryCheckbox from './FileEntryCheckbox.vue'
import FileEntryName from './FileEntryName.vue'
import FileEntryPreview from './FileEntryPreview.vue'
import FileEntrySigners from './FileEntrySigners.vue'
import FileEntryStatus from './FileEntryStatus.vue'

import { useFileEntry, type FileEntrySource } from '../../../composables/useFileEntry.js'
import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import { useFilesStore } from '../../../store/files.js'

defineOptions({
	name: 'FileEntry',
})

type FileEntryActionsRef = {
	doRename: (newName: string) => Promise<void>
}

type FileEntryNameRef = {
	startRenaming?: () => void
	stopRenaming?: () => void
}

const props = defineProps<{
	source: FileEntrySource
	loading: boolean
}>()

const actionsMenuStore = useActionsMenuStore()
const filesStore = useFilesStore()
const { mtime, openedMenu, mtimeOpacity, fileExtension, onRightClick, openDetailsIfAvailable } = useFileEntry(props, {
	actionsMenuStore,
	filesStore,
})
const actions = ref<FileEntryActionsRef | null>(null)
const name = ref<FileEntryNameRef | null>(null)
const isRenaming = ref(false)
const renamingSaving = ref(false)

async function onRename(newName: string) {
	const oldName = props.source.name
	renamingSaving.value = true
	try {
		await actions.value?.doRename(newName)
		name.value?.stopRenaming?.()
		showSuccess(t('libresign', 'Renamed "{oldName}" to "{newName}"', {
			oldName,
			newName,
		}))
	} finally {
		renamingSaving.value = false
	}
}

function onStartRename() {
	name.value?.startRenaming?.()
}

function onFileRenaming(nextIsRenaming: boolean) {
	isRenaming.value = nextIsRenaming
}

defineExpose({
	actionsMenuStore,
	filesStore,
	mtime,
	openedMenu,
	mtimeOpacity,
	fileExtension,
	onRightClick,
	openDetailsIfAvailable,
	actions,
	name,
	isRenaming,
	renamingSaving,
	onRename,
	onStartRename,
	onFileRenaming,
})
</script>
