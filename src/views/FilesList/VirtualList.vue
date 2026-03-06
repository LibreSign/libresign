<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="files-list"
		:class="{ 'files-list--grid': userConfigStore.files_list_grid_view }"
		data-cy-files-list>
		<div class="files-list__filters">
			<slot name="filters" />
		</div>

		<div v-if="$slots['header-overlay']" class="files-list__thead-overlay">
			<slot name="header-overlay" />
		</div>

		<div
			v-if="filesStore.filesSorted().length === 0"
			class="files-list__empty">
			<slot name="empty" />
		</div>

		<table
			:aria-hidden="filesStore.filesSorted().length === 0"
			class="files-list__table"
			:class="{
				'files-list__table--with-thead-overlay': !!$slots['header-overlay'],
				'files-list__table--hidden': filesStore.filesSorted().length === 0,
			}">
			<!-- Accessibility table caption for screen readers -->
			<caption v-if="caption" class="hidden-visually">
				{{ caption }}
			</caption>

			<!-- Header -->
			<thead ref="thead" class="files-list__thead" data-cy-files-list-thead>
				<slot name="header" />
			</thead>
			<!-- Body -->
			<tbody class="files-list__tbody"
				:class="userConfigStore.files_list_grid_view ? 'files-list__tbody--grid' : 'files-list__tbody--list'"
				data-cy-files-list-tbody>
				<component :is="dataComponent"
					v-for="(item) in filesStore.filesSorted()"
					:key="item.id"
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'

import debounce from 'debounce'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useFilesStore } from '../../store/files.js'
import { useUserConfigStore } from '../../store/userconfig.js'

defineOptions({
	name: 'VirtualList',
})

type FileItem = {
	id: string | number
}

type FilesStore = {
	loading: boolean
	filesSorted: () => FileItem[]
	getAllFiles: () => void
}

type UserConfigStore = {
	files_list_grid_view: boolean
}

const props = withDefaults(defineProps<{
	dataComponent: object | (() => unknown)
	loading: boolean
	caption?: string
}>(), {
	caption: '',
})

const filesStore = useFilesStore() as FilesStore
const userConfigStore = useUserConfigStore() as UserConfigStore
const endOfList = useTemplateRef<HTMLElement>('endOfList')
const observer = ref<IntersectionObserver | null>(null)

function getFilesIfNotLoading() {
	if (filesStore.loading) {
		setTimeout(getFilesIfNotLoading, 100)
	} else {
		filesStore.getAllFiles()
	}
}

function updateObserver() {
	const endOfListElement = endOfList.value
	if (!endOfListElement || !observer.value) {
		return
	}
	observer.value.disconnect()
	observer.value.observe(endOfListElement)
}

onMounted(() => {
	observer.value = new IntersectionObserver(debounce(([entry]) => {
		if (entry && entry.isIntersecting) {
			getFilesIfNotLoading()
		}
	}, 100))
	subscribe('libresign:files:updated', updateObserver)
})

onBeforeUnmount(() => {
	observer.value?.disconnect()
	unsubscribe('libresign:files:updated')
})

defineExpose({
	filesStore,
	userConfigStore,
	observer,
	endOfList,
	getFilesIfNotLoading,
	updateObserver,
	props,
	t,
})
</script>

<style scoped lang="scss">
.files-list {
	.hidden-visually {
		position: absolute;
		left: -10000px;
		top: auto;
		width: 1px;
		height: 1px;
		overflow: hidden;
	}

	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		height: 100%;
		min-height: 300px;
	}

	&__table {
		&--hidden {
			display: none;
		}
	}
}
</style>
