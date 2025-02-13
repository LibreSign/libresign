<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcActions force-menu
		:type="isActive ? 'secondary' : 'tertiary'"
		:menu-name="filterName">
		<template #icon>
			<slot name="icon" />
		</template>
		<slot />

		<template v-if="isActive">
			<NcActionSeparator />
			<NcActionButton class="files-list-filter__clear-button"
				close-after-click
				@click="$emit('reset-filter')">
				{{ t('files', 'Clear filter') }}
			</NcActionButton>
		</template>
	</NcActions>
</template>

<script>
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'

export default {
	name: 'FileListFilter',
	components: {
		NcActions,
		NcActionButton,
		NcActionSeparator,
	},
	props: {
		isActive: {
			type: Boolean,
			required: true,
		},
		filterName: {
			type: String,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.files-list-filter__clear-button :deep(.action-button__text) {
	color: var(--color-error-text);
}

:deep(.button-vue) {
	font-weight: normal !important;

	* {
		font-weight: normal !important;
	}
}
</style>
