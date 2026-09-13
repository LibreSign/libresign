/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../testHelpers/l10n.js'
import {
	getObserverPrivateValidationWarningMessage,
	isEnabledPolicyValue,
} from '../../../../../views/Settings/PolicyWorkbench/settings/observerValidationAccessConflict'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('observerValidationAccessConflict', () => {
	it.each([
		{ value: true, expected: true },
		{ value: false, expected: false },
		{ value: 1, expected: false },
		{ value: '1', expected: false },
		{ value: 'true', expected: false },
		{ value: null, expected: false },
		{ value: undefined, expected: false },
	])('isEnabledPolicyValue($value) => $expected', ({ value, expected }) => {
		expect(isEnabledPolicyValue(value)).toBe(expected)
	})

	it('returns the shared administrator warning copy', () => {
		expect(getObserverPrivateValidationWarningMessage()).toContain('Email observers receive a link')
		expect(getObserverPrivateValidationWarningMessage()).toContain('authenticated-only validation')
	})
})
