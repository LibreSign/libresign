<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<td ref="rootElement" class="files-list__row-actions">
		<!-- Menu actions -->
		<NcActions ref="actionsMenu"
			:boundaries-element="boundariesElement"
			:container="boundariesElement"
			:force-name="true"
			variant="tertiary"
			v-model:open="openedMenu"
			@close="openedMenu = false"
			@closed="onMenuClosed">
			<!-- Default actions list-->
			<NcActionButton v-for="action in visibleMenu"
				:key="action.id"
				:ref="`action-${action.id}`"
				:class="{
					[`files-list__row-action-${action.id}`]: true,
				}"
				:aria-label="action.title"
				:title="action.title"
				@click="onActionClick(action)">
				<template #icon>
					<NcLoadingIcon v-if="loading === action.id" :size="18" />
					<NcIconSvgWrapper v-else :svg="action.iconSvgInline" />
				</template>
				{{ action.title }}
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
	</td>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import svgDelete from '@mdi/svg/svg/delete.svg?raw'
import svgFileDocument from '@mdi/svg/svg/file-document-outline.svg?raw'
import svgPencil from '@mdi/svg/svg/pencil-outline.svg?raw'
import svgSignature from '@mdi/svg/svg/signature.svg?raw'
import svgInformation from '@mdi/svg/svg/information-outline.svg?raw'
import svgTextBoxCheck from '@mdi/svg/svg/text-box-check.svg?raw'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import { openDocument } from '../../../utils/viewer.js'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import { useFilesStore } from '../../../store/files.js'
import { useSidebarStore } from '../../../store/sidebar.js'
import { useSignStore } from '../../../store/sign.js'

defineOptions({
	name: 'FileEntryActions',
})

type SourceSigner = {
	me?: boolean
	sign_uuid?: string
}

type SourceFile = {
	id: number
	uuid?: string
	name: string
	nodeId?: number
	nodeType?: string
	file?: string
	signers?: SourceSigner[]
}

type MenuAction = {
	id: string
	title: string
	iconSvgInline: string
}

const props = withDefaults(defineProps<{
	opened?: boolean
	source: SourceFile
	loading: boolean | string
}>(), {
	opened: false,
})

const emit = defineEmits<{
	(e: 'rename'): void
	(e: 'start-rename'): void
}>()

const router = useRouter()
const actionsMenuStore = useActionsMenuStore()
const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()
const signStore = useSignStore()

const rootElement = ref<HTMLElement | null>(null)
const enabledMenuActions = ref<MenuAction[]>([])
const confirmDelete = ref(false)
const deleteFile = ref(true)
const deleting = ref(false)
const documentData = ref(loadState('libresign', 'file_info', {}))
const hasInfo = ref(false)

const openedMenu = computed({
	get: () => actionsMenuStore.opened === props.source.id,
	set: (opened) => {
		actionsMenuStore.opened = opened ? props.source.id : null
	},
})

const visibleMenu = computed(() => enabledMenuActions.value.filter(action => visibleIf(action)))
const file = computed(() => filesStore.files[props.source.id])
const boundariesElement = computed(() => document.querySelector('.app-content > .files-list')
	|| document.querySelector('.app-content')
	|| document.body)

function registerAction(action: MenuAction) {
	enabledMenuActions.value = [...enabledMenuActions.value, action]
}

function visibleIf(action: Pick<MenuAction, 'id'>) {
	let visible = false
	if (action.id === 'request-signature') {
		visible = (props.source?.signers?.length ?? 0) === 0
	} else if (action.id === 'details') {
		visible = (props.source?.signers?.length ?? 0) > 0
	} else if (action.id === 'rename') {
		visible = true
	} else if (action.id === 'sign') {
		visible = filesStore.canSign(file.value)
	} else if (action.id === 'validate') {
		visible = filesStore.canValidate(file.value)
	} else if (action.id === 'delete') {
		visible = filesStore.canDelete(file.value)
	} else if (action.id === 'open') {
		visible = props.source?.nodeType !== 'envelope'
			&& !filesStore.isOriginalFileDeleted(file.value)
	}
	return visible
}

async function onActionClick(action: Pick<MenuAction, 'id'>) {
	openedMenu.value = false
	sidebarStore.hideSidebar()
	if (action.id === 'details' || action.id === 'request-signature') {
		filesStore.selectFile(props.source.id)
		sidebarStore.activeRequestSignatureTab()
	} else if (action.id === 'sign') {
		const signUuid = (props.source.signers ?? [])
			.reduce((accumulator, signer) => {
				if (signer.me) {
					return signer.sign_uuid ?? ''
				}
				return accumulator
			}, '')
		const files = await filesStore.getAllFiles({
			signer_uuid: signUuid,
			force_fetch: true,
		})
		signStore.setFileToSign(files[props.source.id])
		router.push({
			name: 'SignPDF',
			params: {
				uuid: signUuid,
			},
		})
		filesStore.selectFile(props.source.id)
		sidebarStore.activeRequestSignatureTab()
	} else if (action.id === 'validate') {
		if (!props.source.uuid) {
			return
		}
		router.push({
			name: 'ValidationFile',
			params: {
				uuid: props.source.uuid,
			},
		})
	} else if (action.id === 'delete') {
		confirmDelete.value = true
	} else if (action.id === 'rename') {
		emit('start-rename')
	} else if (action.id === 'open') {
		openFile()
	}
}

async function doDelete() {
	deleting.value = true
	await filesStore.delete(props.source, deleteFile.value)
	deleting.value = false
}

function openFile() {
	const fileUrl = props.source.file
		|| generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: props.source.uuid })

	openDocument({
		fileUrl,
		filename: props.source.name,
		nodeId: props.source.nodeId ?? 0,
	})
}

function doRename(newName: string) {
	if (!props.source.uuid) {
		return Promise.resolve()
	}
	return filesStore.rename(props.source.uuid, newName)
}

function onMenuClosed() {
	if (actionsMenuStore.opened === null) {
		const root = rootElement.value?.closest('.app-content') as HTMLElement | null
		if (root) {
			root.style.removeProperty('--mouse-pos-x')
			root.style.removeProperty('--mouse-pos-y')
		}
	}
}

onMounted(() => {
	registerAction({
		id: 'request-signature',
		title: t('libresign', 'Request signature'),
		iconSvgInline: svgSignature,
	})
	registerAction({
		id: 'details',
		title: t('libresign', 'Details'),
		iconSvgInline: svgInformation,
	})
	registerAction({
		id: 'rename',
		title: t('libresign', 'Rename'),
		iconSvgInline: svgPencil,
	})
	registerAction({
		id: 'validate',
		title: t('libresign', 'Validate'),
		iconSvgInline: svgTextBoxCheck,
	})
	registerAction({
		id: 'sign',
		title: t('libresign', 'Sign'),
		iconSvgInline: svgSignature,
	})
	registerAction({
		id: 'delete',
		title: t('libresign', 'Delete'),
		iconSvgInline: svgDelete,
	})
	registerAction({
		id: 'open',
		title: t('libresign', 'Open file'),
		iconSvgInline: svgFileDocument,
	})
	hasInfo.value = Object.keys(documentData.value as Record<string, unknown>).length > 0
})

defineExpose({
	enabledMenuActions,
	confirmDelete,
	deleteFile,
	deleting,
	documentData,
	hasInfo,
	visibleIf,
	onActionClick,
	registerAction,
	doDelete,
	openFile,
	doRename,
	onMenuClosed,
})
</script>

<style lang="scss">
// Allow right click to define the position of the menu
.app-content[style*="mouse-pos-x"] .v-popper__popper {
	transform: translate3d(var(--mouse-pos-x), var(--mouse-pos-y), 0px) !important;

	// If the menu is too close to the bottom, we move it up
	&[data-popper-placement="top"] {
		// 34px added to align with the top of the cursor
		transform: translate3d(var(--mouse-pos-x), calc(var(--mouse-pos-y) - 50vh + 34px), 0px) !important;
	}
	// Hide arrow if floating
	.v-popper__arrow-container {
		display: none;
	}
}
</style>

<style lang="scss" scoped>
:deep(.button-vue--icon-and-text, .files-list__row-action-sharing-status) {
	.button-vue__text {
		color: var(--color-primary-element);
	}
	.button-vue__icon {
		color: var(--color-primary-element);
	}
}
</style>
