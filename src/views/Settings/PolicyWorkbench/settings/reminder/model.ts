/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type ReminderPolicyConfig = {
	days_before: number
	days_between: number
	max: number
	send_timer: string
}

/** Default disabled reminder policy value. */
export const REMINDER_POLICY_DEFAULTS: ReminderPolicyConfig = {
	days_before: 0,
	days_between: 0,
	max: 0,
	send_timer: '10:00',
}

/** Suggested reminder values when enabling reminders from the editor. */
export const REMINDER_POLICY_ENABLED_PRESET: ReminderPolicyConfig = {
	days_before: 2,
	days_between: 5,
	max: 3,
	send_timer: '10:00',
}

/**
 * Normalizes a raw effective policy value to the canonical reminder payload.
 *
 * @param value Raw effective policy value from API/runtime.
 */
export function normalizeReminderPolicyConfig(value: EffectivePolicyValue): ReminderPolicyConfig {
	const normalized = coerceObject(value)

	return {
		days_before: toNonNegativeInt(normalized.days_before, REMINDER_POLICY_DEFAULTS.days_before),
		days_between: toNonNegativeInt(normalized.days_between, REMINDER_POLICY_DEFAULTS.days_between),
		max: toNonNegativeInt(normalized.max, REMINDER_POLICY_DEFAULTS.max),
		send_timer: normalizeSendTimer(normalized.send_timer, REMINDER_POLICY_DEFAULTS.send_timer),
	}
}

/**
 * Serializes reminder policy config to canonical JSON string payload.
 *
 * @param config Reminder policy value to serialize.
 */
export function serializeReminderPolicyConfig(config: ReminderPolicyConfig): string {
	return JSON.stringify(normalizeReminderPolicyConfig(config as unknown as EffectivePolicyValue))
}

/**
 * Coerces an effective policy value to an object payload.
 *
 * @param value Raw effective policy value.
 */
function coerceObject(value: EffectivePolicyValue): Record<string, unknown> {
	if (typeof value === 'string') {
		const trimmed = value.trim()
		if (trimmed.length > 0) {
			try {
				const parsed = JSON.parse(trimmed)
				if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
					return parsed as Record<string, unknown>
				}
			} catch {
				return {}
			}
		}

		return {}
	}

	if (value && typeof value === 'object' && !Array.isArray(value)) {
		return value as Record<string, unknown>
	}

	return {}
}

/**
 * Converts an unknown value to a non-negative integer.
 *
 * @param value Candidate numeric value.
 * @param fallback Fallback integer used when value is invalid.
 */
function toNonNegativeInt(value: unknown, fallback: number): number {
	const parsed = Number.parseInt(String(value), 10)
	if (!Number.isFinite(parsed)) {
		return fallback
	}

	return Math.max(0, parsed)
}

/**
 * Normalizes send timer to HH:mm format, preserving explicit empty string.
 *
 * @param value Candidate time value.
 * @param fallback Fallback HH:mm value.
 */
function normalizeSendTimer(value: unknown, fallback: string): string {
	if (value === '' || value === null || value === undefined) {
		return ''
	}

	const normalized = String(value).trim()
	if (/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(normalized)) {
		return normalized
	}

	return fallback
}
