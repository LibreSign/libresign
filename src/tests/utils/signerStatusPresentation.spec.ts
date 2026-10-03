/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { SIGN_REQUEST_STATUS } from '../../constants.js'
import { getSignerStatusLabel } from '../../utils/signerStatusPresentation'

describe('getSignerStatusLabel', () => {
	it.each([
		['draft', 'Not signed yet'],
		['ready_to_sign', 'Not signed yet'],
		['rejected', 'Rejected'],
		['not_signed', 'Not signed'],
	] as const)('presents the %s display status as "%s"', (displayStatus, label) => {
		expect(getSignerStatusLabel({ displayStatus }, false)).toBe(label)
	})

	it('has no status label for a signer who signed', () => {
		expect(getSignerStatusLabel({ displayStatus: 'signed', signed: '2026-09-09T12:00:00+00:00' }, false)).toBeNull()
		expect(getSignerStatusLabel({ displayStatus: 'signed', signed: '2026-09-09T12:00:00+00:00' }, true)).toBeNull()
	})

	it('presents an observer as observing, whatever the workflow', () => {
		expect(getSignerStatusLabel({ displayStatus: 'observing', participantRole: 'observer' }, false)).toBe('Observing')
		expect(getSignerStatusLabel({ displayStatus: 'observing', participantRole: 'observer' }, true)).toBe('Observing')
	})

	it.each(['draft', 'ready_to_sign', 'not_signed'] as const)(
		'presents a %s signer of a canceled workflow as no longer able to sign',
		(displayStatus) => {
			expect(getSignerStatusLabel({ displayStatus }, true)).toBe('No longer able to sign')
		},
	)

	it('keeps a visible rejection visible in a canceled workflow', () => {
		expect(getSignerStatusLabel({ displayStatus: 'rejected' }, true)).toBe('Rejected')
	})

	it('relies on the display status and never on the real signer status', () => {
		// A hidden signer may still carry any status value in an older payload; the
		// presentation must not recover it.
		expect(getSignerStatusLabel({ displayStatus: 'not_signed', status: SIGN_REQUEST_STATUS.ABLE_TO_SIGN }, false)).toBe('Not signed')
		expect(getSignerStatusLabel({ displayStatus: 'not_signed', status: SIGN_REQUEST_STATUS.REJECTED }, false)).toBe('Not signed')
	})

	it('gives every hidden signer the same label', () => {
		const labels = [SIGN_REQUEST_STATUS.DRAFT, SIGN_REQUEST_STATUS.ABLE_TO_SIGN, SIGN_REQUEST_STATUS.REJECTED]
			.map(status => getSignerStatusLabel({ displayStatus: 'not_signed', status }, false))

		expect(new Set(labels).size).toBe(1)
	})
})
