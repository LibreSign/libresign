<template>
	<div>
		<div ref="containerToolbar" class="toolbar" />
		<ckeditor v-if="ready"
			:value="value"
			:config="config"
			:editor="editor"
			class="editor"
			@input="onEditorInput"
			@ready="onEditorReady" />
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import CKEditor from '@ckeditor/ckeditor5-vue2'
import Editor from '@ckeditor/ckeditor5-editor-decoupled/src/decouplededitor.js'
import EssentialsPlugin from '@ckeditor/ckeditor5-essentials/src/essentials.js'
import ParagraphPlugin from '@ckeditor/ckeditor5-paragraph/src/paragraph.js'
import InsertVariablePlugin from '../../ckeditor/InsertVariablePlugin'
import { DropdownView } from '@ckeditor/ckeditor5-ui'
import { getLanguage } from '@nextcloud/l10n'
import logger from '../../logger.js'

export default {
	name: 'TextEditor',
	components: {
		ckeditor: CKEditor.component,
	},
	props: {
		value: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			ready: false,
			editor: Editor,
			config: {
				licenseKey: 'GPL',
				autoParagraph: false,
				plugins: [
					EssentialsPlugin,
					ParagraphPlugin,
					InsertVariablePlugin,
				],
				toolbar: {
					items: ['undo', 'redo', 'insertVariable'],
				},
				variaveis: loadState('libresign', 'signature_available_variables'),
				language: 'en',
			},
		}
	},
	watch: {
		value(val) {
			console.log('Value mudou:', val)
		}
	},
	beforeMount() {
		this.loadEditorTranslations(getLanguage())
	},
	mounted() {
		console.log('Editor carregado:', this.editor);
	},
	methods: {
		overrideDropdownPositionsToNorth(editor, toolbarView) {
			const {
				south, north, southEast, southWest, northEast, northWest,
				southMiddleEast, southMiddleWest, northMiddleEast, northMiddleWest,
			} = DropdownView.defaultPanelPositions

			let panelPositions

			if (editor.locale.uiLanguageDirection !== 'rtl') {
				panelPositions = [
					northEast, northWest, northMiddleEast, northMiddleWest, north,
					southEast, southWest, southMiddleEast, southMiddleWest, south,
				]
			} else {
				panelPositions = [
					northWest, northEast, northMiddleWest, northMiddleEast, north,
					southWest, southEast, southMiddleWest, southMiddleEast, south,
				]
			}

			for (const item of toolbarView.items) {
				if (!(item instanceof DropdownView)) {
					continue
				}

				item.on('change:isOpen', () => {
					if (!item.isOpen) {
						return
					}

					item.panelView.position = DropdownView._getOptimalPosition({
						element: item.panelView.element,
						target: item.buttonView.element,
						fitInViewport: true,
						positions: panelPositions,
					}).name
				})
			}
		},
		overrideTooltipPositions(toolbarView) {
			for (const item of toolbarView.items) {
				if (item.buttonView) {
					item.buttonView.tooltipPosition = 'n'
				} else if (item.tooltipPosition) {
					item.tooltipPosition = 'n'
				}
			}
		},
		async loadEditorTranslations(language) {
			if (language === 'en') {
				// The default, nothing to fetch
				return this.showEditor('en')
			}

			try {
				logger.debug(`loading ${language} translations for CKEditor`)
				await import(
					/* webpackMode: "lazy-once" */
					/* webpackPrefetch: true */
					/* webpackPreload: true */
					`@ckeditor/ckeditor5-build-decoupled-document/build/translations/${language}`
				)
				this.showEditor(language)
			} catch (error) {
				logger.error(`could not find CKEditor translations for "${language}"`, { error })
				this.showEditor('en')
			}
		},
		showEditor(language) {
			logger.debug(`using "${language}" as CKEditor language`)
			this.config.language = language

			this.ready = true
		},
		/**
		 * @param {module:core/editor/editor~Editor} editor editor the editor instance
		 */
		onEditorReady(editor) {
			logger.debug('TextEditor is ready', { editor })

			// https://ckeditor.com/docs/ckeditor5/latest/examples/builds-custom/bottom-toolbar-editor.html
			if (editor.ui) {
				this.$refs.containerToolbar.appendChild(editor.ui.view.toolbar.element)
				this.overrideDropdownPositionsToNorth(editor, editor.ui.view.toolbar)
				this.overrideTooltipPositions(editor.ui.view.toolbar)
			}

			this.editorInstance = editor
			editor.setData(this.value.replace(/\n/g, '<br />'))

			this.$emit('ready', editor)
		},
		onEditorInput(text) {
			if (text !== this.value) {
				logger.debug(`TextEditor input changed to <${text}>`)
				this.$emit('input', text)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.editor {
	width: 100%;
	min-height: 150px;
	height: calc(100% - 75px);
	overflow: scroll;
	margin-bottom: 10px;

	&.ck {
		border: none !important;
		box-shadow: none !important;
		padding: 0;
	}
}

:deep(a) {
	color: #07d;
}
:deep(p) {
	cursor: text;
	margin: 0 !important;
}
</style>

<style>
/*
Overwrite the default z-index for CKEditor
https://github.com/ckeditor/ckeditor5/issues/1142
 */
 .ck .ck-reset {
	background: var(--color-main-background) !important;
 }
/* Default ckeditor value of padding-inline-start, to overwrite the global styling from server */
.ck-content ul, .ck-content ol {
	padding-inline-start: 40px;
}
.ck-list__item {
	.ck-off {
		background:var(--color-main-background) !important;
	}
	.ck-on {
		background:var(--color-primary-element-light) !important;
	}
}
.custom-item-username {
	color: var(--color-main-text) !important;
 }
 .link-title{
	color: var(--color-main-text) !important;
	margin-left: var(--default-grid-baseline) !important;
 }
 .link-icon {
	width: 16px !important;
 }
 .custom-item {
	width : 100% !important;
	border-radius : 8px !important;
	padding : 4px 8px !important;
	display :block;
	background:var(--color-main-background)!important;
 }
 .custom-item:hover {
	background:var(--color-primary-element-light)!important;
 }
 .link-container{
	border-radius :8px !important;
	padding :4px 8px !important;
	display : block;
	width : 100% !important;
	background:var(--color-main-background)!important;
 }
 .link-container:hover {
	background:var(--color-primary-element-light)!important;
 }
:root {
	--ck-z-default: 10000;
	--ck-balloon-border-width:  0;
}
.ck.ck-toolbar.ck-rounded-corners {
	border-radius: var(--border-radius-large) !important;
}
.ck-rounded-corners .ck.ck-dropdown__panel, .ck.ck-dropdown__panel.ck-rounded-corners {
	border-radius: var(--border-radius-large) !important;
	overflow: hidden;
}

.ck.ck-button {
	border-radius: var(--border-radius-element) !important;
}
.ck-powered-by-balloon {
	display: none !important;
}
</style>
