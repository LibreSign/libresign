/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	globals: {
		appName: true,
	},
	extends: [
		'@nextcloud',
	],
	rules: {
		// production only
		'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		'import/order': [
			'error',
			{
				groups: ['builtin', 'external', 'internal', ['parent', 'sibling', 'index'], 'unknown'],
				pathGroups: [
					{
						// group all style imports at the end
						pattern: '{*.css,*.scss}',
						patternOptions: { matchBase: true },
						group: 'unknown',
						position: 'after',
					},
					{
						// group material design icons
						pattern: 'vue-material-design-icons/**',
						group: 'external',
						position: 'after',
					},
					{
						// group @nextcloud imports
						pattern: '@nextcloud/{!(vue),!(vue)/**}',
						group: 'external',
						position: 'after',
					},
					{
						// group @nextcloud/vue imports
						pattern: '{@nextcloud/vue,@nextcloud/vue/**}',
						group: 'external',
						position: 'after',
					},
					{
						// group project components
						pattern: '*.vue',
						patternOptions: { matchBase: true },
						group: 'parent',
						position: 'before',
					},
				],
				pathGroupsExcludedImportTypes: ['@nextcloud', 'vue-material-design-icons'],
				'newlines-between': 'always',
				alphabetize: {
					order: 'asc',
					caseInsensitive: true,
				},
				warnOnUnassignedImports: true,
			},
		],
		'import/no-unresolved': ['error', {
			// Ignore Webpack query parameters, not supported by eslint-plugin-import
			// https://github.com/import-js/eslint-plugin-import/issues/2562
			ignore: ['\\?raw$'],
		}],
	},
	overrides: [
		{
			files: ['src/types/openapi/*.ts'],
			rules: {
				'@typescript-eslint/no-explicit-any': 'off',
				quotes: 'off',
				'no-multiple-empty-lines': 'off',
				'no-use-before-define': 'off',
			},
		},
	],
}
