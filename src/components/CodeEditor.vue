<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="code-editor">
		<label v-if="label" :for="editorId" class="code-editor__label">
			{{ label }}
		</label>
		<codemirror
			:id="editorId"
			:value="value"
			:options="editorOptions"
			@input="onInput" />
	</div>
</template>

<script>
import { codemirror } from 'vue-codemirror'
import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/material.css'
import 'codemirror/mode/twig/twig.js'
import 'codemirror/addon/edit/matchbrackets.js'
import 'codemirror/addon/edit/closebrackets.js'
import 'codemirror/addon/edit/closetag.js'

export default {
	name: 'CodeEditor',
	components: {
		codemirror,
	},
	props: {
		value: {
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
		}
	},
	computed: {
		editorOptions() {
			return {
				mode: 'twig',
				theme: 'material',
				lineNumbers: true,
				lineWrapping: true,
				matchBrackets: true,
				autoCloseBrackets: true,
				indentUnit: 4,
				tabSize: 4,
				indentWithTabs: true,
				placeholder: this.placeholder,
			}
		},
	},
	methods: {
		onInput(value) {
			this.$emit('input', value)
		},
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

	.CodeMirror {
		height: auto;
		min-height: 200px;
		font-family: 'Courier New', Courier, monospace;
		font-size: 14px;
		line-height: 1.5;
		background-color: var(--color-main-background);
		color: var(--color-main-text);
	}

	.CodeMirror-cursor {
		border-left-color: var(--color-main-text);
	}

	.CodeMirror-selected {
		background-color: rgba(var(--color-primary-element-rgb), 0.2);
	}

	.CodeMirror-gutters {
		background-color: var(--color-background-dark);
		border-right: 1px solid var(--color-border);
	}

	.CodeMirror-linenumber {
		color: var(--color-text-maxcontrast);
	}

	.CodeMirror-matchingbracket {
		color: var(--color-primary-element) !important;
		background-color: rgba(var(--color-primary-element-rgb), 0.1);
		font-weight: bold;
	}
}
</style>
