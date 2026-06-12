/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { requestSignGroupsRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/request-sign-groups/realDefinition'

describe('requestSignGroupsRealDefinition', () => {
	it('describes delegated request access using managed groups', () => {
		expect(requestSignGroupsRealDefinition.description).toBe('Define which groups may create signature requests within this scope. Delegated group admins may authorize only groups they manage.')
	})

	it('allows overriding child customization at system and group scopes', () => {
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('system', true)).toBe(true)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('system', false)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('group', true)).toBe(true)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('group', false)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('user', true)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('user', false)).toBe(false)
	})

	it('formats allow-override summary for both delegation states', () => {
		expect(requestSignGroupsRealDefinition.formatAllowOverride(true)).toBe('Group admins can define scope-specific requester groups')
		expect(requestSignGroupsRealDefinition.formatAllowOverride(false)).toBe('Group admins must inherit the system requester groups')
	})

	it('normalizes empty draft and group-target seeding using composed payload', () => {
		expect(requestSignGroupsRealDefinition.createEmptyValue()).toBe('{"allowGroups":[],"denyGroups":[]}')

		const seeded = requestSignGroupsRealDefinition.syncCreateDraftValueFromTargets?.(
			'group',
			['finance'],
			'{"allowGroups":[],"denyGroups":[]}',
			true,
		)

		expect(seeded).toBe('{"allowGroups":["finance"],"denyGroups":[]}')
	})

	it('keeps denied groups while seeding allow groups from targets', () => {
		const seeded = requestSignGroupsRealDefinition.syncCreateDraftValueFromTargets?.(
			'group',
			['finance'],
			'{"allowGroups":[],"denyGroups":["legal"]}',
			true,
		)

		expect(seeded).toBe('{"allowGroups":["finance"],"denyGroups":["legal"]}')
	})

	it('accepts deny-only payload as selectable draft value', () => {
		expect(requestSignGroupsRealDefinition.hasSelectableDraftValue('{"allowGroups":[],"denyGroups":["board"]}')).toBe(true)
		expect(requestSignGroupsRealDefinition.hasSelectableDraftValue('{"allowGroups":[],"denyGroups":[]}')).toBe(false)
	})

	it('summarizes overlap case as deny-only when allow groups are fully denied', () => {
		expect(requestSignGroupsRealDefinition.summarizeValue('{"allowGroups":["board"],"denyGroups":["board"]}')).toBe('board')
	})

	it('keeps composed allow/deny summary when deny does not fully shadow allow', () => {
		expect(requestSignGroupsRealDefinition.summarizeValue('{"allowGroups":["admin","board"],"denyGroups":["board"]}')).toBe('{allowCount} allow · {denyCount} deny')
	})
})
