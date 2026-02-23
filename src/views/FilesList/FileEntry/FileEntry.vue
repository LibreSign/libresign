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
			@rename="onRename"
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

<script>
import { t } from '@nextcloud/l10n'

import { showSuccess } from '@nextcloud/dialogs'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'

import FileEntryActions from './FileEntryActions.vue'
import FileEntryCheckbox from './FileEntryCheckbox.vue'
import FileEntryName from './FileEntryName.vue'
import FileEntryPreview from './FileEntryPreview.vue'
import FileEntrySigners from './FileEntrySigners.vue'
import FileEntryStatus from './FileEntryStatus.vue'

import FileEntryMixin from './FileEntryMixin.js'
import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import { useFilesStore } from '../../../store/files.js'

export default {
	name: 'FileEntry',
	components: {
		NcDateTime,
		FileEntryActions,
		FileEntryCheckbox,
		FileEntryName,
		FileEntryPreview,
		FileEntrySigners,
		FileEntryStatus,
	},
	mixins: [
		FileEntryMixin,
	],
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		const filesStore = useFilesStore()
		return {
			t,
			actionsMenuStore,
			filesStore,
		}
	},
	data() {
		return {
			isRenaming: false,
			renamingSaving: false,
		}
	},
	methods: {
		async onRename(newName) {
			const oldName = this.source.name
			this.renamingSaving = true
			try {
				await this.$refs.actions.doRename(newName)
				this.$refs.name?.stopRenaming?.()
				showSuccess(t('libresign', 'Renamed "{oldName}" to "{newName}"', {
					oldName,
					newName,
				}))
			} finally {
				this.renamingSaving = false
			}
		},
		onStartRename() {
			this.$refs.name?.startRenaming?.()
		},
		onFileRenaming(isRenaming) {
			this.isRenaming = isRenaming
		},
	},
}
</script>
