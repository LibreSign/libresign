<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<tr>
		<td class="files-list__row-name">
			<FileEntryPreview :source="source" />
		</td>
	</tr>
</template>

<script>
import FileEntryPreview from './FileEntryPreview.vue'
export default {
	name: 'FileEntryGrid',
	components: {
		FileEntryPreview,
	},
	props: {
		source: {
			type: Object,
			required: true,
		},
	},
}
</script>
