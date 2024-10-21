<template>
	<td class="files-list__row-actions">
		<!-- Menu actions -->
		<NcActions ref="actionsMenu"
			:force-name="true"
			type="tertiary"
			:open.sync="openedMenu"
			@close="openedMenu = null">
			<!-- Default actions list-->
			<NcActionButton v-for="action in enabledMenuActions"
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
	</td>
</template>

<script>
import svgFile from '@mdi/svg/svg/file.svg?raw'
import svgSignature from '@mdi/svg/svg/signature.svg?raw'
import svgTextBoxCheck from '@mdi/svg/svg/text-box-check.svg?raw'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import { useFilesStore } from '../../../store/files.js'
import { useSidebarStore } from '../../../store/sidebar.js'
import { useSignStore } from '../../../store/sign.js'

export default {
	name: 'FileEntryActions',
	components: {
		NcActionButton,
		NcActions,
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
	},
	mounted() {
		if (this.filesStore.getFile(this.filesStore.files[this.source.nodeId])) {
			this.registerAction({
				id: 'validate',
				title: t('libresign', 'Validate'),
				iconSvgInline: svgTextBoxCheck,
			})
		}
		if (this.filesStore.canSign(this.filesStore.files[this.source.nodeId])) {
			this.registerAction({
				id: 'sign',
				title: t('libresign', 'Sign'),
				iconSvgInline: svgSignature,
			})
		}
		if (this.filesStore.canDelete(this.filesStore.files[this.source.nodeId])) {
			this.registerAction({
				id: 'delete',
				title: t('libresign', 'Delete'),
				iconSvgInline: svgFile,
			})
		}
	},
	methods: {
		async onActionClick(action) {
			const uuid = this.source.uuid
			this.openedMenu = null
			this.sidebarStore.hideSidebar()
			if (action.id === 'sign') {
				this.source.signers
					.reduce((accumulator, signer) => {
						if (signer.me) {
							return signer.sign_uuid
						}
						return accumulator
					}, '')
				this.signStore.setDocumentToSign(this.source)
				this.$router.push({ name: 'SignPDF', params: { uuid } })
				this.filesStore.selectFile(this.source.nodeId)
			} else if (action.id === 'validate') {
				this.$router.push({ name: 'ValidationFile', params: { uuid } })
			} else if (action.id === 'delete') {
				this.filesStore.delete(this.source)
			}
		},
		registerAction(action) {
			this.enabledMenuActions = [...this.enabledMenuActions, action]
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
