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

import { signatureHashAlgorithmRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-hash-algorithm/realDefinition'

describe('signatureHashAlgorithmRealDefinition', () => {
	it('keeps SHA256 as the secure fallback summary default', () => {
		expect(signatureHashAlgorithmRealDefinition.getFallbackSystemDefault(null, null)).toBe('SHA256')
	})

	it('allows delegated group admins to create descendant rules', () => {
		expect(signatureHashAlgorithmRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('hides non-removable delegated group seed rules', () => {
		expect(signatureHashAlgorithmRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: true,
		} as never)).toBe(true)
	})
})
