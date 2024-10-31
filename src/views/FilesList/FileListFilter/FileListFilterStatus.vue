<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<FileListFilter class="file-list-filter-status"
		:is-active="isActive"
		:filter-name="t('libresign', 'Status')"
		@reset-filter="resetFilter">
		<template #icon>
			<NcIconSvgWrapper :path="mdiListStatus" />
		</template>
		<NcActionButton v-for="status of statusPresets"
			:key="status.id"
			type="checkbox"
			:model-value="selectedOptions.includes(status)"
			@click="toggleOption(status)">
			<template #icon>
				<NcIconSvgWrapper :svg="status.icon" />
			</template>
			{{ status.label }}
		</NcActionButton>
	</FileListFilter>
</template>

<script>
import { mdiListStatus } from '@mdi/js'
import svgFile from '@mdi/svg/svg/file.svg?raw'
import svgFractionOneHalf from '@mdi/svg/svg/fraction-one-half.svg?raw'
import svgSignatureFreehand from '@mdi/svg/svg/signature-freehand.svg?raw'
import svgSignature from '@mdi/svg/svg/signature.svg?raw'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

import FileListFilter from './FileListFilter.vue'

import { useFiltersStore } from '../../../store/filters.js'

const colorize = (svg, color) => {
	return svg.replace('<path ', `<path fill="${color}" `)
}

export default {
	name: 'FileListFilterStatus',
	components: {
		FileListFilter,
		NcActionButton,
		NcIconSvgWrapper,
	},
	setup() {
		const filtersStore = useFiltersStore()
		return {
			mdiListStatus,
			filtersStore,
		}
	},
	data() {
		return {
			selectedOptions: [],
			statusPresets: [
				{
					id: 0,
					icon: colorize(svgFile, '#E0E0E0'),
					label: t('libresign', 'draft'),
				},
				{
					id: 1,
					icon: colorize(svgSignature, '#B2E0B2'),
					label: t('libresign', 'available for signature'),
				},
				{
					id: 2,
					icon: colorize(svgFractionOneHalf, '#F0E68C'),
					label: t('libresign', 'partially signed'),
				},
				{
					id: 3,
					icon: colorize(svgSignatureFreehand, '#A0C4FF'),
					label: t('libresign', 'signed'),
				},
			],
		}
	},
	computed: {
		isActive() {
			return this.selectedOptions.length > 0
		},
	},
	watch: {
		selectedOptions(newValue, oldValue) {
			if (newValue.length === 0) {
				this.setPreset()
			} else {
				this.setPreset(newValue)
			}
		},
	},
	methods: {
		setPreset(presets) {
			const chips = []
			if (presets && presets.length > 0) {
				for (const preset of presets) {
					chips.push({
						id: preset.id,
						icon: preset.icon,
						text: preset.label,
						onclick: () => this.setPreset(presets.filter(({ id }) => id !== preset.id)),
					})
				}
			} else {
				this.resetFilter()
			}
			this.filtersStore.onFilterUpdateChips({ detail: chips, id: 'status' })
		},
		resetFilter() {
			if (this.selectedOptions.length > 0) {
				this.selectedOptions = []
			}
		},
		toggleOption(option) {
			const idx = this.selectedOptions.indexOf(option)
			if (idx !== -1) {
				this.selectedOptions.splice(idx, 1)
			} else {
				this.selectedOptions.push(option)
			}
		},
	},
}
</script>

<style>
.file-list-filter-status {
	max-width: 220px;
}
</style>
