<template>
	<div class="files-list">
		<div class="files-list__filters">
			<slot name="filters" />
		</div>

		<table class="files-list__table">
			<!-- Header -->
			<thead ref="thead" class="files-list__thead">
				<slot name="header" />
			</thead>
			<!-- Body -->
			<tbody class="files-list__tbody"
				:class="userConfigStore.grid_view ? 'files-list__tbody--grid' : 'files-list__tbody--list'"
				data-cy-files-list-tbody>
				<component :is="dataComponent"
					v-for="(item) in filesStore.files"
					:key="item.nodeId"
					:source="item"
					:loading="loading" />
			</tbody>
		</table>
	</div>
</template>

<script>
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
}
</script>
