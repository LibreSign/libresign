<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="files-list">
		<div class="files-list__filters">
			<slot name="filters" />
		</div>

		<div v-if="!!$scopedSlots['header-overlay']" class="files-list__thead-overlay">
			<slot name="header-overlay" />
		</div>

		<table class="files-list__table" :class="{ 'files-list__table--with-thead-overlay': !!$scopedSlots['header-overlay'] }">
			<!-- Header -->
			<thead ref="thead" class="files-list__thead">
				<slot name="header" />
			</thead>
			<!-- Body -->
			<tbody class="files-list__tbody"
				:class="userConfigStore.grid_view ? 'files-list__tbody--grid' : 'files-list__tbody--list'"
				data-cy-files-list-tbody>
				<component :is="dataComponent"
					v-for="(item) in filesStore.filesSorted()"
					:key="item.nodeId"
					:source="item"
					:loading="loading" />
			</tbody>
			<tfoot ref="endOfList"
				class="files-list__tfoot"
				data-cy-files-list-tfoot>
				<slot name="footer" />
			</tfoot>
		</table>
	</div>
</template>

<script>
import debounce from 'debounce'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useFilesStore } from '../../store/files.js'
import { useUserConfigStore } from '../../store/userconfig.js'

export default {
	name: 'VirtualList',
	props: {
		dataComponent: {
			type: [Object, Function],
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()
		return {
			filesStore,
			userConfigStore,
		}
	},
	data() {
		return {
			observer: null,
		}
	},
	mounted() {
		this.observer = new IntersectionObserver(debounce(([entry]) => {
			if (entry && entry.isIntersecting) {
				this.getFilesIfNotLoading()
			}
		}, 100, false))
		subscribe('libresign:files:updated', this.updateObserver)
	},
	beforeDestroy() {
		this.observer.disconnect()
		unsubscribe('libresign:files:updated')
	},
	methods: {
		getFilesIfNotLoading() {
			if (this.filesStore.loading) {
				setTimeout(this.getFilesIfNotLoading, 100)
			} else {
				this.filesStore.getAllFiles()
			}
		},
		updateObserver() {
			const endOfListElement = this.$refs?.endOfList
			if (!endOfListElement) return
			this.observer.disconnect()
			this.observer.observe(endOfListElement)
		},
	},
}
</script>
