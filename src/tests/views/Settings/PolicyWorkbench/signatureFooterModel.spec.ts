/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	buildFooterTemplateSourceOptions,
	getDefaultSignatureFooterPolicyConfig,
	normalizeSignatureFooterPolicyConfig,
	resolveFooterPolicyPayloadForRequest,
	serializeSignatureFooterPolicyConfig,
} from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/model'

const footerTemplateLabels = {
	mySavedTemplate: 'My saved footer template',
	configuredTemplate: 'Configured footer template',
	defaultTemplate: 'Default footer template',
}

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

	it('builds effective and inherited footer template source options', () => {
		const options = buildFooterTemplateSourceOptions({
			effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>My footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Team footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			sourceScope: 'user',
		}, footerTemplateLabels)

		expect(options).toHaveLength(2)
		expect(options[0]).toMatchObject({
			value: 'effective',
			label: 'My saved footer template',
		})
		expect(options[1]).toMatchObject({
			value: 'inherited',
			label: 'Default footer template',
		})
	})

	it('deduplicates inherited option when serialized value matches effective option', () => {
		const sharedTemplate = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Same</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}'
		const options = buildFooterTemplateSourceOptions({
			effectiveValue: sharedTemplate,
			inheritedValue: sharedTemplate,
			sourceScope: 'group',
		}, footerTemplateLabels)

		expect(options).toHaveLength(1)
		expect(options[0]).toMatchObject({
			value: 'effective',
			label: 'Configured footer template',
		})
	})

	it('returns empty options when there is no custom template in effective or inherited values', () => {
		const options = buildFooterTemplateSourceOptions({
			effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			sourceScope: 'system',
		}, footerTemplateLabels)

		expect(options).toEqual([])
	})

	it('resolves selected footer payload when request-level override is allowed', () => {
		const options = buildFooterTemplateSourceOptions({
			effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Personal template</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Group template</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			sourceScope: 'user',
		}, footerTemplateLabels)

		expect(resolveFooterPolicyPayloadForRequest(true, options, 'inherited')).toContain('Group template')
		expect(resolveFooterPolicyPayloadForRequest(true, options, 'effective')).toContain('Personal template')
	})

	it('returns null footer payload when request-level override is disallowed or selected option does not exist', () => {
		const options = buildFooterTemplateSourceOptions({
			effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Personal template</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			sourceScope: 'user',
		}, footerTemplateLabels)

		expect(resolveFooterPolicyPayloadForRequest(false, options, 'effective')).toBeNull()
		expect(resolveFooterPolicyPayloadForRequest(true, [], 'effective')).toBeNull()
	})
})
