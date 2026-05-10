/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const { identifyMethodsState } = vi.hoisted(() => ({
	identifyMethodsState: [] as unknown[],
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'identify_methods') {
			return identifyMethodsState
		}

		return defaultValue
	}),
}))

import { identifyMethodsRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/identify-methods/realDefinition'

describe('identifyMethodsRealDefinition', () => {
	beforeEach(() => {
		identifyMethodsState.length = 0
	})

	it('reads identify_methods from initial state at create-time, not module-load time', () => {
		const first = JSON.parse(String(identifyMethodsRealDefinition.createEmptyValue()))
		expect(first.factors).toEqual([])

		identifyMethodsState.push({
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
			mandatory: false,
		})
	})
})
