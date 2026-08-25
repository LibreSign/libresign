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
	 * Remaining rules intentionally deferred with per-rule reasons (issue #8051 follow-ups).
	 * Enable one rule at a time: remove it here, fix violations, commit separately.
	 * Source of truth: https://github.com/LibreSign/libresign/issues/8051
	 */
	{
		name: 'libresign/disabled-during-migration',
		rules: {
			// Translations — changes user-visible strings
			'@nextcloud/l10n-enforce-ellipsis': 'off', // ~6 hits; changes translation strings
			'@nextcloud/l10n-non-breaking-space': 'off', // ~6 hits; changes translation strings

			// Behavior/API risk — may rename Vue events, slots, attrs, or break DOM contracts
			'@nextcloud/no-deprecated-library-props': 'off', // ~10 hits; needs careful Nc* prop migration
			'vue/attribute-hyphenation': 'off', // ~404 hits; large + DOM attr naming
			'vue/custom-event-name-casing': 'off', // ~65 hits; may rename Vue events
			'vue/slot-name-casing': 'off', // ~3 hits; may rename slots
			'vue/v-on-event-hyphenation': 'off', // ~74 hits; may rename listeners
			'vue/no-v-html': 'off', // ~8 hits; intentional trusted HTML in places
			'vue/multi-word-component-names': 'off', // ~18 hits; rename churn for short view names
			'camelcase': 'off', // ~9 hits; store fields mirror backend/API keys

			// Formatting — large autofix churn; follow-up PR
			'@stylistic/arrow-parens': 'off', // ~168 hits
			'@stylistic/comma-dangle': 'off', // ~46 hits
			'@stylistic/eol-last': 'off', // ~10 hits
			'@stylistic/exp-list-style': 'off', // ~34 hits
			'@stylistic/function-call-argument-newline': 'off', // ~4 hits
			'@stylistic/function-call-spacing': 'off', // ~1 hit
			'@stylistic/function-paren-newline': 'off', // ~177 hits
			'@stylistic/implicit-arrow-linebreak': 'off', // ~39 hits
			'@stylistic/indent': 'off', // ~778 hits; tabs vs spaces migration
			'@stylistic/indent-binary-ops': 'off', // ~4 hits
			'@stylistic/lines-between-class-members': 'off', // ~1 hit
			'@stylistic/max-statements-per-line': 'off', // ~100 hits
			'@stylistic/member-delimiter-style': 'off', // ~180 hits
			'@stylistic/no-extra-semi': 'off', // ~23 hits
			'@stylistic/no-multi-spaces': 'off', // ~1 hit
			'@stylistic/no-multiple-empty-lines': 'off', // ~42 hits
			'@stylistic/no-tabs': 'off', // ~190 hits; tabs vs spaces migration
			'@stylistic/no-trailing-spaces': 'off', // ~1 hit
			'@stylistic/operator-linebreak': 'off', // ~6 hits
			'@stylistic/padded-blocks': 'off', // ~4 hits
			'@stylistic/quote-props': 'off', // ~26 hits
			'@stylistic/semi': 'off', // ~16 hits
			'@stylistic/space-before-function-paren': 'off', // ~2 hits
			'@stylistic/space-in-parens': 'off', // ~2 hits
			'vue/first-attribute-linebreak': 'off', // ~435 hits; formatting churn
			'vue/html-indent': 'off', // ~164 hits; formatting churn
			'vue/max-attributes-per-line': 'off', // ~68 hits; formatting churn
			'vue/multiline-html-element-content-newline': 'off', // ~2 hits
			'vue/singleline-html-element-content-newline': 'off', // ~74 hits; formatting churn

			// Import sorting / extensions — large churn; needs dedicated PR
			'perfectionist/sort-imports': 'off', // ~1216 hits
			'perfectionist/sort-named-imports': 'off', // ~198 hits
			'import-extensions/extensions': 'off', // ~558 hits
			'import-extensions/ban-inline-type-imports': 'off', // ~68 hits; unsafe autofix vs type-only imports

			// Documentation debt
			'jsdoc/require-jsdoc': 'off', // ~1144 hits; needs real docs not stubs
			'jsdoc/require-param': 'off', // ~99 hits; follow require-jsdoc work
			'jsdoc/require-param-description': 'off', // ~18 hits; follow require-jsdoc work

			// Semantic cleanup needing focused PRs
			'@typescript-eslint/consistent-type-imports': 'off', // ~66 hits; mostly typeof import() in Vitest mocks
			'@typescript-eslint/no-explicit-any': 'off', // ~17 hits; needs typed replacements
			'@typescript-eslint/no-unused-vars': 'off', // ~78 hits; non-fixable cleanup
			'@typescript-eslint/no-use-before-define': 'off', // ~38 hits; non-fixable
			'no-unused-vars': 'off', // ~3 hits; non-fixable (JS twin)
			'no-use-before-define': 'off', // ~8 hits; non-fixable (JS twin)
			'antfu/top-level-function': 'off', // ~114 hits; style preference, large churn
			'vue/no-unused-properties': 'off', // ~11 hits; public API / shared editor props
		},
	},
]
