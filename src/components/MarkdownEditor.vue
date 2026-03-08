<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="markdown-editor" :style="{ '--markdown-editor-min-height': minHeight }">
		<label v-if="label" :for="editorId" class="markdown-editor__label">
			{{ label }}
		</label>
		<div class="markdown-editor__toolbar">
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
			<NcButton variant="tertiary"
				:aria-label="t('libresign', 'Underline')"
				@click="applyUnderline">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFormatUnderline" :size="18" />
				</template>
			</NcButton>
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
import { EditorView, lineNumbers, keymap, type ViewUpdate } from '@codemirror/view'
import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete'
import { indentUnit, bracketMatching } from '@codemirror/language'
import { defaultKeymap, indentWithTab, history, undo, redo, undoDepth, redoDepth } from '@codemirror/commands'
import { type EditorState } from '@codemirror/state'
import { material } from '@uiw/codemirror-theme-material'
import {
	mdiFormatBold,
	mdiFormatItalic,
	mdiFormatUnderline,
	mdiUndo,
	mdiRedo,
} from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

const toggleSurroundSelection = (view: EditorView, prefix: string, suffix = prefix) => {
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

defineOptions({
	name: 'MarkdownEditor',
})

const props = withDefaults(defineProps<{
	modelValue?: string
	label?: string
	placeholder?: string
	minHeight?: string
}>(), {
	modelValue: '',
	label: '',
	placeholder: '',
	minHeight: '80px',
})

const emit = defineEmits<{
	(event: 'update:modelValue', value: string): void
}>()

const codeMirror = ref<{ view?: EditorView | { value: EditorView } | unknown } | null>(null)
const editorId = `markdown-editor-${Math.random().toString(36).substr(2, 9)}`
const internalValue = ref(props.modelValue)
const canUndo = ref(false)
const canRedo = ref(false)

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
			{ key: 'Mod-u', run: (view) => toggleSurroundSelection(view, '<u>', '</u>') },
			{ key: 'Mod-Shift-s', run: (view) => toggleSurroundSelection(view, '~~') },
			{ key: 'Mod-k', run: insertMarkdownLink },
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
	mdiFormatUnderline,
	mdiUndo,
	mdiRedo,
	editorId,
	internalValue,
	canUndo,
	canRedo,
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
	undoAction,
	redoAction,
})
</script>

<style lang="scss">
.markdown-editor {
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	overflow: hidden;

	&__label {
		display: block;
		padding: 8px 12px;
		font-weight: bold;
		font-size: 14px;
		background-color: var(--color-background-dark);
		border-bottom: 1px solid var(--color-border);
		color: var(--color-main-text);
	}

	&__toolbar {
		display: flex;
		gap: 4px;
		padding: 6px 8px;
		border-bottom: 1px solid var(--color-border);
	}

	:deep(.cm-editor) {
		height: auto;
		min-height: var(--markdown-editor-min-height, 80px);
		font-family: 'Courier New', Courier, monospace;
		font-size: 14px;
		line-height: 1.5;

		.cm-content {
			font-family: inherit;
		}
	}
}
</style>
