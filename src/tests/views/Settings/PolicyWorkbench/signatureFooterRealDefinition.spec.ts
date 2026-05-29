/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { signatureFooterRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/realDefinition'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
}))

const SYSTEM_DEFAULT_TEMPLATE = 'SYSTEM_DEFAULT_TEMPLATE'
const SYSTEM_DEFAULT_POLICY = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"SYSTEM_DEFAULT_TEMPLATE","previewWidth":595,"previewHeight":100,"previewZoom":100}'

describe('signatureFooterRealDefinition.resolveEditorProps', () => {
	it('keeps empty base editor props until policy payload provides the canonical default', () => {
		expect(signatureFooterRealDefinition.editorProps).toMatchObject({
			inheritedTemplate: '',
		})
	})

	it('uses the canonical system footer template from policy meta.defaultSystemValue for system scope', () => {
		const baseEditorProps = {
			inheritedTemplate: '',
		}
		const policy = {
			sourceScope: 'system',
			effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"CURRENT_EFFECTIVE_TEMPLATE","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			meta: {
				defaultSystemValue: SYSTEM_DEFAULT_POLICY,
			},
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		expect(resolved).toMatchObject({
			inheritedTemplate: SYSTEM_DEFAULT_TEMPLATE,
		})
	})

	it('falls back to base inheritedTemplate when policy inheritedValue has no custom footer template', () => {
		const baseEditorProps = {
			inheritedTemplate: '',
		}
		const policy = {
			meta: {
				defaultSystemValue: SYSTEM_DEFAULT_POLICY,
			},
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		// When the inherited level has no custom template, the canonical default from the policy payload must be preserved.
		expect(resolved).toMatchObject({
			inheritedTemplate: SYSTEM_DEFAULT_TEMPLATE,
		})
	})

	it('uses inherited policy footer template when non-empty and overrides base', () => {
		const baseEditorProps = {
			inheritedTemplate: '',
		}
		const policy = {
			meta: {
				defaultSystemValue: SYSTEM_DEFAULT_POLICY,
			},
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"ADMIN_CUSTOM_TEMPLATE","previewWidth":595,"previewHeight":100,"previewZoom":100}',
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		// When the inherited level has a real custom template, that takes precedence over the system default
		expect(resolved).toMatchObject({
			inheritedTemplate: 'ADMIN_CUSTOM_TEMPLATE',
		})
	})

	it('keeps base inheritedTemplate when policy exists but has no inheritedValue property', () => {
		const baseEditorProps = {
			inheritedTemplate: 'BASE_TEMPLATE',
		}
		const policy = {
			effectiveValue: 'parallel',
			meta: {
				defaultSystemValue: SYSTEM_DEFAULT_POLICY,
			},
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		// Policy without inheritedValue property still uses the canonical default from the policy payload.
		expect(resolved).toMatchObject({
			inheritedTemplate: SYSTEM_DEFAULT_TEMPLATE,
		})
	})

	it('keeps base inheritedTemplate when policy is null', () => {
		const baseEditorProps = {
			inheritedTemplate: 'BASE_TEMPLATE',
		}

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(null, baseEditorProps)

		expect(resolved).toEqual({
			inheritedTemplate: 'BASE_TEMPLATE',
		})
	})

	it('returns canonical policy default as fallback system value', () => {
		const fallback = signatureFooterRealDefinition.getFallbackSystemDefault(
			null,
			'system',
			{
				meta: {
					defaultSystemValue: SYSTEM_DEFAULT_POLICY,
				},
			} as unknown as Parameters<typeof signatureFooterRealDefinition.getFallbackSystemDefault>[2],
		)

		expect(fallback).toBe(SYSTEM_DEFAULT_POLICY)
	})
})
