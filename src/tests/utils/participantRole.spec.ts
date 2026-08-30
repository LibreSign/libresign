/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	countSigningParticipants,
	filterParticipantsByRole,
	isObserverParticipant,
	isSigningParticipant,
	PARTICIPANT_ROLE,
} from '../../utils/participantRole.ts'

describe('participantRole utils', () => {
	it('treats missing role as signer', () => {
		expect(isSigningParticipant({})).toBe(true)
		expect(isObserverParticipant({})).toBe(false)
	})

	it('filters signers and observers separately', () => {
		const participants = [
			{ displayName: 'Alice', participantRole: PARTICIPANT_ROLE.SIGNER },
			{ displayName: 'Bob', participantRole: PARTICIPANT_ROLE.OBSERVER },
		]

		expect(filterParticipantsByRole(participants, PARTICIPANT_ROLE.SIGNER)).toHaveLength(1)
		expect(filterParticipantsByRole(participants, PARTICIPANT_ROLE.OBSERVER)).toHaveLength(1)
		expect(countSigningParticipants(participants)).toBe(1)
	})
})
