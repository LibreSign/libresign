<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<td class="files-list__row-actions">
		<!-- Menu actions -->
		<NcActions ref="actionsMenu"
			:force-name="true"
			type="tertiary"
			:open.sync="openedMenu"
			@close="openedMenu = null">
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
	</td>
</template>

<script>
import svgDelete from '@mdi/svg/svg/delete.svg?raw'
import svgSignature from '@mdi/svg/svg/signature.svg?raw'
import svgTextBoxCheck from '@mdi/svg/svg/text-box-check.svg?raw'

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
		NcCheckboxRadioSwitch,
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
		}
	},
	computed: {
		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === this.source.nodeId
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? this.source.nodeId : null
			},
		},
		visibleMenu() {
			return this.enabledMenuActions.filter(action => this.visibleIf(action))
		},
	},
	mounted() {
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
	},
	methods: {
		visibleIf(action) {
			const file = this.filesStore.files[this.source.nodeId]
			let visible = false
			if (action.id === 'sign') {
				visible = this.filesStore.canSign(file)
			} else if (action.id === 'validate') {
				visible = this.filesStore.canValidate(file)
			} else if (action.id === 'delete') {
				visible = this.filesStore.canDelete(file)
			}
			return visible
		},
		async onActionClick(action) {
			this.openedMenu = null
			this.sidebarStore.hideSidebar()
			if (action.id === 'sign') {
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
				this.signStore.setDocumentToSign(files[this.source.nodeId])
				this.$router.push({
					name: 'SignPDF',
					params: {
						uuid: signUuid,
					},
				})
				this.filesStore.selectFile(this.source.nodeId)
			} else if (action.id === 'validate') {
				this.$router.push({
					name: 'ValidationFile',
					params: {
						uuid: this.source.uuid,
					},
				})
			} else if (action.id === 'delete') {
				this.confirmDelete = true
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
	},
}
</script>

<style lang="scss">
// Allow right click to define the position of the menu
// only if defined
main.app-content[style*="mouse-pos-x"] .v-popper__popper {
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
