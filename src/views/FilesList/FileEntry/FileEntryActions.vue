<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<td class="files-list__row-actions">
		<!-- Menu actions -->
		<NcActions ref="actionsMenu"
			:boundaries-element="boundariesElement"
			:container="boundariesElement"
			:force-name="true"
			type="tertiary"
			v-model:open="openedMenu"
			@close="openedMenu = null"
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

<script>
import { t } from '@nextcloud/l10n'

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

export default {
	name: 'FileEntryActions',
	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcLoadingIcon,
	},
	props: {
		opened: {
			type: Boolean,
			default: false,
		},
		source: {
			type: Object,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const signStore = useSignStore()
		return {
			t,
			actionsMenuStore,
			filesStore,
			sidebarStore,
			signStore,
		}
	},
	data() {
		return {
			enabledMenuActions: [],
			confirmDelete: false,
			deleteFile: true,
			deleting: false,
			document: {},
			hasInfo: false,
		}
	},
	emits: ['rename', 'start-rename'],
	computed: {
		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === this.source.id
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? this.source.id : null
			},
		},
		visibleMenu() {
			return this.enabledMenuActions.filter(action => this.visibleIf(action))
		},
		file() {
			return this.filesStore.files[this.source.id]
		},
		boundariesElement() {
			return document.querySelector('.app-content > .files-list')
				|| document.querySelector('.app-content')
				|| document.body
		},
	},
	mounted() {
		this.registerAction({
			id: 'request-signature',
			title: t('libresign', 'Request signature'),
			iconSvgInline: svgSignature,
		})
		this.registerAction({
			id: 'details',
			title: t('libresign', 'Details'),
			iconSvgInline: svgInformation,
		})
		this.registerAction({
			id: 'rename',
			title: t('libresign', 'Rename'),
			iconSvgInline: svgPencil,
		})
		this.registerAction({
			id: 'validate',
			title: t('libresign', 'Validate'),
			iconSvgInline: svgTextBoxCheck,
		})
		this.registerAction({
			id: 'sign',
			title: t('libresign', 'Sign'),
			iconSvgInline: svgSignature,
		})
		this.registerAction({
			id: 'delete',
			title: t('libresign', 'Delete'),
			iconSvgInline: svgDelete,
		})
		this.registerAction({
			id: 'open',
			title: t('libresign', 'Open file'),
			iconSvgInline: svgFileDocument,
		})
	},
	created() {
		this.document = loadState('libresign', 'file_info', {})
	},
	methods: {
		visibleIf(action) {
			let visible = false
			if (action.id === 'request-signature') {
				visible = (this.source?.signers?.length ?? 0) === 0
			} else if (action.id === 'details') {
				visible = (this.source?.signers?.length ?? 0) > 0
			} else if (action.id === 'rename') {
				visible = true
			} else if (action.id === 'sign') {
				visible = this.filesStore.canSign(this.file)
			} else if (action.id === 'validate') {
				visible = this.filesStore.canValidate(this.file)
			} else if (action.id === 'delete') {
				visible = this.filesStore.canDelete(this.file)
			} else if (action.id === 'open') {
				visible = this.source?.nodeType !== 'envelope'
					&& !this.filesStore.isOriginalFileDeleted(this.file)
			}
			return visible
		},
		async onActionClick(action) {
			this.openedMenu = null
			this.sidebarStore.hideSidebar()
			if (action.id === 'details' || action.id === 'request-signature') {
				this.filesStore.selectFile(this.source.id)
				this.sidebarStore.activeRequestSignatureTab()
			} else if (action.id === 'sign') {
				const signUuid = this.source.signers
					.reduce((accumulator, signer) => {
						if (signer.me) {
							return signer.sign_uuid
						}
						return accumulator
					}, '')
				const files = await this.filesStore.getAllFiles({
					signer_uuid: signUuid,
					force_fetch: true,
				})
				this.signStore.setFileToSign(files[this.source.id])
				this.$router.push({
					name: 'SignPDF',
					params: {
						uuid: signUuid,
	},
				})
				this.filesStore.selectFile(this.source.id)
				this.sidebarStore.activeRequestSignatureTab()
			} else if (action.id === 'validate') {
				this.$router.push({
					name: 'ValidationFile',
					params: {
						uuid: this.source.uuid,
	},
				})
			} else if (action.id === 'delete') {
				this.confirmDelete = true
			} else if (action.id === 'rename') {
				this.$emit('start-rename')
			} else if (action.id === 'open') {
				this.openFile()
			}
		},
		registerAction(action) {
			this.enabledMenuActions = [...this.enabledMenuActions, action]
		},
		async doDelete() {
			this.deleting = true
			await this.filesStore.delete(this.source, this.deleteFile)
			this.deleting = false
		},
		openFile() {
			const fileUrl = this.source.file
				|| generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: this.source.uuid })

			openDocument({
				fileUrl,
				filename: this.source.name,
				nodeId: this.source.nodeId,
			})
		},
		doRename(newName) {
			return this.filesStore.rename(this.source.uuid, newName)
		},
		onMenuClosed() {
			if (this.actionsMenuStore.opened === null) {
				const root = this.$el?.closest('.app-content')
				if (root) {
					root.style.removeProperty('--mouse-pos-x')
					root.style.removeProperty('--mouse-pos-y')
				}
			}
		},
	},
}
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
