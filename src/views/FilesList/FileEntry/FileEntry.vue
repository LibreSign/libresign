<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr class="files-list__row"
		@contextmenu="onRightClick">
		<!-- Checkbox -->
		<FileEntryCheckbox :is-loading="filesStore.loading"
			:source="source" />

		<td class="files-list__row-name"
			@click="openDetailsIfAvailable">
			<FileEntryPreview :source="source" />
			<FileEntryName ref="name"
				:basename="source.name"
				:extension="'.pdf'" />
		</td>

		<!-- Actions -->
		<FileEntryActions ref="actions"
			:class="`files-list__row-actions-${source.nodeId}`"
			:opened.sync="openedMenu"
			:source="source"
			:loading="loading" />

		<!-- Status -->
		<td class="files-list__row-status"
			@click="openDetailsIfAvailable">
			<FileEntryStatus :status="source.status"
				:status-text="source.statusText" />
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
import NcDateTime from '@nextcloud/vue/components/NcDateTime'

import FileEntryActions from './FileEntryActions.vue'
import FileEntryCheckbox from './FileEntryCheckbox.vue'
import FileEntryName from './FileEntryName.vue'
import FileEntryPreview from './FileEntryPreview.vue'
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
		FileEntryStatus,
	},
	mixins: [
		FileEntryMixin,
	],
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		const filesStore = useFilesStore()
		return {
			actionsMenuStore,
			filesStore,
		}
	},
}
</script>
