/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { SIGN_REQUEST_STATUS } from '../constants.js'

export const PARTICIPANT_ROLE = Object.freeze({
	SIGNER: 'signer',
	OBSERVER: 'observer',
})

export type ParticipantRole = typeof PARTICIPANT_ROLE[keyof typeof PARTICIPANT_ROLE]

type ParticipantLike = {
	participantRole?: string | null
	status?: number | null
}

export function isObserverParticipant(participant?: ParticipantLike | null): boolean {
	if (!participant) {
		return false
	}

	if (participant.participantRole === PARTICIPANT_ROLE.OBSERVER) {
		return true
	}

	return participant.status === SIGN_REQUEST_STATUS.OBSERVING
}

export function isSigningParticipant(participant?: ParticipantLike | null): boolean {
	if (!participant) {
		return false
	}

	return !isObserverParticipant(participant)
}

export function countSigningParticipants<T extends ParticipantLike>(participants: T[] | null | undefined): number {
	if (!Array.isArray(participants)) {
		return 0
	}

	return participants.filter(isSigningParticipant).length
}

export function filterParticipantsByRole<T extends ParticipantLike>(
	participants: T[] | null | undefined,
	role: ParticipantRole,
): T[] {
	if (!Array.isArray(participants)) {
		return []
	}

	if (role === PARTICIPANT_ROLE.SIGNER) {
		return participants.filter(isSigningParticipant)
	}

	return participants.filter(isObserverParticipant)
}
