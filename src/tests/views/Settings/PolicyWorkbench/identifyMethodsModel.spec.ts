/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeIdentifyMethodsPolicyConfig,
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

		expect(JSON.parse(serialized)).toEqual({
			factors: [
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
			],
		})
	})

	it('normalizes global account-creation setting from object payload', () => {
		const normalized = normalizeIdentifyMethodsPolicyConfig({
			can_create_account: false,
			factors: [
				{
					name: 'email',
					enabled: true,
					signatureMethods: ['emailToken'],
				},
			],
		} as never)

		expect(normalized.global.canCreateAccount).toBe(false)
		expect(normalized.factors).toHaveLength(1)
	})

	it('serializes global account-creation setting at policy root', () => {
		const serialized = serializeIdentifyMethodsPolicy([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true },
				},
			},
		], {
			canCreateAccount: false,
		})

		expect(JSON.parse(serialized)).toEqual({
			can_create_account: false,
			factors: [
				{
					name: 'email',
					enabled: true,
					signatureMethods: {
						emailToken: { enabled: true },
					},
				},
			],
		})
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

	it('defaults enabled to true when the payload omits it', () => {
		const normalized = normalizeIdentifyMethodsPolicy([
			{
				name: 'email',
				signatureMethods: ['emailToken'],
			},
		] as never)

		expect(normalized).toHaveLength(1)
		expect(normalized[0].enabled).toBe(true)
	})

	it('accepts shared minimumTotalVerifiedFactors when it comes as a numeric string', () => {
		const normalized = normalizeIdentifyMethodsPolicy({
			minimumTotalVerifiedFactors: '2',
			factors: [
				{
					name: 'sms',
					enabled: true,
					signatureMethods: ['smsToken'],
				},
			],
		} as never)

		expect(normalized).toHaveLength(1)
		expect(normalized[0].minimumTotalVerifiedFactors).toBe(2)
	})

	it('normalizes legacy string-list payloads into canonical entries', () => {
		const normalized = normalizeIdentifyMethodsPolicy(['email', 'sms'] as never)

		expect(normalized).toEqual([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {},
				friendly_name: undefined,
				requirement: undefined,
				mandatory: undefined,
				minimumTotalVerifiedFactors: undefined,
				signatureMethodEnabled: undefined,
			},
			{
				name: 'sms',
				enabled: true,
				signatureMethods: {},
				friendly_name: undefined,
				requirement: undefined,
				mandatory: undefined,
				minimumTotalVerifiedFactors: undefined,
				signatureMethodEnabled: undefined,
			},
		])
	})
})
