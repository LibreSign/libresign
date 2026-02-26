<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- Rename input -->
	<form
		v-if="isRenaming"
		ref="renameForm"
		class="files-list__row-rename"
		@submit.prevent.stop="onRename"
		@click.stop>
		<NcTextField
			ref="renameInput"
			v-model="newName"
			:label="t('libresign', 'File name')"
			:autofocus="true"
			:minlength="1"
			:maxlength="255"
			:required="true"
			enterkeyhint="done"
			@keyup.esc="stopRenaming"
			@blur="onRename" />
	</form>

	<!-- Display name -->
	<component v-else
		:is="linkTo.is"
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

import { t } from '@nextcloud/l10n'

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

	emits: ['rename', 'update:basename', 'renaming'],

	setup() {
		return {
			t,
		}
	},

	data() {
		return {
			isRenaming: false,
			newName: '',
		}
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
	watch: {
		basename(newVal) {
			if (!this.isRenaming) {
				this.newName = newVal
			}
		},
	},
	methods: {
		startRenaming() {
			this.isRenaming = true
			this.$emit('renaming', true)
			this.newName = this.basename
			this.$nextTick(() => {
				const input = this.$refs.renameInput?.$el?.querySelector('input')
				if (input) {
					input.focus()
					input.setSelectionRange(0, this.basename.length)
				}
			})
		},

		stopRenaming() {
			this.isRenaming = false
			this.$emit('renaming', false)
			this.newName = ''
		},

		async onRename() {
			const trimmedName = this.newName.trim()

			if (!trimmedName || trimmedName.length < 1) {
				this.stopRenaming()
				return
			}

			if (trimmedName === this.basename) {
				this.stopRenaming()
				return
			}

			this.$emit('rename', trimmedName)
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

.files-list__row-rename {
	display: contents;

	:deep(input) {
		padding: 4px 8px;
		border: 2px solid var(--color-primary-element);
		border-radius: var(--border-radius-large);
	}
}
</style>
