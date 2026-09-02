/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const MAIL_SENDER_STRATEGIES = ['system', 'requester'] as const
export type MailSenderStrategy = typeof MAIL_SENDER_STRATEGIES[number]

export const DEFAULT_MAIL_SENDER_STRATEGY: MailSenderStrategy = 'system'

/**
 * Type guard for the values accepted by the mail_sender_strategy policy.
 *
 * @param value Candidate value
 */
export function isMailSenderStrategy(value: unknown): value is MailSenderStrategy {
	return MAIL_SENDER_STRATEGIES.includes(value as MailSenderStrategy)
}

/**
 * Normalizes any effective policy value to a known strategy, defaulting to the system mailer.
 *
 * @param value Effective policy value coming from the backend or the editor
 */
export function normalizeMailSenderStrategy(value: EffectivePolicyValue): MailSenderStrategy {
	if (typeof value === 'string') {
		const normalized = value.trim().toLowerCase()
		if (isMailSenderStrategy(normalized)) {
			return normalized
		}
	}

	return DEFAULT_MAIL_SENDER_STRATEGY
}
