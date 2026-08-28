/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,

	{
		name: 'libresign/ignores',
		ignores: [
			// Generated files
			'src/types/openapi/*',
			'js/*',
			// Build artifacts
			'build/*',
			// Node modules
			'node_modules/*',
			// TODO: upstream
			'openapi-*.json',
		],
	},

		{
		name: 'libresign/config',
		rules: {
			// production only
			'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
			// `eslint-plugin-import` (e quindi `import/order` e `import/no-unresolved`)
			// non fa più parte di @nextcloud/eslint-config a partire dalla v9.
			// L'ordinamento degli import è ora gestito da `perfectionist/sort-imports`
			// (fornito dalla config condivisa), che verrà valutata come regola
			// separata durante la migrazione.
		},
	},

	{
		name: 'libresign/config-vue',
		files: ['**/*.vue'],
		rules: {
			// production only
			'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		},
	},

	{
		name: 'libresign/openapi-overrides',
		files: ['src/types/openapi/*.ts'],
		rules: {
			'@typescript-eslint/no-explicit-any': 'off',
			quotes: 'off',
			'no-multiple-empty-lines': 'off',
			'no-use-before-define': 'off',
		},
	},
		{
		name: 'libresign/disabled-during-migration',
		rules: Object.fromEntries([
			'perfectionist/sort-imports',
			'jsdoc/require-jsdoc',
			'@stylistic/indent',
			'import-extensions/extensions',
			'vue/first-attribute-linebreak',
			'vue/attribute-hyphenation',
			'perfectionist/sort-named-imports',
			'@stylistic/no-tabs',
			'@stylistic/member-delimiter-style',
			'@stylistic/function-paren-newline',
			'@stylistic/arrow-parens',
			'vue/html-indent',
			'antfu/top-level-function',
			'jsdoc/require-param',
			'curly',
			'@typescript-eslint/no-unused-vars',
			'vue/singleline-html-element-content-newline',
			'vue/v-on-event-hyphenation',
			'import-extensions/ban-inline-type-imports',
			'vue/max-attributes-per-line',
			'@typescript-eslint/consistent-type-imports',
			'vue/custom-event-name-casing',
			'vue/define-macros-order',
			'@stylistic/comma-dangle',
			'@stylistic/no-multiple-empty-lines',
			'@stylistic/implicit-arrow-linebreak',
			'@typescript-eslint/no-use-before-define',
			'@stylistic/exp-list-style',
			'vue/attributes-order',
			'@stylistic/quote-props',
			'vue/padding-line-between-blocks',
			'@stylistic/no-extra-semi',
			'vue/multi-word-component-names',
			'jsdoc/require-param-description',
			'@typescript-eslint/no-explicit-any',
			'@stylistic/semi',
			'vue/html-self-closing',
			'vue/no-unused-refs',
			'@stylistic/eol-last',
			'prefer-object-has-own',
			'no-empty',
			'vue/no-unused-properties',
			'@nextcloud/no-deprecated-library-props',
			'jsdoc/no-types',
			'camelcase',
			'jsdoc/tag-lines',
			'no-use-before-define',
			'vue/new-line-between-multi-line-property',
			'vue/no-v-html',
			'vue/no-boolean-default',
			'vue/prefer-separate-static-class',
			'@nextcloud/l10n-non-breaking-space',
			'@nextcloud/l10n-enforce-ellipsis',
			'@stylistic/operator-linebreak',
			'@stylistic/max-statements-per-line',
			'no-empty-pattern',
			'vue/html-closing-bracket-newline',
			'jsdoc/check-tag-names',
			'@stylistic/indent-binary-ops',
			'@stylistic/padded-blocks',
			'no-useless-escape',
			'@stylistic/function-call-argument-newline',
			'no-useless-assignment',
			'vue/slot-name-casing',
			'no-extra-boolean-cast',
			'@typescript-eslint/no-empty-object-type',
			'no-unused-vars',
			'jsdoc/valid-types',
			'vue/no-template-shadow',
			'prefer-const',
			'@stylistic/space-in-parens',
			'@stylistic/space-before-function-paren',
			'object-shorthand',
			'vue/multiline-html-element-content-newline',
			'vue/no-useless-mustaches',
			'vue/require-default-prop',
			'vue/prefer-prop-type-boolean-first',
			'package-json/sort-package-json',
			'jsdoc/escape-inline-tags',
			'vue/key-spacing',
			'vue/no-useless-v-bind',
			'vue/no-undef-components',
			'vue/no-use-v-if-with-v-for',
			'@nextcloud/no-deprecated-globals',
			'no-undef',
			'@stylistic/no-trailing-spaces',
			'jsdoc/reject-any-type',
			'@typescript-eslint/no-unused-expressions',
			'@stylistic/no-multi-spaces',
			'@stylistic/lines-between-class-members',
			'no-unassigned-vars',
			'jsdoc/no-defaults',
			'@stylistic/function-call-spacing',
			'vue/no-dupe-keys',
		].map(rule => [rule, 'off'])),
	},
		{
		name: 'libresign/tests-console-override',
		files: [
			'src/tests/setup.js',
			'src/tests/store/filters.spec.ts',
		],
		rules: {
			// These files intentionally intercept console.error/console.warn
			// to fail tests on unexpected console output, or to silence
			// expected console output during a specific assertion. This is
			// test tooling, not application logging.
			'no-console': 'off',
		},
	},
]