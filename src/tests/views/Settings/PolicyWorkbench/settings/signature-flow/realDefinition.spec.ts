/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { signatureFlowRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-flow/realDefinition'

describe('signatureFlowRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(signatureFlowRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('keeps the instance default fallback and summarizes known modes', () => {
		expect(signatureFlowRealDefinition.getFallbackSystemDefault(null, 'group')).toBe('none')
		expect(signatureFlowRealDefinition.summarizeValue('parallel')).toBe('Parallel')
		expect(signatureFlowRealDefinition.summarizeValue('ordered_numeric')).toBe('Sequential')
		expect(signatureFlowRealDefinition.summarizeValue('none')).toBe('Using instance default')
	})

	it('normalizes invalid draft values to parallel while keeping invalid values non-selectable', () => {
		expect(signatureFlowRealDefinition.normalizeDraftValue('banana')).toBe('parallel')
		expect(signatureFlowRealDefinition.hasSelectableDraftValue('banana')).toBe(false)
		expect(signatureFlowRealDefinition.hasSelectableDraftValue('ordered_numeric')).toBe(true)
	})
})
