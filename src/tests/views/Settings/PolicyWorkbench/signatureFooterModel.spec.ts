/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	getDefaultSignatureFooterPolicyConfig,
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/model'

describe('signature-footer model', () => {
	it('normalizes legacy boolean values', () => {
		expect(normalizeSignatureFooterPolicyConfig(true)).toEqual({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 595,
			previewHeight: 100,
			previewZoom: 100,
		})

		expect(normalizeSignatureFooterPolicyConfig('0')).toEqual({
			enabled: false,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 595,
			previewHeight: 100,
			previewZoom: 100,
		})
	})

	it('normalizes structured JSON payload', () => {
		const payload = '{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true,"footerTemplate":"{{ signer.displayName }}","previewWidth":640,"previewHeight":120,"previewZoom":130}'
		expect(normalizeSignatureFooterPolicyConfig(payload)).toEqual({
			enabled: true,
			writeQrcodeOnFooter: false,
			validationSite: 'https://validation.example',
			customizeFooterTemplate: true,
			footerTemplate: '{{ signer.displayName }}',
			previewWidth: 640,
			previewHeight: 120,
			previewZoom: 130,
		})
	})

	it('serializes canonical payload from config object', () => {
		const serialized = serializeSignatureFooterPolicyConfig({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 700,
			previewHeight: 110,
			previewZoom: 120,
		})

		expect(serialized).toBe('{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":700,"previewHeight":110,"previewZoom":120}')
	})

	it('returns defaults when payload is empty', () => {
		expect(normalizeSignatureFooterPolicyConfig('')).toEqual(getDefaultSignatureFooterPolicyConfig())
	})
})
