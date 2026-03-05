<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<ul v-if="activeChips.length > 0"
		class="file-list-filter-chips"
		:aria-label="t('libresign', 'Active filters')">
		<li v-for="(chip, index) of activeChips" :key="index">
			<NcChip :aria-label-close="t('libresign', 'Remove filter')"
				:icon-svg="chip.icon"
				:text="chip.text"
				@close="chip.onclick">
				<template v-if="chip.user" #icon>
					<NcAvatar disable-menu
						:verbose-status="false"
						:size="24"
						:user="chip.user" />
				</template>
			</NcChip>
		</li>
	</ul>
</template>

<script>
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcChip from '@nextcloud/vue/components/NcChip'

import { useFiltersStore } from '../../../store/filters.js'

export default {
	name: 'FileListFilterChips',
	components: {
		NcAvatar,
		NcChip,
	},
	setup() {
		const filtersStore = useFiltersStore()
		const activeChips = computed(() => filtersStore.activeChips)
		return {
			t,
			activeChips,
		}
	},
}
</script>

<style scoped lang="scss">
.file-list-filter-chips {
	display: flex;
	gap: var(--default-grid-baseline);
}
</style>
