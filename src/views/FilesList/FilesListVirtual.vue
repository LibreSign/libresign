<template>
	<VirtualList>
		<template #filters>
			<FileListFilters />
		</template>
	</VirtualList>
</template>

<script>
import VirtualList from './VirtualList.vue'
import FileListFilters from './FileListFilters.vue'
export default {
	name: 'FilesListVirtual',
	components: {
		VirtualList,
		FileListFilters,
	},
	props: {
		nodes: {
			type: Array,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.files-list {
	--row-height: 55px;
	--cell-margin: 14px;

	--checkbox-padding: calc((var(--row-height) - var(--checkbox-size)) / 2);
	--checkbox-size: 24px;
	--clickable-area: var(--default-clickable-area);
	--icon-preview-size: 32px;

	--fixed-block-start-position: var(--default-clickable-area);

	overflow: auto;
	height: 100%;
	will-change: scroll-position;
	& :deep() {
		.files-list__filters {
			// Pinned on top when scrolling above table header
			position: sticky;
			top: 0;
			// ensure there is a background to hide the file list on scroll
			background-color: var(--color-main-background);
			z-index: 10;
			// fixed the size
			padding-inline: var(--row-height) var(--default-grid-baseline, 4px);
			height: var(--fixed-block-start-position);
			width: 100%;
		}
	}
}
</style>
