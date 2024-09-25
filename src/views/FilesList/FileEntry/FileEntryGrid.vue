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

export default {
	name: 'FileEntryGrid',
	components: {
		NcDateTime,
		FileEntryActions,
		FileEntryName,
		FileEntryPreview,
	},
	props: {
		source: {
			type: Object,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		return { actionsMenuStore }
	},
	computed: {
		mtime() {
			return Date.parse(this?.source?.request_date)
		},

		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === this.source.nodeId
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? this.source.nodeId : null
			},
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
	},
	methods: {
		// Open the actions menu on right click
		onRightClick(event) {
			// If already opened, fallback to default browser
			if (this.openedMenu) {
				return
			}

			// Reset any right menu position potentially set
			const root = this.$el?.closest('main.app-content')
			root.style.removeProperty('--mouse-pos-x')
			root.style.removeProperty('--mouse-pos-y')

			this.actionsMenuStore.opened = this.source.nodeId

			// Prevent any browser defaults
			event.preventDefault()
			event.stopPropagation()
		},
	}
}
</script>
