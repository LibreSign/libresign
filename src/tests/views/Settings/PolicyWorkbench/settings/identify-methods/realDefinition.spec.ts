/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const { identifyMethodsState } = vi.hoisted(() => ({
	identifyMethodsState: {
		policies: {
			identify_methods: {
				effectiveValue: [] as unknown[],
			},
		},
	},
}))

const { loadStateMock } = vi.hoisted(() => ({
	loadStateMock: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return identifyMethodsState
		}

		return defaultValue
	}),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: loadStateMock,
}))

import { identifyMethodsRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/identify-methods/realDefinition'

describe('identifyMethodsRealDefinition', () => {
	beforeEach(() => {
		identifyMethodsState.policies.identify_methods.effectiveValue = []
		loadStateMock.mockClear()
	})

	it('supports instance, group, and account rule scopes', () => {
		expect(identifyMethodsRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create descendant rules', () => {
		expect(identifyMethodsRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('reads identify_methods from effective policies at create-time, not module-load time', () => {
		const first = JSON.parse(String(identifyMethodsRealDefinition.createEmptyValue()))
		expect(first.factors).toEqual([])

		identifyMethodsState.policies.identify_methods.effectiveValue.push({
			name: 'email',
			enabled: true,
			requirement: 'required',
			signatureMethods: {
				emailToken: { enabled: true },
			},
			signatureMethodEnabled: 'emailToken',
		})

		const second = JSON.parse(String(identifyMethodsRealDefinition.createEmptyValue()))
		expect(second.factors).toHaveLength(1)
		expect(second.factors[0]).toMatchObject({
			name: 'email',
			enabled: true,
			requirement: 'optional',
		})
	})

	it('does not read identify_methods_catalog initial state', () => {
		identifyMethodsRealDefinition.createEmptyValue()

		expect(loadStateMock).not.toHaveBeenCalledWith(
			'libresign',
			'identify_methods_catalog',
			expect.anything(),
		)
	})
})
