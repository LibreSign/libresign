<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="markdown-editor" :style="{ '--markdown-editor-min-height': minHeight, '--markdown-editor-max-height': maxHeight }">
		<div v-if="label || description" class="markdown-editor__header">
			<div class="markdown-editor__heading">
				<label v-if="label" :for="editorId" class="markdown-editor__label">
					{{ label }}
				</label>
				<p v-if="description" :id="descriptionId" class="markdown-editor__description">
					{{ description }}
				</p>
			</div>
		</div>
		<div class="markdown-editor__toolbar" :aria-label="t('libresign', 'Markdown formatting shortcuts')">
			<!-- Group 1: headings -->
			<HeadingMenu
				class="markdown-editor__headings-menu"
				@clear-heading="clearHeading"
				@apply-heading="applyHeading" />
			<span class="markdown-editor__toolbar-separator" aria-hidden="true" />
			<!-- Group 2: inline emphasis -->
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Bold')"
				@click="applyBold">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatBold" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Italic')"
				@click="applyItalic">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatItalic" :size="18" />
				</template>
			</NcButton>
			<span class="markdown-editor__toolbar-separator" aria-hidden="true" />
			<!-- Group 3: block elements -->
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Unordered list')"
				@click="applyUnorderedList">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatListBulleted" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Ordered list')"
				@click="applyOrderedList">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatListNumbered" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Blockquote')"
				@click="applyBlockquote">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatQuoteClose" :size="18" />
				</template>
			</NcButton>
			<span class="markdown-editor__toolbar-separator" aria-hidden="true" />
			<!-- Group 4: code and link -->
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Inline code')"
				@click="applyCode">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCodeTags" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Code block')"
				@click="applyCodeBlock">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCodeBraces" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Link')"
				@click="applyLink">
				<template #icon>
					<NcIconSvgWrapper :path="mdiLink" :size="18" />
				</template>
			</NcButton>
			<span class="markdown-editor__toolbar-separator" aria-hidden="true" />
			<!-- Group 5: separators -->
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Horizontal rule')"
				@click="applyHorizontalRule">
				<template #icon>
					<NcIconSvgWrapper :path="mdiMinus" :size="18" />
				</template>
			</NcButton>
			<span class="markdown-editor__toolbar-separator" aria-hidden="true" />
			<!-- Group 6: history -->
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Undo')"
				:disabled="!canUndo"
				@click="undoAction">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndo" :size="18" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Redo')"
				:disabled="!canRedo"
				@click="redoAction">
				<template #icon>
					<NcIconSvgWrapper :path="mdiRedo" :size="18" />
				</template>
			</NcButton>
		</div>
		<CodeMirror
			ref="codeMirror"
			:id="editorId"
			v-model="internalValue"
			:aria-describedby="describedBy"
			:tab-size="4"
			:tab="true"
			:placeholder="placeholder"
			:extensions="extensions"
			@update="onEditorUpdate"
			@ready="onEditorReady"
			:style="{ height: 'auto' }" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'

import CodeMirror from 'vue-codemirror6'
import { EditorView, lineNumbers, keymap } from '@codemirror/view'
import type { ViewUpdate } from '@codemirror/view'
import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete'
import { indentUnit, bracketMatching } from '@codemirror/language'
import { defaultKeymap, indentWithTab, history, undo, redo, undoDepth, redoDepth } from '@codemirror/commands'
import type { EditorState } from '@codemirror/state'
import { material } from '@uiw/codemirror-theme-material'
import {
	mdiFormatBold,
	mdiFormatItalic,
	mdiFormatListBulleted,
	mdiFormatListNumbered,
	mdiFormatQuoteClose,
	mdiCodeTags,
	mdiCodeBraces,
	mdiLink,
	mdiMinus,
	mdiUndo,
	mdiRedo,
} from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import HeadingMenu from './markdown-editor/HeadingMenu.vue'

const toggleSurroundSelection = (view: EditorView, prefix: string, suffix: string = prefix) => {
	const mainSelection = view.state.selection.main
	const documentLength = view.state.doc.length
	const markerStart = mainSelection.from - prefix.length
	const markerEnd = mainSelection.to + suffix.length

	if (markerStart >= 0 && markerEnd <= documentLength) {
		const leftMarker = view.state.sliceDoc(markerStart, mainSelection.from)
		const rightMarker = view.state.sliceDoc(mainSelection.to, markerEnd)

		if (leftMarker === prefix && rightMarker === suffix) {
			const selectedText = view.state.sliceDoc(mainSelection.from, mainSelection.to)

			view.dispatch({
				changes: {
					from: markerStart,
					to: markerEnd,
					insert: selectedText,
				},
				selection: {
					anchor: markerStart,
					head: markerStart + selectedText.length,
				},
			})

			return true
		}
	}

	const selectedText = view.state.sliceDoc(mainSelection.from, mainSelection.to)
	const outputText = `${prefix}${selectedText}${suffix}`

	view.dispatch({
		changes: {
			from: mainSelection.from,
			to: mainSelection.to,
			insert: outputText,
		},
		selection: {
			anchor: mainSelection.from + prefix.length,
			head: mainSelection.from + prefix.length + selectedText.length,
		},
	})

	return true
}

const toggleLinePrefix = (view: EditorView, prefix: string) => {
	const { state } = view
	const { from, to } = state.selection.main
	const fromLine = state.doc.lineAt(from)
	const toLine = state.doc.lineAt(to)
	const changes: { from: number; to: number; insert: string }[] = []

	// Detect if ALL selected lines already have the prefix – if so, remove it
	let allHavePrefix = true
	for (let lineNo = fromLine.number; lineNo <= toLine.number; lineNo++) {
		const line = state.doc.line(lineNo)
		if (!line.text.startsWith(prefix)) {
			allHavePrefix = false
			break
		}
	}

	for (let lineNo = fromLine.number; lineNo <= toLine.number; lineNo++) {
		const line = state.doc.line(lineNo)
		if (allHavePrefix) {
			changes.push({ from: line.from, to: line.from + prefix.length, insert: '' })
		} else {
			changes.push({ from: line.from, to: line.from, insert: prefix })
		}
	}

	view.dispatch({ changes })
	return true
}

const applyOrderedListToLines = (view: EditorView) => {
	const { state } = view
	const { from, to } = state.selection.main
	const fromLine = state.doc.lineAt(from)
	const toLine = state.doc.lineAt(to)
	const changes: { from: number; to: number; insert: string }[] = []

	// Detect if ALL selected lines already start with `N. ` pattern
	const orderedListRe = /^\d+\. /
	let allHavePrefix = true
	for (let lineNo = fromLine.number; lineNo <= toLine.number; lineNo++) {
		const line = state.doc.line(lineNo)
		if (!orderedListRe.test(line.text)) {
			allHavePrefix = false
			break
		}
	}

	let counter = 1
	for (let lineNo = fromLine.number; lineNo <= toLine.number; lineNo++) {
		const line = state.doc.line(lineNo)
		if (allHavePrefix) {
			const match = line.text.match(orderedListRe)
			if (match) {
				changes.push({ from: line.from, to: line.from + match[0].length, insert: '' })
			}
		} else {
			changes.push({ from: line.from, to: line.from, insert: `${counter}. ` })
			counter++
		}
	}

	view.dispatch({ changes })
	return true
}

const applyHeadingToLine = (view: EditorView, level: 1 | 2 | 3 | 4 | 5 | 6) => {
	const { state } = view
	const line = state.doc.lineAt(state.selection.main.from)
	const prefix = '#'.repeat(level) + ' '
	const headingRe = /^(#{1,6}) /
	const match = line.text.match(headingRe)

	if (match) {
		if (match[1].length === level) {
			// Same level → remove heading
			view.dispatch({
				changes: { from: line.from, to: line.from + match[0].length, insert: '' },
			})
		} else {
			// Different level → replace
			view.dispatch({
				changes: { from: line.from, to: line.from + match[0].length, insert: prefix },
			})
		}
	} else {
		view.dispatch({
			changes: { from: line.from, to: line.from, insert: prefix },
		})
	}
	return true
}

const clearHeadingFromLine = (view: EditorView) => {
	const { state } = view
	const { from, to } = state.selection.main
	const fromLine = state.doc.lineAt(from)
	const toLine = state.doc.lineAt(to)
	const headingRe = /^(#{1,6}) /
	const changes: { from: number; to: number; insert: string }[] = []

	for (let lineNo = fromLine.number; lineNo <= toLine.number; lineNo++) {
		const line = state.doc.line(lineNo)
		const match = line.text.match(headingRe)
		if (match) {
			changes.push({ from: line.from, to: line.from + match[0].length, insert: '' })
		}
	}

	if (changes.length) {
		view.dispatch({ changes })
	}

	return true
}

const insertMarkdownLink = (view: EditorView) => {
	const mainSelection = view.state.selection.main
	const selectedText = view.state.sliceDoc(mainSelection.from, mainSelection.to) || 'text'
	const outputText = `[${selectedText}](https://)`

	view.dispatch({
		changes: {
			from: mainSelection.from,
			to: mainSelection.to,
			insert: outputText,
		},
		selection: {
			anchor: mainSelection.from + selectedText.length + 3,
			head: mainSelection.from + selectedText.length + 11,
		},
	})

	return true
}

const insertCodeBlock = (view: EditorView) => {
	const { state } = view
	const { from, to } = state.selection.main
	const lineStart = state.doc.lineAt(from)
	const lineEnd = state.doc.lineAt(to)

	const insertStart = lineStart.from
	const insertEnd = lineEnd.to

	const prefix = '```\n'
	const suffix = '\n```'

	view.dispatch({
		changes: {
			from: insertStart,
			to: insertEnd,
			insert: `${prefix}${state.sliceDoc(insertStart, insertEnd)}${suffix}`,
		},
		selection: {
			anchor: insertStart + prefix.length,
			head: insertStart + prefix.length + (insertEnd - insertStart),
		},
	})

	return true
}

const insertHorizontalRule = (view: EditorView) => {
	const { state } = view
	const { from } = state.selection.main
	const line = state.doc.lineAt(from)
	const lineEnd = line.to

	view.dispatch({
		changes: {
			from: lineEnd,
			to: lineEnd,
			insert: '\n\n---\n',
		},
		selection: {
			anchor: lineEnd + 5,
			head: lineEnd + 5,
		},
	})

	return true
}

defineOptions({
	name: 'MarkdownEditor',
})

const props = withDefaults(defineProps<{
	modelValue?: string
	label?: string
	description?: string
	placeholder?: string
	minHeight?: string
	maxHeight?: string
}>(), {
	modelValue: '',
	label: '',
	description: '',
	placeholder: '',
	minHeight: '80px',
	maxHeight: 'none',
})

const emit = defineEmits<{
	(event: 'update:modelValue', value: string): void
}>()

const codeMirror = ref<{ view?: EditorView | { value: EditorView } | unknown } | null>(null)
const editorId = `markdown-editor-${Math.random().toString(36).substr(2, 9)}`
const descriptionId = `${editorId}-description`
const internalValue = ref(props.modelValue)
const canUndo = ref(false)
const canRedo = ref(false)

const describedBy = computed(() => {
	const ids: string[] = []
	if (props.description) {
		ids.push(descriptionId)
	}
	return ids.join(' ')
})

const extensions = computed(() => {
	return [
		history(),
		lineNumbers(),
		EditorView.lineWrapping,
		bracketMatching(),
		closeBrackets(),
		material,
		indentUnit.of('\t'),
		keymap.of([
			{ key: 'Mod-b', run: (view) => toggleSurroundSelection(view, '**') },
			{ key: 'Mod-i', run: (view) => toggleSurroundSelection(view, '_') },
			{ key: 'Mod-k', run: insertMarkdownLink },
			{ key: 'Mod-Shift-1', run: (view) => applyHeadingToLine(view, 1) },
			{ key: 'Mod-Shift-2', run: (view) => applyHeadingToLine(view, 2) },
			{ key: 'Mod-Shift-3', run: (view) => applyHeadingToLine(view, 3) },
			{ key: 'Mod-Shift-4', run: (view) => applyHeadingToLine(view, 4) },
			{ key: 'Mod-Shift-5', run: (view) => applyHeadingToLine(view, 5) },
			{ key: 'Mod-Shift-6', run: (view) => applyHeadingToLine(view, 6) },
			{ key: 'Mod-Shift-8', run: (view) => toggleLinePrefix(view, '- ') },
			{ key: 'Mod-Shift-9', run: (view) => toggleLinePrefix(view, '> ') },
			{ key: 'Mod-Shift-c', run: insertCodeBlock },
			{ key: 'Mod-Shift-h', run: insertHorizontalRule },
			...closeBracketsKeymap,
			...defaultKeymap,
			indentWithTab,
		]),
	]
})

watch(() => props.modelValue, (newValue) => {
	if (newValue !== internalValue.value) {
		internalValue.value = newValue
	}
})

watch(internalValue, (newValue) => {
	if (newValue !== props.modelValue) {
		emit('update:modelValue', newValue)
	}
})

function isEditorView(view: unknown): view is EditorView {
	return !!view
		&& typeof view === 'object'
		&& 'state' in view
		&& 'dispatch' in view
		&& 'focus' in view
}

function getCurrentView() {
	const codeMirrorRef = codeMirror.value
	if (!codeMirrorRef) {
		return null
	}

	if (codeMirrorRef.view && typeof codeMirrorRef.view === 'object' && 'value' in codeMirrorRef.view) {
		return isEditorView(codeMirrorRef.view.value) ? codeMirrorRef.view.value : null
	}

	return isEditorView(codeMirrorRef.view) ? codeMirrorRef.view : null
}

function syncHistoryState(state: EditorState) {
	canUndo.value = undoDepth(state) > 0
	canRedo.value = redoDepth(state) > 0
}

function onEditorReady({ view }: { view: EditorView }) {
	syncHistoryState(view.state)
}

function onEditorUpdate(viewUpdate: ViewUpdate) {
	syncHistoryState(viewUpdate.state)
}

function withEditor(callback: (view: EditorView) => void, { focus = true }: { focus?: boolean } = {}) {
	const view = getCurrentView()
	if (!view) {
		return
	}
	callback(view)
	if (focus) {
		view.focus()
	}
}

function applyBold() {
	withEditor((view) => toggleSurroundSelection(view, '**'))
}

function applyItalic() {
	withEditor((view) => toggleSurroundSelection(view, '_'))
}

function applyUnderline() {
	withEditor((view) => toggleSurroundSelection(view, '<u>', '</u>'))
}

function applyStrikethrough() {
	withEditor((view) => toggleSurroundSelection(view, '~~'))
}

function clearHeading() {
	withEditor((view) => clearHeadingFromLine(view))
}

function applyHeading(level: 1 | 2 | 3 | 4 | 5 | 6) {
	withEditor((view) => applyHeadingToLine(view, level))
}

function applyUnorderedList() {
	withEditor((view) => toggleLinePrefix(view, '- '))
}

function applyOrderedList() {
	withEditor((view) => applyOrderedListToLines(view))
}

function applyBlockquote() {
	withEditor((view) => toggleLinePrefix(view, '> '))
}

function applyCode() {
	withEditor((view) => toggleSurroundSelection(view, '`'))
}

function applyCodeBlock() {
	withEditor((view) => insertCodeBlock(view))
}

function applyHorizontalRule() {
	withEditor((view) => insertHorizontalRule(view))
}

function applyLink() {
	withEditor((view) => insertMarkdownLink(view))
}


function undoAction() {
	withEditor((view) => {
		undo(view)
	}, { focus: false })
}
function redoAction() {
	withEditor((view) => {
		redo(view)
	}, { focus: false })
}

defineExpose({
	t,
	mdiFormatBold,
	mdiFormatItalic,
	mdiFormatListBulleted,
	mdiFormatListNumbered,
	mdiFormatQuoteClose,
	mdiCodeBraces,
	mdiCodeTags,
	mdiLink,
	mdiMinus,
	mdiUndo,
	mdiRedo,
	editorId,
	descriptionId,
	internalValue,
	canUndo,
	canRedo,
	describedBy,
	extensions,
	isEditorView,
	getCurrentView,
	syncHistoryState,
	onEditorReady,
	onEditorUpdate,
	withEditor,
	applyBold,
	applyItalic,
	applyUnderline,
	applyStrikethrough,
	clearHeading,
	applyHeading,
	applyUnorderedList,
	applyOrderedList,
	applyBlockquote,
	applyCode,
	applyCodeBlock,
	applyHorizontalRule,
	applyLink,
	undoAction,
	redoAction,
})
</script>

<style lang="scss">
.markdown-editor {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	overflow: hidden;

	&__header {
		padding: 8px 12px 4px;
	}

	&__heading {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__label {
		display: block;
		font-weight: 600;
		font-size: 0.9rem;
		line-height: 1.35;
		color: var(--color-main-text);
	}

	&__description {
		margin: 0;
		font-size: 0.75rem;
		line-height: 1.3;
		color: var(--color-text-maxcontrast);
		opacity: 0.62;
	}

	&__toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 4px;
		padding: 4px 8px;
		border-bottom: 1px solid var(--color-border);
		background: var(--color-main-background);
	}

	&__headings-menu {
		display: inline-flex;
	}

	&__toolbar-separator {
		display: inline-block;
		width: 1px;
		height: 20px;
		background: var(--color-border);
		margin: 0 4px;
		align-self: center;
	}

	.cm-editor {
		height: auto;
		min-height: var(--markdown-editor-min-height, 80px);
		max-height: var(--markdown-editor-max-height, none);
		overflow: hidden;
		background: transparent;
		font-family: 'Courier New', Courier, monospace;
		font-size: 14px;
		line-height: 1.5;

		&.cm-focused {
			outline: none;
		}

		.cm-gutters {
			border: 0;
			background: transparent;
		}

		.cm-scroller {
			overflow-y: auto;
			padding: 0 10px 10px;
		}

		.cm-content {
			font-family: inherit;
			padding: 10px 0 18px;
		}

		.cm-activeLine,
		.cm-activeLineGutter {
			background: transparent;
		}
	}
}
</style>
