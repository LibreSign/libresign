/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const { identifyMethodsState } = vi.hoisted(() => ({
	identifyMethodsState: {
		policies: {
			identify_methods: {
				effectiveValue: [] as unknown[],
			},
		},
	},
}))

const { identifyMethodsCatalogState } = vi.hoisted(() => ({
	identifyMethodsCatalogState: [] as unknown[],
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return identifyMethodsState
		}

		if (key === 'identify_methods_catalog') {
			return identifyMethodsCatalogState
		}

		return defaultValue
	}),
}))

import { identifyMethodsRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/identify-methods/realDefinition'

describe('identifyMethodsRealDefinition', () => {
	beforeEach(() => {
		identifyMethodsState.policies.identify_methods.effectiveValue = []
		identifyMethodsCatalogState.splice(0, identifyMethodsCatalogState.length)
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

	it('falls back to identify methods catalog when effective policy factors are empty', () => {
		identifyMethodsCatalogState.push(
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					clickToSign: { enabled: true },
				},
			},
		)

		const value = JSON.parse(String(identifyMethodsRealDefinition.createEmptyValue()))
		expect(value.factors).toHaveLength(1)
		expect(value.factors[0]).toMatchObject({
			name: 'account',
			enabled: true,
			requirement: 'optional',
		})
	})
})
