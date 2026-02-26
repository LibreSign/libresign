<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="file-list-filters" data-test-id="files-list-filters">
		<!-- Wide: individual filter buttons shown inline -->
		<template v-if="isWide">
			<FileListFilterModified />
			<FileListFilterStatus />
		</template>

		<!-- Narrow: single collapsed button; turns primary-blue when filters are active -->
		<NcPopover v-else :boundary="boundary">
			<template #trigger>
				<NcButton :aria-label="t('libresign', 'Filters')"
					:pressed="hasActiveFilters"
					variant="tertiary">
					<template #icon>
						<NcIconSvgWrapper :path="mdiFilterVariant" />
					</template>
				</NcButton>
			</template>
			<template #default>
				<div class="file-list-filters__popover">
					<FileListFilterModified />
					<FileListFilterStatus />
				</div>
			</template>
		</NcPopover>
	</div>
</template>

<script>
import { computed } from 'vue'

import { mdiFilterVariant } from '@mdi/js'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import FileListFilterModified from './FileListFilter/FileListFilterModified.vue'
import FileListFilterStatus from './FileListFilter/FileListFilterStatus.vue'

import { useFileListWidth } from '../../composables/useFileListWidth.js'
import { useFiltersStore } from '../../store/filters.js'

export default {
	name: 'FileListFilters',
	components: {
		NcButton,
		NcIconSvgWrapper,
		NcPopover,
		FileListFilterModified,
		FileListFilterStatus,
	},
	setup() {
		const filtersStore = useFiltersStore()
		const { isWide } = useFileListWidth()
		const hasActiveFilters = computed(() => filtersStore.activeChips.length > 0)
		const boundary = document.getElementById('app-content-vue') ?? document.body

		return {
			t,
			mdiFilterVariant,
			isWide,
			hasActiveFilters,
			boundary,
		}
	},
}
</script>

<style scoped lang="scss">
.file-list-filters {
	display: flex;
	flex-direction: row;
	align-items: center;
	gap: var(--default-grid-baseline);
	height: 100%;

	&__popover {
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
		padding: calc(var(--default-grid-baseline) / 2);
		min-width: calc(7 * var(--default-clickable-area));
	}
}
</style>
