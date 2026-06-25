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

import { signatureFooterRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-footer/realDefinition'

describe('signatureFooterRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(signatureFooterRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})
})
