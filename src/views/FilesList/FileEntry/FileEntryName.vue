<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<component :is="linkTo.is"
		ref="basename"
		class="files-list__row-name-link"
		v-bind="linkTo.params"
		dir="auto">
		<!-- Filename -->
		<span class="files-list__row-name-text">
			<!-- Keep the filename stuck to the extension to avoid whitespace rendering issues-->
			<span class="files-list__row-name-" v-text="basename" />
			<span class="files-list__row-name-ext" v-text="extension" />
		</span>
	</component>
</template>

<script>

import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'FileEntryName',

	components: {
		NcTextField,
	},

	props: {
		/**
		 * The filename without extension
		 */
		basename: {
			type: String,
			required: true,
		},
		/**
		 * The extension of the filename
		 */
		extension: {
			type: String,
			required: true,
		},
	},

	computed: {
		linkTo() {
			return {
				is: 'button',
				params: {
					'aria-label': this.basename,
					title: this.basename,
					tabindex: '0',
				},
			}
		},
	},
}
</script>

<style scoped lang="scss">
button.files-list__row-name-link {
	background-color: unset;
	border: none;
	font-weight: normal;

	&:active {
		// No active styles - handled by the row entry
		background-color: unset !important;
	}
}
</style>
