/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, params?: Record<string, string>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, value)
		}, text)
	},
}))

import { useCatalogCrudTable } from '../../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogCrudTable'

describe('useCatalogCrudTable', () => {
	it('does not allow selecting or removing group rules when group deletions are disabled', () => {
		const table = useCatalogCrudTable({
			state: {
				inheritedSystemRule: null,
				hasGlobalDefault: false,
				viewMode: 'group-admin',
				visibleGroupRules: [
					{ id: 'group-finance', targetId: 'finance', value: 'parallel', canRemove: false },
				],
				visibleUserRules: [
					{ id: 'user-john', targetId: 'john', value: 'parallel', canRemove: true },
				],
				resolveTargetLabel: (_scope, targetId) => targetId,
			},
			summarizeRuleValue: (value) => String(value),
		})

		expect(table.filteredCrudRows.value).toEqual(expect.arrayContaining([
			expect.objectContaining({ ruleId: 'group-finance', canRemove: false }),
			expect.objectContaining({ ruleId: 'user-john', canRemove: true }),
		]))

		table.toggleCrudRowSelection('group-finance', true)
		expect(table.selectedCrudRowsCount.value).toBe(0)

		table.toggleVisibleCrudRowsSelection(true)
		expect([...table.selectedCrudRuleIds.value]).toEqual(['user-john'])
		expect(table.allVisibleCrudRowsSelected.value).toBe(true)
	})
})
