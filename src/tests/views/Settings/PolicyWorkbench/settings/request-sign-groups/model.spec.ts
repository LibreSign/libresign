/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_REQUEST_SIGN_DENY_GROUPS,
	DEFAULT_REQUEST_SIGN_GROUPS,
	resolveDeniedRequestSignGroups,
	resolveRequestSignGroups,
	resolveRequestSignGroupsPolicy,
	serializeRequestSignGroups,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/request-sign-groups/model'

describe('request-sign-groups model', () => {
	it('exposes canonical defaults', () => {
		expect(DEFAULT_REQUEST_SIGN_GROUPS).toEqual(['admin'])
		expect(DEFAULT_REQUEST_SIGN_DENY_GROUPS).toEqual([])
	})

	it('normalizes canonical JSON payloads by trimming, deduplicating, and sorting allow/deny groups', () => {
		expect(resolveRequestSignGroupsPolicy('{"allowGroups":[" finance ","admin","finance"],"denyGroups":[" legal ","admin",""]}')).toEqual({
			allowGroups: ['admin', 'finance'],
			denyGroups: ['admin', 'legal'],
		})
	})

	it('normalizes native object payloads and ignores invalid members', () => {
		expect(resolveRequestSignGroupsPolicy({
			allowGroups: [' board ', 'admin', 'board', 123] as never,
			denyGroups: [' legal ', '', null] as never,
		})).toEqual({
			allowGroups: ['admin', 'board'],
			denyGroups: ['legal'],
		})
	})

	it('rejects legacy list and invalid string payloads back to empty canonical state', () => {
		expect(resolveRequestSignGroupsPolicy('["admin"]')).toEqual({
			allowGroups: [],
			denyGroups: [],
		})
		expect(resolveRequestSignGroupsPolicy('not-json')).toEqual({
			allowGroups: [],
			denyGroups: [],
		})
	})

	it('derives allow and deny groups from canonical payloads', () => {
		const value = '{"allowGroups":["finance"],"denyGroups":["board"]}'
		expect(resolveRequestSignGroups(value)).toEqual(['finance'])
		expect(resolveDeniedRequestSignGroups(value)).toEqual(['board'])
	})

	it('serializes back to canonical JSON object shape', () => {
		expect(serializeRequestSignGroups([' finance ', 'admin', 'finance'] as never)).toBe('{"allowGroups":[],"denyGroups":[]}')
		expect(serializeRequestSignGroups({
			allowGroups: ['company', ' admin '],
			denyGroups: [' legal ', 'company'],
		})).toBe('{"allowGroups":["admin","company"],"denyGroups":["company","legal"]}')
	})
})
