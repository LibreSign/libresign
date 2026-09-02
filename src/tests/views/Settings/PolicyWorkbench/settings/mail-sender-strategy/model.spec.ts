/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_MAIL_SENDER_STRATEGY,
	MAIL_SENDER_STRATEGIES,
	isMailSenderStrategy,
	normalizeMailSenderStrategy,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/mail-sender-strategy/model'

describe('mail-sender-strategy model', () => {
	it('exposes the supported strategies and defaults to the system mailer', () => {
		expect(MAIL_SENDER_STRATEGIES).toEqual(['system', 'requester'])
		expect(DEFAULT_MAIL_SENDER_STRATEGY).toBe('system')
	})

	it.each([
		['system', true],
		['requester', true],
		['user_choice', false],
		['', false],
		[null, false],
		[true, false],
	])('recognizes %s as a strategy: %s', (value, expected) => {
		expect(isMailSenderStrategy(value)).toBe(expected)
	})

	it.each([
		['system', 'system'],
		['requester', 'requester'],
		['  REQUESTER ', 'requester'],
		['System', 'system'],
		['user_choice', 'system'],
		['', 'system'],
		[null, 'system'],
		[undefined, 'system'],
		[true, 'system'],
		[{ strategy: 'requester' }, 'system'],
	])('normalizes %s to %s', (value, expected) => {
		expect(normalizeMailSenderStrategy(value as never)).toBe(expected)
	})
})
