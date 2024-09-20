<template>
	<div class="file-list-filters">
		<div class="file-list-filters__filter">
			<div class="file-list-filters__filter">
				<FileListFilterModified />
				<FileListFilterStatus />
			</div>
		</div>
		<ul v-if="filtersStore.activeChips.length > 0" class="file-list-filters__active" :aria-label="t('libresign', 'Active filters')">
			<li v-for="(chip, index) of filtersStore.activeChips" :key="index">
				<NcChip :aria-label-close="t('libresign', 'Remove filter')"
					:icon-svg="chip.icon"
					:text="chip.text"
					@close="chip.onclick">
					<template v-if="chip.user" #icon>
						<NcAvatar disable-menu
							:show-user-status="false"
							:size="24"
							:user="chip.user" />
					</template>
				</NcChip>
			</li>
		</ul>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import FileListFilterModified from './FileListFilter/FileListFilterModified.vue'
import FileListFilterStatus from './FileListFilter/FileListFilterStatus.vue'
import { useFiltersStore } from '../../store/filters.js'

export default {
	name: 'FileListFilters',
	components: {
		NcChip,
		NcAvatar,
		FileListFilterModified,
		FileListFilterStatus,
	},
	setup() {
		const filtersStore = useFiltersStore()
		return { filtersStore }
	},
}
</script>

<style scoped lang="scss">
.file-list-filters {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	height: 100%;
	width: 100%;

	&__filter {
		display: flex;
		align-items: start;
		justify-content: start;
		gap: calc(var(--default-grid-baseline, 4px) * 2);

		> * {
			flex: 0 1 fit-content;
		}
	}

	&__active {
		display: flex;
		flex-direction: row;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
	}
}
</style>
