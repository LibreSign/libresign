/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	mergeIdentifyMethodsEntriesWithCatalog,
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

	it('uses canonical requirement from payload', () => {
		const normalized = normalizeIdentifyMethodsPolicy([
			{
				name: 'email',
				enabled: true,
				requirement: 'required',
				signatureMethods: ['emailToken'],
			},
		] as never)

		expect(normalized).toHaveLength(1)
		expect(normalized[0].requirement).toBe('required')
	})

	it('preserves canonical requirement during serialization', () => {
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
					requirement: 'required',
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
				minimumTotalVerifiedFactors: undefined,
				signatureMethodEnabled: undefined,
			},
			{
				name: 'sms',
				enabled: true,
				signatureMethods: {},
				friendly_name: undefined,
				requirement: undefined,
				minimumTotalVerifiedFactors: undefined,
				signatureMethodEnabled: undefined,
			},
		])
	})

	it('merges editor entries with the admin catalog so omitted methods stay visible', () => {
		const merged = mergeIdentifyMethodsEntriesWithCatalog([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					password: { enabled: true },
				},
				signatureMethodEnabled: 'password',
			},
		], [
			{
				name: 'account',
				friendly_name: 'Account',
				enabled: true,
				requirement: 'required',
				signatureMethods: {
					clickToSign: { enabled: false, label: 'One-click confirmation' },
					password: { enabled: true, label: 'Certificate with password' },
				},
				signatureMethodEnabled: 'password',
			},
			{
				name: 'email',
				friendly_name: 'Email',
				enabled: false,
				requirement: 'required',
				signatureMethods: {
					emailToken: { enabled: true, label: 'Email code' },
				},
				signatureMethodEnabled: 'emailToken',
			},
		])

		expect(merged).toHaveLength(2)
		expect(merged[0]).toMatchObject({
			name: 'account',
			friendly_name: 'Account',
			enabled: true,
			signatureMethodEnabled: 'password',
		})
		expect(merged[0].signatureMethods).toEqual({
			clickToSign: { enabled: false, label: 'One-click confirmation' },
			password: { enabled: true, label: 'Certificate with password' },
		})
		expect(merged[1]).toMatchObject({
			name: 'email',
			friendly_name: 'Email',
			enabled: false,
			signatureMethodEnabled: 'emailToken',
		})
	})
})
