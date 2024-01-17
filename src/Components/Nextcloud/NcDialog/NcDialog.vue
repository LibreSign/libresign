<template>
	<NcModal v-if="open"
		v-bind="modalProps"
		class="modal-container__content"
		@close="handleClosed"
		@update:show="handleClosing">
		<!-- The dialog name / header -->
		<h2 :id="navigationId" class="dialog__name" v-text="name" />
		<div class="dialog" :class="dialogClasses">
			<div :class="['dialog__wrapper', { 'dialog__wrapper--collapsed': isNavigationCollapsed }]">
				<!-- When the navigation is collapsed (too small dialog) it is displayed above the main content, otherwise on the inline start -->
				<nav v-if="hasNavigation"
					class="dialog__navigation"
					:class="navigationClasses"
					:aria-labelledby="navigationId">
					<slot name="navigation" :is-collapsed="isNavigationCollapsed" />
				</nav>
				<!-- Main dialog content -->
				<div class="dialog__content" :class="contentClasses">
					<slot>
						<p class="dialog__text">
							{{ message }}
						</p>
					</slot>
				</div>
			</div>
			<!-- The dialog actions aka the buttons -->
			<div class="dialog__actions">
				<slot name="actions" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import { computed, defineComponent, ref } from 'vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

export default defineComponent({
	name: 'NcDialog',

	components: {
		NcModal,
	},

	props: {
		/** Name of the dialog (the heading) */
		name: {
			type: String,
			required: true,
		},

		/** Text of the dialog */
		message: {
			type: String,
			default: '',
		},

		/** Additional elements to add to the focus trap */
		additionalTrapElements: {
			type: Array,
			validator: (arr) => Array.isArray(arr) && arr.every((element) => typeof element === 'string'),
			default: () => ([]),
		},

		/**
		 * The element where to mount the dialog, if `null` is passed the dialog is mounted in place
		 * @default 'body'
		 */
		container: {
			type: String,
			required: false,
			default: 'body',
		},

		/**
		 * Whether the dialog should be shown
		 * @default true
		 */
		open: {
			type: Boolean,
			default: true,
		},

		/**
		 * Size of the underlying NcModal
		 * @default 'small'
		 * @type {'small'|'normal'|'large'|'full'}
		 */
		size: {
			type: String,
			required: false,
			default: 'small',
			validator: (value) => typeof value === 'string' && ['small', 'normal', 'large', 'full'].includes(value),
		},

		/**
		 * Buttons to display
		 * @default []
		 */
		buttons: {
			type: Array,
			required: false,
			default: () => ([]),
			validator: (value) => Array.isArray(value) && value.every((element) => typeof element === 'object'),
		},

		/**
		 * Set to false to no show a close button on the dialog
		 * @default true
		 */
		canClose: {
			type: Boolean,
			default: true,
		},

		/**
		 * Close the dialog if the user clicked outside of the dialog
		 * Only relevant if `canClose` is set to true.
		 */
		closeOnClickOutside: {
			type: Boolean,
			default: false,
		},

		/**
		 * Declare if hiding the modal should be animated
		 * @default false
		 */
		outTransition: {
			type: Boolean,
			default: false,
		},

		/**
		 * Optionally pass additionaly classes which will be set on the navigation for custom styling
		 * @default ''
		 * @example
		 * ```html
		 * <DialogBase :navigation-classes="['mydialog-navigation']"><!-- --></DialogBase>
		 * <!-- ... -->
		 * <style lang="scss">
		 * :deep(.mydialog-navigation) {
		 *     flex-direction: row-reverse;
		 * }
		 * </style>
		 * ```
		 */
		navigationClasses: {
			type: [String, Array, Object],
			required: false,
			default: '',
		},

		/**
		 * Optionally pass additionaly classes which will be set on the content wrapper for custom styling
		 * @default ''
		 */
		contentClasses: {
			type: [String, Array, Object],
			required: false,
			default: '',
		},

		/**
		 * Optionally pass additionaly classes which will be set on the dialog itself
		 * (the default `class` attribute will be set on the modal wrapper)
		 * @default ''
		 */
		dialogClasses: {
			type: [String, Array, Object],
			required: false,
			default: '',
		},
	},

	emits: ['closing', 'update:open'],

	setup(props, { emit, slots }) {
		/**
		 * We use the dialog width to decide if we collapse the navigation (flex direction row)
		 */
		const { width: dialogWidth } = { width: 900 }

		/**
		 * Whether the navigation is collapsed due to dialog and window size
		 * (collapses when modal is below: 900px modal width - 2x 12px margin)
		 */
		const isNavigationCollapsed = computed(() => dialogWidth.value < 876)

		/**
		 * Whether a navigation was passed and the element should be displayed
		 */
		const hasNavigation = computed(() => slots?.navigation !== undefined)

		/**
		 * The unique id of the nav element
		 */
		const navigationId = ref(Math.random()
			.toString(36)
			.replace(/[^a-z]+/g, '')
			.slice(0, 5),
		)

		/**
		 * If the underlaying modal is shown
		 */
		const showModal = ref(true)

		// Because NcModal does not emit `close` when show prop is changed
		/**
		 * Handle clicking a dialog button -> should close
		 */
		const handleButtonClose = () => {
			handleClosing()
			window.setTimeout(() => handleClosed(), 300)
		}

		/**
		 * Handle closing the dialog, optional out transition did not run yet
		 */
		const handleClosing = () => {
			showModal.value = false
			/**
			 * Emitted when the dialog is closing, so the out transition did not finish yet
			 */
			emit('closing')
		}

		/**
		 * Handle dialog closed (out transition finished)
		 */
		const handleClosed = () => {
			showModal.value = true
			/**
			 * Emitted then the dialog is fully closed and the out transition run
			 */
			emit('update:open', false)
		}

		/**
		 * Properties to pass to the underlying NcModal
		 */
		const modalProps = computed(() => ({
			canClose: props.canClose,
			container: props.container === undefined ? 'body' : props.container,
			// we do not pass the name as we already have the name as the headline
			// name: props.name,
			size: props.size,
			show: props.open && showModal.value,
			outTransition: props.outTransition,
			closeOnClickOutside: props.closeOnClickOutside,
			enableSlideshow: false,
			enableSwipe: false,
		}))

		return {
			handleButtonClose,
			handleClosing,
			handleClosed,
			hasNavigation,
			navigationId,
			isNavigationCollapsed,
			modalProps,
		}
	},
})
</script>

<style lang="scss">
/** When having the small dialog style we override the modal styling so dialogs look more dialog like */
@media only screen and (max-width: 1024px) {
	.dialog__modal .modal-wrapper--small .modal-container {
		width: fit-content;
		height: unset;
		max-height: 90%;
		position: relative;
		top: unset;
		border-radius: var(--border-radius-large);
	}
}
</style>

<style lang="scss" scoped>
.dialog {
	height: 100%;
	width: 100%;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	overflow: hidden;
	padding-block: 4px 8px;
	padding-inline: 12px 8px;

	&__modal {
		:deep(.modal-wrapper .modal-container) {
			display: flex !important;
			padding-block: 4px 8px; // 4px to align with close button, 8px block-end to allow the actions a margin of 4px for the focus visible outline
			padding-inline: 12px 8px; // Same as with padding-block, we need the actions to have a margin of 4px for the button outline
		}
		:deep(.modal-container__content) {
			display: flex;
			flex-direction: column;
		}
	}

	&__wrapper {
		display: flex;
		flex-direction: row;
		// Auto scale to fit
		flex: 1;
		min-height: 0;
		overflow: hidden;
		// see modal-container padding, this aligns with the padding-inline-start (8px + 4px = 12px)
		padding-inline-end: 4px;

		&--collapsed {
			flex-direction: column;
		}
	}

	&__navigation {
		display: flex;
		flex-shrink: 0;
	}

	// Navigation styling when side-by-side with content
	&__wrapper:not(&__wrapper--collapsed) &__navigation {
		flex-direction: column;

		overflow: hidden auto;
		height: 100%;
		min-width: 200px;
		margin-inline-end: 20px;
	}

	// Navigation styling when on top of content
	&__wrapper#{&}__wrapper--collapsed &__navigation {
		flex-direction: row;
		justify-content: space-between;

		overflow: auto hidden;
		width: 100%;
		min-width: 100%;
	}

	&__name {
		// Same as the NcAppSettingsDialog
		text-align: center;
		height: var(--default-clickable-area);
		min-height: var(--default-clickable-area);
		line-height: var(--default-clickable-area);
		margin-block-end: 12px;
		font-weight: bold;
		font-size: 20px;
		margin-bottom: 12px;
		color: var(--color-text-light);
	}

	&__content {
		// Auto fit
		flex: 1;
		min-height: 0;
		overflow: auto;
	}

	// In case only text content is show
	&__text {
		// Also add padding to the bottom to make it more readable
		padding-block-end: 6px;
	}

	&__actions {
		display: flex;
		gap: 6px;
		align-content: center;
		width: fit-content;
		margin-inline: auto 4px; // 4px to align with the overall modal padding, we need this here for the buttons to have their 4px focus-visible outline
		margin-block: 6px 4px; // 4px block-end see reason above
	}
}
</style>
