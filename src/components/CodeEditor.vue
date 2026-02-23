<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="code-editor">
		<label v-if="label" :for="editorId" class="code-editor__label">
			{{ label }}
		</label>
		<CodeMirror
			:id="editorId"
			v-model="internalValue"
			:tab-size="4"
			:tab="true"
			:placeholder="placeholder"
			:extensions="extensions"
			:style="{ height: 'auto', minHeight: '80px' }" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import CodeMirror from 'vue-codemirror6'
import { twig } from '@ssddanbrown/codemirror-lang-twig'
import { EditorView, lineNumbers } from '@codemirror/view'
import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete'
import { keymap } from '@codemirror/view'
import { indentUnit, bracketMatching } from '@codemirror/language'
import { defaultKeymap, indentWithTab } from '@codemirror/commands'
import { material } from '@uiw/codemirror-theme-material'

export default {
	name: 'CodeEditor',
	components: {
		CodeMirror,
	},
	props: {
		modelValue: {
			type: String,
			default: '',
	},
		label: {
			type: String,
			default: '',
	},
		placeholder: {
			type: String,
			default: '',
	},
	},
	data() {
		return {
			editorId: `code-editor-${Math.random().toString(36).substr(2, 9)}`,
			internalValue: this.modelValue,
		}
	},
	computed: {
		extensions() {
			return [
				twig(),
				lineNumbers(),
				EditorView.lineWrapping,
				bracketMatching(),
				closeBrackets(),
				material,
				indentUnit.of('\t'),
				keymap.of([
					...closeBracketsKeymap,
					...defaultKeymap,
					indentWithTab,
				]),
			]
		},
	},
	watch: {
		modelValue(newValue) {
			if (newValue !== this.internalValue) {
				this.internalValue = newValue
			}
		},
		internalValue(newValue) {
			if (newValue !== this.modelValue) {
				this.$emit('update:modelValue', newValue)
			}
		},
	},
	methods: {
		t,
	},
}
</script>

<style lang="scss">
.code-editor {
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

	:deep(.cm-editor) {
		height: auto;
		min-height: 80px;
		font-family: 'Courier New', Courier, monospace;
		font-size: 14px;
		line-height: 1.5;

		.cm-content {
			font-family: inherit;
		}
	}
}
</style>
