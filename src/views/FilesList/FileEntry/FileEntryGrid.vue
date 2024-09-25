<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<tr
		class="files-list__row"
		@contextmenu="onRightClick">
		<td class="files-list__row-name">
			<FileEntryPreview :source="source" />
			<FileEntryName ref="name"
				:basename="source.name"
				:extension="'.pdf'" />
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

import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import FileEntryMixin from './FileEntryMixin.js'

export default {
	name: 'FileEntryGrid',
	components: {
		NcDateTime,
		FileEntryActions,
		FileEntryName,
		FileEntryPreview,
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
