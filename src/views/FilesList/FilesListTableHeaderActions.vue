<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="files-list__column files-list__row-actions-batch" data-cy-files-list-selection-actions>
		<NcActions ref="actionsMenu"
			container="#app-content-vue"
			:disabled="!!loading || areFilesLoading"
			:force-name="true"
			:menu-name="null">
			<NcActionButton v-for="action in enabledMenuActions"
				:key="action.id"
				:aria-label="action.displayName(selectionStore.selected) + ' ' + t('libresign', '(selected)') /** TRANSLATORS: Selected like 'selected files' */"
				:class="'files-list__row-actions-batch-' + action.id"
				@click="onActionClick(action)">
				<template #icon>
					<NcLoadingIcon v-if="loading === action.id" :size="18" />
					<NcIconSvgWrapper v-else :svg="action.iconSvgInline(selectionStore.selected)" />
				</template>
				{{ action.displayName(selectionStore.selected) }}
			</NcActionButton>
		</NcActions>
		<NcDialog v-if="confirmDelete"
			:name="t('libresign', 'Confirm')"
			:no-close="deleting"
			:open.sync="confirmDelete">
			{{ t('libresign', 'The signature request will be deleted. Do you confirm this action?') }}
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="deleteFile"
				:disabled="deleting">
				{{ t('libresign', 'Also delete the file.') }}
			</NcCheckboxRadioSwitch>
			<template #actions>
				<NcButton variant="primary"
					:disabled="deleting"
					@click="doDelete()">
					<template #icon>
						<NcLoadingIcon v-if="deleting" :size="20" />
					</template>
					{{ t('libresign', 'Ok') }}
				</NcButton>
				<NcButton :disabled="deleting"
					@click="confirmDelete = false">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import svgDelete from '@mdi/svg/svg/delete.svg?raw'

import { showError, showSuccess } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import logger from '../../logger.js'
import { useFilesStore } from '../../store/files.js'
import { useSelectionStore } from '../../store/selection.js'

export default {
	name: 'FilesListTableHeaderActions',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcIconSvgWrapper,
		NcLoadingIcon,
	},

	setup() {
		const filesStore = useFilesStore()
		const selectionStore = useSelectionStore()

		return {
			filesStore,
			selectionStore,
		}
	},

	data() {
		return {
			enabledMenuActions: [],
			loading: null,
			toDelete: [],
			confirmDelete: false,
			deleteFile: true,
			deleting: false,
		}
	},
	computed: {
		areFilesLoading() {
			return this.filesStore.loading
		},
	},
	mounted() {
		this.registerAction({
			id: 'delete',
			displayName: () => t('libresign', 'Delete'),
			iconSvgInline: () => svgDelete,
			execBatch: (files) => {
				this.confirmDelete = true
				this.toDelete = files
				return files.map(() => (null))
			},
		})
	},
	methods: {
		registerAction(action) {
			this.enabledMenuActions = [...this.enabledMenuActions, action]
		},
		doDelete() {
			this.deleting = true
			this.filesStore.deleteMultiple(this.toDelete, this.deleteFile)
				.then(() => {
					this.toDelete = []
					this.selectionStore.reset()
					this.deleting = false
				})
		},
		async onActionClick(action) {
			const displayName = action.displayName(this.selectionStore.selected)
			const selectionSources = this.selectionStore.selected
			try {
				// Set loading markers
				this.loading = action.id
				this.changeLoadingStatusOfSelectedFiles('loading')

				// Dispatch action execution
				const results = await action.execBatch(this.selectionStore.selected)

				// Check if all actions returned null
				if (!results.some(result => result !== null)) {
					// If the actions returned null, we stay silent
					return
				}

				// Handle potential failures
				if (results.some(result => result === false)) {
					// Remove the failed ids from the selection
					const failedSources = selectionSources
						.filter((source, index) => results[index] === false)
					this.selectionStore.set(failedSources)

					if (results.some(result => result === null)) {
						// If some actions returned null, we assume that the dev
						// is handling the error messages and we stay silent
						return
					}

					showError(this.t('libresign', '"{displayName}" failed on some elements ', { displayName }))
					return
				}

				// Show success message and clear selection
				showSuccess(this.t('libresign', '"{displayName}" batch action executed successfully', { displayName }))
				this.selectionStore.reset()
			} catch (e) {
				logger.error('Error while executing action', { action, e })
				showError(this.t('libresign', '"{displayName}" action failed', { displayName }))
			} finally {
				// Remove loading markers
				this.loading = null
				this.changeLoadingStatusOfSelectedFiles()
			}
		},
		changeLoadingStatusOfSelectedFiles(status) {
			this.selectionStore.selected.forEach(nodeId => {
				this.filesStore.files[nodeId].loading = status
			})
		},
	},
}
</script>

<style scoped lang="scss">
.files-list__row-actions-batch {
	flex: 1 1 100% !important;
	max-width: 100%;
}
</style>
