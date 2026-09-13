/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../testHelpers/l10n.js'
import { getObserverPrivateValidationWarningMessage } from '../../../../../views/Settings/PolicyWorkbench/settings/observerValidationAccessConflict'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('observerValidationAccessConflict', () => {
	it('returns the shared administrator warning copy', () => {
		expect(getObserverPrivateValidationWarningMessage()).toContain('Email observers receive a link')
		expect(getObserverPrivateValidationWarningMessage()).toContain('authenticated-only validation')
	})
})
