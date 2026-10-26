/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { emailSignerSignatureRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/email-signer-signature/realDefinition'
import { resolveEmailSignerSignature } from '../../../../../../views/Settings/PolicyWorkbench/settings/email-signer-signature/model'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('emailSignerSignatureRealDefinition', () => {
	it('is a system-only setting disabled by default', () => {
		expect(emailSignerSignatureRealDefinition.key).toBe('persist_email_signer_signatures')
		expect(emailSignerSignatureRealDefinition.supportedScopes).toEqual(['system'])
		expect(emailSignerSignatureRealDefinition.createEmptyValue()).toBe(false)
		expect(emailSignerSignatureRealDefinition.getFallbackSystemDefault(null, null)).toBe(false)
		expect(emailSignerSignatureRealDefinition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(emailSignerSignatureRealDefinition.groupAdminBehavior?.canRenderPolicy?.(null)).toBe(false)
	})

	it('keeps the stored system value as fallback default', () => {
		expect(emailSignerSignatureRealDefinition.getFallbackSystemDefault(true, 'system')).toBe(true)
		expect(emailSignerSignatureRealDefinition.getFallbackSystemDefault(true, 'group')).toBe(false)
	})

	it.each([
		[true, 'Enabled'],
		['1', 'Enabled'],
		[false, 'Disabled'],
		['', 'Disabled'],
		['unexpected', 'Not configured'],
	])('summarizes %s as %s', (value, summary) => {
		expect(emailSignerSignatureRealDefinition.summarizeValue(value)).toBe(summary)
	})

	it('normalizes draft values to a boolean', () => {
		expect(emailSignerSignatureRealDefinition.normalizeDraftValue('true')).toBe(true)
		expect(emailSignerSignatureRealDefinition.normalizeDraftValue('unexpected')).toBe(false)
		expect(emailSignerSignatureRealDefinition.hasSelectableDraftValue('unexpected')).toBe(false)
	})
})

describe('resolveEmailSignerSignature', () => {
	it.each([
		[true, true],
		[1, true],
		[' TRUE ', true],
		[false, false],
		[0, false],
		['0', false],
		[2, null],
		['maybe', null],
		[null, null],
	])('resolves %s to %s', (value, expected) => {
		expect(resolveEmailSignerSignature(value)).toBe(expected)
	})
})
