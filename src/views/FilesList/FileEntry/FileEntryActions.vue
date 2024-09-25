<template>
	<td class="files-list__row-actions">
		<!-- Menu actions -->
		<NcActions ref="actionsMenu"
			:force-name="true"
			type="tertiary"
			:open.sync="openedMenu"
			@close="openedSubmenu = null">
			<!-- Default actions list-->
			<NcActionButton v-for="action in enabledMenuActions"
				:key="action.id"
				:ref="`action-${action.id}`"
				:class="{
					[`files-list__row-action-${action.id}`]: true,
				}"
				:aria-label="action.title"
				:title="action.title">
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
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import { useActionsMenuStore } from '../../../store/actionsmenu.js'

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
		return { actionsMenuStore }
	},
	data() {
		return {
			openedSubmenu: null,
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
		this.registerAction({
			id: 'id-teste0',
			title: 'Validate',
			iconSvgInline: svgFile,
		})
		this.registerAction({
			id: 'id-teste1',
			title: 'Sign',
			iconSvgInline: svgFile,
		})
		this.registerAction({
			id: 'id-teste2',
			title: 'Delete',
			iconSvgInline: svgFile,
		})
	},
	methods: {
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
