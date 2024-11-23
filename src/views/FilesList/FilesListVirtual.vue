<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VirtualList :data-component="userConfigStore.grid_view ? FileEntryGrid : FileEntry"
		:loading="loading">
		<template #filters>
			<FileListFilters />
		</template>
		<template v-if="!isNoneSelected" #header-overlay>
			<span class="files-list__selected">{{ n('libresign', '{count} selected', '{count} selected', selectionStore.selected.length, { count: selectionStore.selected.length }) }}</span>
			<FilesListTableHeaderActions />
		</template>
		<template #header>
			<!-- Table header and sort buttons -->
			<FilesListTableHeader ref="thead"
				:nodes="nodes" />
		</template>
		<template #footer>
			<FilesListTableFooter />
		</template>
	</VirtualList>
</template>

<script>
import FileEntry from './FileEntry/FileEntry.vue'
import FileEntryGrid from './FileEntry/FileEntryGrid.vue'
import FileListFilters from './FileListFilters.vue'
import FilesListTableFooter from './FilesListTableFooter.vue'
import FilesListTableHeader from './FilesListTableHeader.vue'
import FilesListTableHeaderActions from './FilesListTableHeaderActions.vue'
import VirtualList from './VirtualList.vue'

import { useFilesStore } from '../../store/files.js'
import { useSelectionStore } from '../../store/selection.js'
import { useUserConfigStore } from '../../store/userconfig.js'

export default {
	name: 'FilesListVirtual',
	components: {
		VirtualList,
		FileListFilters,
		FilesListTableHeader,
		FilesListTableHeaderActions,
		FilesListTableFooter,
		// eslint-disable-next-line vue/no-unused-components
		FileEntry,
		// eslint-disable-next-line vue/no-unused-components
		FileEntryGrid,
	},
	props: {
		nodes: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const selectionStore = useSelectionStore()
		const userConfigStore = useUserConfigStore()
		return {
			filesStore,
			selectionStore,
			userConfigStore,
		}
	},
	data() {
		return {
			FileEntry,
			FileEntryGrid,
		}
	},
	computed: {
		isNoneSelected() {
			return this.selectionStore.selected.length === 0
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

	&:has(.file-list-filters__active) {
		--fixed-block-start-position: calc(var(--default-clickable-area) + var(--default-grid-baseline) + var(--clickable-area-small));
	}

	& :deep() {
		// Table head, body and footer
		tbody {
			will-change: padding;
			contain: layout paint style;
			display: flex;
			flex-direction: column;
			width: 100%;
			// Necessary for virtual scrolling absolute
			position: relative;

			/* Hover effect on tbody lines only */
			tr {
				contain: strict;
				&:hover,
				&:focus {
					background-color: var(--color-background-dark);
				}
			}
		}

		.files-list__selected {
			padding-inline-end: 12px;
			white-space: nowrap;
		}

		.files-list__table {
			display: block;

			&.files-list__table--with-thead-overlay {
				// Hide the table header below the overlay
				margin-block-start: calc(-1 * var(--row-height));
			}
		}

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

		.files-list__thead-overlay {
			// Pinned on top when scrolling
			position: sticky;
			top: var(--fixed-block-start-position);
			// Save space for a row checkbox
			margin-inline-start: var(--row-height);
			// More than .files-list__thead
			z-index: 20;

			display: flex;
			align-items: center;

			// Reuse row styles
			background-color: var(--color-main-background);
			border-block-end: 1px solid var(--color-border);
			height: var(--row-height);
		}

		.files-list__thead,
		.files-list__tfoot {
			display: flex;
			flex-direction: column;
			width: 100%;
			background-color: var(--color-main-background);

		}

		// Table header
		.files-list__thead {
			// Pinned on top when scrolling
			position: sticky;
			z-index: 10;
			top: var(--fixed-block-start-position);
		}

		tr {
			position: relative;
			display: flex;
			align-items: center;
			width: 100%;
			user-select: none;
			border-block-end: 1px solid var(--color-border);
			box-sizing: border-box;
			user-select: none;
			height: var(--row-height);
		}

		td, th {
			display: flex;
			align-items: center;
			flex: 0 0 auto;
			justify-content: start;
			width: var(--row-height);
			height: var(--row-height);
			margin: 0;
			padding: 0;
			color: var(--color-text-maxcontrast);
			border: none;

			// Columns should try to add any text
			// node wrapped in a span. That should help
			// with the ellipsis on overflow.
			span {
				overflow: hidden;
				white-space: nowrap;
				text-overflow: ellipsis;
			}
		}

		.files-list__row-checkbox {
			justify-content: center;

			.checkbox-radio-switch {
				display: flex;
				justify-content: center;

				--icon-size: var(--checkbox-size);

				label.checkbox-radio-switch__label {
					width: var(--clickable-area);
					height: var(--clickable-area);
					margin: 0;
					padding: calc((var(--clickable-area) - var(--checkbox-size)) / 2);
				}

				.checkbox-radio-switch__icon {
					margin: 0 !important;
				}
			}
		}

		.files-list__row {
			&:hover, &:focus, &:active, &--active, &--dragover {
				// WCAG AA compliant
				background-color: var(--color-background-hover);
				// text-maxcontrast have been designed to pass WCAG AA over
				// a white background, we need to adjust then.
				--color-text-maxcontrast: var(--color-main-text);
				> * {
					--color-border: var(--color-border-dark);
				}

				// Hover state of the row should also change the favorite markers background
				.favorite-marker-icon svg path {
					stroke: var(--color-background-hover);
				}
			}

			&--dragover * {
				// Prevent dropping on row children
				pointer-events: none;
			}
		}

		// Entry preview or mime icon
		.files-list__row-icon {
			position: relative;
			display: flex;
			overflow: visible;
			align-items: center;
			// No shrinking or growing allowed
			flex: 0 0 var(--icon-preview-size);
			justify-content: center;
			width: var(--icon-preview-size);
			height: 100%;
			// Show same padding as the checkbox right padding for visual balance
			margin-inline-end: var(--checkbox-padding);
			color: var(--color-primary-element);

			// Icon is also clickable
			* {
				cursor: pointer;
			}

			& > span {
				justify-content: flex-start;

				&:not(.files-list__row-icon-favorite) svg {
					width: var(--icon-preview-size);
					height: var(--icon-preview-size);
				}

				// Slightly increase the size of the folder icon
				&.folder-icon,
				&.folder-open-icon {
					margin: -3px;
					svg {
						width: calc(var(--icon-preview-size) + 6px);
						height: calc(var(--icon-preview-size) + 6px);
					}
				}
			}

			&-preview-container {
				position: relative; // Needed for the blurshash to be positioned correctly
				overflow: hidden;
				width: var(--icon-preview-size);
				height: var(--icon-preview-size);
				border-radius: var(--border-radius);
			}

			&-blurhash {
				position: absolute;
				inset-block-start: 0;
				inset-inline-start: 0;
				height: 100%;
				width: 100%;
				object-fit: cover;
			}

			&-preview {
				// Center and contain the preview
				object-fit: contain;
				object-position: center;

				height: 100%;
				width: 100%;

				/* Preview not loaded animation effect */
				&:not(.files-list__row-icon-preview--loaded) {
					background: var(--color-loading-dark);
					// animation: preview-gradient-fade 1.2s ease-in-out infinite;
				}
			}

			&-favorite {
				position: absolute;
				top: 0px;
				inset-inline-end: -10px;
			}

			// File and folder overlay
			&-overlay {
				position: absolute;
				max-height: calc(var(--icon-preview-size) * 0.5);
				max-width: calc(var(--icon-preview-size) * 0.5);
				color: var(--color-primary-element-text);
				// better alignment with the folder icon
				margin-block-start: 2px;

				// Improve icon contrast with a background for files
				&--file {
					color: var(--color-main-text);
					background: var(--color-main-background);
					border-radius: 100%;
				}
			}
		}

		// Entry link
		.files-list__row-name {
			// Prevent link from overflowing
			overflow: hidden;
			// Take as much space as possible
			flex: 1 1 auto;

			button.files-list__row-name-link {
				display: flex;
				align-items: center;
				text-align: start;
				// Fill cell height and width
				width: 100%;
				height: 100%;
				// Necessary for flex grow to work
				min-width: 0;
				margin: 0;
				padding: 0;

				// Already added to the inner text, see rule below
				&:focus-visible {
					outline: none !important;
				}

				// Keyboard indicator a11y
				&:focus .files-list__row-name-text {
					outline: var(--border-width-input-focused) solid var(--color-main-text) !important;
					border-radius: var(--border-radius-element);
				}
				&:focus:not(:focus-visible) .files-list__row-name-text {
					outline: none !important;
				}
			}

			.files-list__row-name-text {
				color: var(--color-main-text);
				// Make some space for the outline
				padding: var(--default-grid-baseline) calc(2 * var(--default-grid-baseline));
				padding-inline-start: -10px;
				// Align two name and ext
				display: inline-flex;
			}

			.files-list__row-name-ext {
				color: var(--color-text-maxcontrast);
				// always show the extension
				overflow: visible;
			}
		}

		.files-list__row-actions {
			// take as much space as necessary
			width: auto;

			// Add margin to all cells after the actions
			& ~ td,
			& ~ th {
				margin: 0 var(--cell-margin);
			}

			button {
				.button-vue__text {
					// Remove bold from default button styling
					font-weight: normal;
				}
			}
		}

		.files-list__row-mtime,
		.files-list__row-status {
			color: var(--color-text-maxcontrast);
		}
		.files-list__row-status {
			width: calc(var(--row-height) * 1.5);
			// Right align content/text
			justify-content: flex-end;
		}

		.files-list__row-mtime {
			width: calc(var(--row-height) * 2);
		}

		.files-list__row-column-custom {
			width: calc(var(--row-height) * 2);
		}
	}

}

@media screen and (max-width: 512px) {
	.files-list :deep(.files-list__filters) {
		// Reduce padding on mobile
		padding-inline: var(--default-grid-baseline, 4px);
	}
}

</style>

<style lang="scss">
// Grid mode
tbody.files-list__tbody.files-list__tbody--grid {
	--half-clickable-area: calc(var(--clickable-area) / 2);
	--item-padding: 16px;
	--icon-preview-size: 166px;
	--name-height: 32px;
	--mtime-height: 16px;
	--row-width: calc(var(--icon-preview-size) + var(--item-padding) * 2);
	--row-height: calc(var(--icon-preview-size) + var(--name-height) + var(--mtime-height) + var(--item-padding) * 2);
	--checkbox-padding: 0px;

	display: grid;
	grid-template-columns: repeat(auto-fill, var(--row-width));

	align-content: center;
	align-items: center;
	justify-content: space-around;
	justify-items: center;

	tr {
		position: relative;
		display: flex;
		flex-direction: column;
		width: var(--row-width);
		height: var(--row-height);
		border: none;
		border-radius: var(--border-radius-large);
		padding: var(--item-padding);
	}

	// Checkbox in the top left
	.files-list__row-checkbox {
		position: absolute;
		z-index: 9;
		top: calc(var(--item-padding)/2);
		inset-inline-start: calc(var(--item-padding)/2);
		overflow: hidden;
		--checkbox-container-size: 44px;
		width: var(--checkbox-container-size);
		height: var(--checkbox-container-size);

		// Add a background to the checkbox so we do not see the image through it.
		.checkbox-radio-switch__content::after {
			content: '';
			width: 16px;
			height: 16px;
			position: absolute;
			inset-inline-start: 50%;
			margin-inline-start: -8px;
			z-index: -1;
			background: var(--color-main-background);
		}
	}

	// Star icon in the top right
	.files-list__row-icon-favorite {
		position: absolute;
		top: 0;
		inset-inline-end: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: var(--clickable-area);
		height: var(--clickable-area);
	}

	.files-list__row-name {
		display: flex;
		flex-direction: column;
		width: var(--icon-preview-size);
		height: calc(var(--icon-preview-size) + var(--name-height));
		// Ensure that the name outline is visible.
		overflow: visible;

		span.files-list__row-icon {
			width: var(--icon-preview-size);
			height: var(--icon-preview-size);
		}

		.files-list__row-name-text {
			margin: 0;
			// Ensure that the outline is not too close to the text.
			margin-inline-start: -4px;
			padding: 0px 4px;
		}
	}

	.files-list__row-status {
		position: absolute;
		top: var(--item-padding);
		inset-inline-end: var(--item-padding);
		width: var(--clickable-area);
		height: var(--clickable-area);
	}

	.files-list__row-mtime {
		width: var(--icon-preview-size);
		height: var(--mtime-height);
		font-size: calc(var(--default-font-size) - 4px);
	}

	.files-list__row-actions {
		position: absolute;
		inset-inline-end: calc(var(--half-clickable-area) / 2);
		inset-block-end: calc(var(--mtime-height) / 2);
		width: var(--clickable-area);
		height: var(--clickable-area);
	}
}
</style>
