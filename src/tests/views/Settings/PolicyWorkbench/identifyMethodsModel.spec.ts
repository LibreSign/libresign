/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeIdentifyMethodsPolicy,
	serializeIdentifyMethodsPolicy,
} from '../../../../views/Settings/PolicyWorkbench/settings/identify-methods/model'

describe('identify-methods model compatibility', () => {
	it('parses legacy availableSignatureMethods for account', () => {
		const normalized = normalizeIdentifyMethodsPolicy([
			{
				name: 'account',
				enabled: true,
				availableSignatureMethods: ['clickToSign', 'emailToken', 'password'],
			},
		] as never)

		expect(normalized).toHaveLength(1)
		expect(Object.keys(normalized[0].signatureMethods)).toEqual(['clickToSign', 'emailToken', 'password'])
	})

	it('parses signatureMethods when provided as a list', () => {
		const normalized = normalizeIdentifyMethodsPolicy([
			{
				name: 'account',
				enabled: true,
				signatureMethods: ['clickToSign', 'password'],
			},
		] as never)

		expect(normalized).toHaveLength(1)
		expect(Object.keys(normalized[0].signatureMethods)).toEqual(['clickToSign', 'password'])
	})

	it('derives canonical requirement from legacy mandatory flag', () => {
		const normalized = normalizeIdentifyMethodsPolicy([
			{
				name: 'email',
				enabled: true,
				mandatory: true,
				signatureMethods: ['emailToken'],
			},
		] as never)

		expect(normalized).toHaveLength(1)
		expect(normalized[0].requirement).toBe('required')
	})

	it('preserves canonical requirement during serialization while mirroring mandatory for compatibility', () => {
		const serialized = serializeIdentifyMethodsPolicy([
			{
				name: 'whatsapp',
				enabled: true,
				requirement: 'optional',
				minimumTotalVerifiedFactors: 2,
				signatureMethods: {
					whatsappToken: { enabled: true },
				},
				signatureMethodEnabled: 'whatsappToken',
			},
		])

		expect(JSON.parse(serialized)).toEqual([
			{
				name: 'whatsapp',
				enabled: true,
				requirement: 'optional',
				mandatory: false,
				minimumTotalVerifiedFactors: 2,
				signatureMethods: {
					whatsappToken: { enabled: true },
				},
				signatureMethodEnabled: 'whatsappToken',
			},
		])
	})

	it('normalizes shared minimumTotalVerifiedFactors from object-shaped payload', () => {
		const normalized = normalizeIdentifyMethodsPolicy({
			minimumTotalVerifiedFactors: 2,
			factors: [
				{
					name: 'email',
					enabled: true,
					mandatory: true,
					signatureMethods: ['emailToken'],
				},
			],
		} as never)

		expect(normalized).toHaveLength(1)
		expect(normalized[0].minimumTotalVerifiedFactors).toBe(2)
	})
})
