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
				effectiveValue: [
					{
						name: 'account',
						enabled: true,
						requirement: 'required',
						signatureMethods: {
							clickToSign: { enabled: true },
							emailToken: { enabled: false },
							password: { enabled: false },
						},
						signatureMethodEnabled: 'clickToSign',
					},
					{
						name: 'email',
						enabled: true,
						requirement: 'optional',
						signatureMethods: {
							emailToken: { enabled: true },
							clickToSign: { enabled: false },
						},
						signatureMethodEnabled: 'emailToken',
					},
				] as unknown[],
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

vi.mock('@nextcloud/initial-state', () => ({
	loadState: loadStateMock,
}))

import { identifyMethodsRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/identify-methods/realDefinition'

describe('identifyMethodsRealDefinition', () => {
	beforeEach(() => {
		identifyMethodsState.policies.identify_methods.effectiveValue = [
			{
				name: 'account',
				enabled: true,
				requirement: 'required',
				signatureMethods: {
					clickToSign: { enabled: true },
					emailToken: { enabled: false },
					password: { enabled: false },
				},
				signatureMethodEnabled: 'clickToSign',
			},
			{
				name: 'email',
				enabled: true,
				requirement: 'optional',
				signatureMethods: {
					emailToken: { enabled: true },
					clickToSign: { enabled: false },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]
		loadStateMock.mockClear()
	})

	it('reads identify_methods from effective policies at create-time, not module-load time', () => {
		const first = JSON.parse(String(identifyMethodsRealDefinition.createEmptyValue()))
		expect(first.factors).toHaveLength(2)
		expect(first.factors[0].name).toBe('account')
		expect(first.factors[1].name).toBe('email')

		identifyMethodsState.policies.identify_methods.effectiveValue = [
			{
				name: 'email',
				enabled: true,
				requirement: 'required',
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]

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
