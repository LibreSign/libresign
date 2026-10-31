/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { notificationDeliveryRealDefinition as definition } from '../../../../../../views/Settings/PolicyWorkbench/settings/notification-delivery/realDefinition'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('notificationDeliveryRealDefinition', () => {
	it('uses sync by default and permits explicit sync', () => {
		expect(definition.createEmptyValue()).toBe('sync')
		expect(definition.normalizeDraftValue('sync')).toBe('sync')
		expect(definition.getFallbackSystemDefault('sync', 'system')).toBe('sync')
		expect(definition.getFallbackSystemDefault(null, null)).toBe('sync')
	})

	it('supports async and preserves the system value', () => {
		expect(definition.normalizeDraftValue('async')).toBe('async')
		expect(definition.getFallbackSystemDefault('async', 'system')).toBe('async')
		expect(definition.getFallbackSystemDefault('async', 'user')).toBe('sync')
	})

	it('is restricted to system scope and cannot be overridden', () => {
		expect(definition.supportedScopes).toEqual(['system'])
		expect(definition.groupAdminBehavior?.canRenderPolicy?.(null)).toBe(false)
		expect(definition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(definition.personalPreferenceBehavior).toBeUndefined()
		expect(definition.compound).toBeUndefined()
	})
})
