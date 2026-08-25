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
			// @nextcloud/eslint-config v9 replaced eslint-plugin-import with
			// perfectionist/sort-imports and import-extensions; keep only app overrides here.
		},
	},

	{
		// Vue plugin is only registered for *.vue files; keep this override scoped.
		name: 'libresign/vue-overrides',
		files: ['**/*.vue'],
		rules: {
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

	/**
	 * Temporary disables while migrating to @nextcloud/eslint-config v9 / ESLint 10.
	 * Source of truth for pending work on https://github.com/LibreSign/libresign/issues/8051
	 * Enable one rule at a time: remove it here, fix violations, commit separately.
	 */
	{
		name: 'libresign/disabled-during-migration',
		rules: {
			'@nextcloud/l10n-enforce-ellipsis': 'off',
			'@nextcloud/l10n-non-breaking-space': 'off',
			'@nextcloud/no-deprecated-globals': 'off',
			'@nextcloud/no-deprecated-library-props': 'off',
			'@stylistic/arrow-parens': 'off',
			'@stylistic/comma-dangle': 'off',
			'@stylistic/eol-last': 'off',
			'@stylistic/exp-list-style': 'off',
			'@stylistic/function-call-argument-newline': 'off',
			'@stylistic/function-call-spacing': 'off',
			'@stylistic/function-paren-newline': 'off',
			'@stylistic/implicit-arrow-linebreak': 'off',
			'@stylistic/indent': 'off',
			'@stylistic/indent-binary-ops': 'off',
			'@stylistic/lines-between-class-members': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'@stylistic/member-delimiter-style': 'off',
			'@stylistic/no-extra-semi': 'off',
			'@stylistic/no-multi-spaces': 'off',
			'@stylistic/no-multiple-empty-lines': 'off',
			'@stylistic/no-tabs': 'off',
			'@stylistic/no-trailing-spaces': 'off',
			'@stylistic/operator-linebreak': 'off',
			'@stylistic/padded-blocks': 'off',
			'@stylistic/quote-props': 'off',
			'@stylistic/semi': 'off',
			'@stylistic/space-before-function-paren': 'off',
			'@stylistic/space-in-parens': 'off',
			'@typescript-eslint/consistent-type-imports': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'@typescript-eslint/no-unused-expressions': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'@typescript-eslint/no-use-before-define': 'off',
			'antfu/top-level-function': 'off',
			camelcase: 'off',
			curly: 'off',
			'import-extensions/ban-inline-type-imports': 'off',
			'import-extensions/extensions': 'off',
			'jsdoc/check-tag-names': 'off',
			'jsdoc/escape-inline-tags': 'off',
			'jsdoc/no-defaults': 'off',
			'jsdoc/no-types': 'off',
			'jsdoc/reject-any-type': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-description': 'off',
			'jsdoc/tag-lines': 'off',
			'jsdoc/valid-types': 'off',
			'no-unused-vars': 'off',
			'no-use-before-define': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'vue/attribute-hyphenation': 'off',
			'vue/attributes-order': 'off',
			'vue/custom-event-name-casing': 'off',
			'vue/define-macros-order': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/html-closing-bracket-newline': 'off',
			'vue/html-indent': 'off',
			'vue/html-self-closing': 'off',
			'vue/key-spacing': 'off',
			'vue/max-attributes-per-line': 'off',
			'vue/multi-word-component-names': 'off',
			'vue/multiline-html-element-content-newline': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'vue/no-boolean-default': 'off',
			'vue/no-unused-properties': 'off',
			'vue/no-unused-refs': 'off',
			'vue/no-v-html': 'off',
			'vue/padding-line-between-blocks': 'off',
			'vue/prefer-separate-static-class': 'off',
			'vue/singleline-html-element-content-newline': 'off',
			'vue/slot-name-casing': 'off',
			'vue/v-on-event-hyphenation': 'off',
		},
	},
]
