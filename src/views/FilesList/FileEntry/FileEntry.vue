<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<tr class="files-list__row"
		@contextmenu="onRightClick">
		<td class="files-list__row-name">
			<FileEntryPreview :source="source" />
			<FileEntryName ref="name"
				:basename="source.name"
				:extension="'.pdf'" />
		</td>

		<!-- Status -->
		<td class="files-list__row-status">
			<FileEntryStatus :status="source.status"
				:status-text="source.statusText" />
		</td>

		<!-- Mtime -->
		<td :style="mtimeOpacity"
			class="files-list__row-mtime">
			<NcDateTime v-if="source.request_date" :timestamp="mtime" :ignore-seconds="true" />
		</td>

		<!-- Actions -->
		<FileEntryActions ref="actions"
			:class="`files-list__row-actions-${source.nodeId}`"
			:opened.sync="openedMenu"
			:source="source"
			:loading="loading" />
	</tr>
</template>

<script>
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'

import FileEntryActions from './FileEntryActions.vue'
import FileEntryName from './FileEntryName.vue'
import FileEntryPreview from './FileEntryPreview.vue'
import FileEntryStatus from './FileEntryStatus.vue'

import FileEntryMixin from './FileEntryMixin.js'
import { useActionsMenuStore } from '../../../store/actionsmenu.js'

export default {
	name: 'FileEntry',
	components: {
		NcDateTime,
		FileEntryActions,
		FileEntryName,
		FileEntryPreview,
		FileEntryStatus,
	},
	mixins: [
		FileEntryMixin,
	],
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		return { actionsMenuStore }
	},
}
</script>
