<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcPopover :boundary="boundary">
		<template #trigger>
			<NcButton :variant="isActive ? 'secondary' : 'tertiary'">
				<template #icon>
					<slot name="icon" />
				</template>
				{{ filterName }}
			</NcButton>
		</template>
		<template #default>
			<div class="file-list-filter__popover">
				<slot />
				<template v-if="isActive">
					<hr class="file-list-filter__separator">
					<NcButton class="file-list-filter__clear-button"
						alignment="start"
						variant="tertiary"
						wide
						@click="$emit('reset-filter')">
						{{ t('libresign', 'Clear filter') }}
					</NcButton>
				</template>
			</div>
		</template>
	</NcPopover>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

export default {
	name: 'FileListFilter',
	components: {
		NcButton,
		NcPopover,
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
	setup() {
		const boundary = document.getElementById('app-content-vue') ?? document.body
		return { t, boundary }
	},
}
</script>

<style scoped lang="scss">
.file-list-filter__popover {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) / 2);
	padding: calc(var(--default-grid-baseline) / 2);
	min-width: calc(7 * var(--default-clickable-area));
}

.file-list-filter__separator {
	margin: calc(var(--default-grid-baseline) / 2) 0;
	border: none;
	border-top: 1px solid var(--color-border);
}

.file-list-filter__clear-button {
	color: var(--color-error-text) !important;
}

:deep(.button-vue) {
	font-weight: normal !important;

	* {
		font-weight: normal !important;
	}
}
</style>
