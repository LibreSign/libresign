<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<tr>
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
	</tr>
</template>

<script>
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'
import FileEntryName from './FileEntryName.vue'
import FileEntryPreview from './FileEntryPreview.vue'
export default {
	name: 'FileEntryGrid',
	components: {
		NcDateTime,
		FileEntryName,
		FileEntryPreview,
	},
	props: {
		source: {
			type: Object,
			required: true,
		},
	},
	computed: {
		mtime() {
			return Date.parse(this?.source?.request_date)
		},
		mtimeOpacity() {
			const maxOpacityTime = 31 * 24 * 60 * 60 * 1000 // 31 days

			const mtime = this.mtime?.getTime?.()
			if (!mtime) {
				return {}
			}

			// 1 = today, 0 = 31 days ago
			const ratio = Math.round(Math.min(100, 100 * (maxOpacityTime - (Date.now() - mtime)) / maxOpacityTime))
			if (ratio < 0) {
				return {}
			}
			return {
				color: `color-mix(in srgb, var(--color-main-text) ${ratio}%, var(--color-text-maxcontrast))`,
			}
		},
	}
}
</script>
