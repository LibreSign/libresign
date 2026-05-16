/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (_app: string, key: string, fallback: unknown) => {
		if (key === 'effective_policies') {
			return {
				policies: {
					add_footer: {
						effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"SYSTEM_DEFAULT_TEMPLATE","previewWidth":595,"previewHeight":100,"previewZoom":100}',
					},
				},
			}
		}

		return fallback
	},
}))

import { signatureFooterRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/realDefinition'

describe('signatureFooterRealDefinition.resolveEditorProps', () => {
	it('falls back to base inheritedTemplate when policy inheritedValue has no custom footer template', () => {
		const baseEditorProps = {
			inheritedTemplate: 'SYSTEM_DEFAULT_TEMPLATE',
		}
		const policy = {
			inheritedValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		// When the inherited level has no custom template, the system-level default from loadState must be preserved
		expect(resolved).toMatchObject({
			inheritedTemplate: 'SYSTEM_DEFAULT_TEMPLATE',
		})
	})

	it('uses inherited policy footer template when non-empty and overrides base', () => {
		const baseEditorProps = {
			inheritedTemplate: 'SYSTEM_DEFAULT_TEMPLATE',
		}
		const policy = {
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
		} as unknown as Parameters<NonNullable<typeof signatureFooterRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureFooterRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		// Policy without inheritedValue property → do not override base
		expect(resolved).toMatchObject({
			inheritedTemplate: 'BASE_TEMPLATE',
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
})
