<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="files-list__column files-list__row-actions-batch" data-cy-files-list-selection-actions>
		<NcActions ref="actionsMenu"
			container="#app-content-vue"
			:disabled="!!loading || areFilesLoading"
			:force-name="true">
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
			v-model:open="confirmDelete">
			{{ t('libresign', 'The signature request will be deleted. Do you confirm this action?') }}
			<NcCheckboxRadioSwitch type="switch"
				v-model="deleteFile"
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

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

defineOptions({
	name: 'FilesListTableHeaderActions',
})

type BatchActionResult = boolean | null
type BatchAction = {
	id: string
	displayName: (selection: number[]) => string
	iconSvgInline: (selection: number[]) => string
	execBatch: (selection: number[]) => Promise<BatchActionResult[]> | BatchActionResult[]
}

const filesStore = useFilesStore()
const selectionStore = useSelectionStore()

const enabledMenuActions = ref<BatchAction[]>([])
const loading = ref<string | null>(null)
const toDelete = ref<number[]>([])
const confirmDelete = ref(false)
const deleteFile = ref(true)
const deleting = ref(false)

const areFilesLoading = computed(() => filesStore.loading)

function registerAction(action: BatchAction) {
	enabledMenuActions.value = [...enabledMenuActions.value, action]
}

function doDelete() {
	deleting.value = true
	filesStore.deleteMultiple(toDelete.value, deleteFile.value)
		.then(() => {
			toDelete.value = []
			selectionStore.reset()
			deleting.value = false
		})
}

async function onActionClick(action: BatchAction) {
	const displayName = action.displayName(selectionStore.selected)
	const selectionSources = selectionStore.selected
	try {
		loading.value = action.id
		changeLoadingStatusOfSelectedFiles('loading')

		const results = await action.execBatch(selectionStore.selected)

		if (!results.some(result => result !== null)) {
			return
		}

		if (results.some(result => result === false)) {
			const failedSources = selectionSources
				.filter((source, index) => results[index] === false)
			selectionStore.set(failedSources)

			if (results.some(result => result === null)) {
				return
			}

			showError(t('libresign', '"{displayName}" failed on some elements ', { displayName }))
			return
		}

		showSuccess(t('libresign', '"{displayName}" batch action executed successfully', { displayName }))
		selectionStore.reset()
	} catch (e) {
		logger.error('Error while executing action', { action, e })
		showError(t('libresign', '"{displayName}" action failed', { displayName }))
	} finally {
		loading.value = null
		changeLoadingStatusOfSelectedFiles()
	}
}

function changeLoadingStatusOfSelectedFiles(status?: string) {
	selectionStore.selected.forEach((key: number) => {
		const file = filesStore.files[key]
		if (file) {
			file.loading = status
		}
	})
}

onMounted(() => {
	registerAction({
		id: 'delete',
		displayName: () => t('libresign', 'Delete'),
		iconSvgInline: () => svgDelete,
		execBatch: (files) => {
			confirmDelete.value = true
			toDelete.value = files
			return files.map(() => null)
		},
	})
})

defineExpose({
	enabledMenuActions,
	loading,
	toDelete,
	confirmDelete,
	deleteFile,
	deleting,
	registerAction,
	doDelete,
	onActionClick,
	changeLoadingStatusOfSelectedFiles,
})
</script>

<style scoped lang="scss">
.files-list__row-actions-batch {
	flex: 1 1 100% !important;
	max-width: 100%;
}
</style>
