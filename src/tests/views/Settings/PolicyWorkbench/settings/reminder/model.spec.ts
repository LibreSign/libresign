/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeReminderPolicyConfig,
	REMINDER_POLICY_DEFAULTS,
	REMINDER_POLICY_ENABLED_PRESET,
	serializeReminderPolicyConfig,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/reminder/model'

describe('reminder model', () => {
	it('returns defaults for empty payloads', () => {
		expect(normalizeReminderPolicyConfig('')).toEqual({
			...REMINDER_POLICY_DEFAULTS,
			send_timer: '',
		})
		expect(normalizeReminderPolicyConfig(null as never)).toEqual({
			...REMINDER_POLICY_DEFAULTS,
			send_timer: '',
		})
	})

	it('normalizes structured payloads', () => {
		expect(normalizeReminderPolicyConfig('{"days_before":"2","days_between":3,"max":"4","send_timer":"09:45"}')).toEqual({
			days_before: 2,
			days_between: 3,
			max: 4,
			send_timer: '09:45',
		})
	})

	it('keeps explicit empty send_timer and clamps numeric values to non-negative integers', () => {
		expect(normalizeReminderPolicyConfig({
			days_before: -5,
			days_between: 'invalid',
			max: 3,
			send_timer: '',
		} as never)).toEqual({
			days_before: 0,
			days_between: 0,
			max: 3,
			send_timer: '',
		})
	})

	it('falls back to 10:00 when send_timer is invalid', () => {
		expect(normalizeReminderPolicyConfig({
			...REMINDER_POLICY_ENABLED_PRESET,
			send_timer: 'invalid',
		})).toEqual({
			...REMINDER_POLICY_ENABLED_PRESET,
			send_timer: '10:00',
		})
	})

	it('serializes canonical normalized payloads', () => {
		expect(serializeReminderPolicyConfig({
			days_before: 2,
			days_between: 3,
			max: 4,
			send_timer: '09:45',
		})).toBe('{"days_before":2,"days_between":3,"max":4,"send_timer":"09:45"}')
	})
})
