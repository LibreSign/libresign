/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import { SIGN_REQUEST_STATUS } from '../constants.js'
import type { SignerDetailRecord } from '../types/index'
import { isObserverParticipant } from './participantRole.ts'

type SignerStatusLike = {
	displayStatus?: SignerDetailRecord['displayStatus']
	status?: number | null
	signed?: string | null
	participantRole?: string | null
}

export function isSignedSigner(signer: SignerStatusLike): boolean {
	return !!signer.signed || signer.displayStatus === 'signed' || signer.status === SIGN_REQUEST_STATUS.SIGNED
}

/**
 * The backend decides what each viewer may know about a signer, so only the
 * display status is read here: a hidden signer must look the same whatever
 * its real state, and a canceled workflow must not single anybody out.
 *
 * @return null for a signer who signed, whose presentation shows the signature instead
 */
export function getSignerStatusLabel(signer: SignerStatusLike, workflowCanceled: boolean): string | null {
	if (isObserverParticipant(signer)) {
		return t('libresign', 'Observing')
	}

	if (isSignedSigner(signer)) {
		return null
	}

	if (signer.displayStatus === 'rejected') {
		// TRANSLATORS Status of a signer who refused to sign the document.
		return t('libresign', 'Rejected')
	}

	if (workflowCanceled) {
		// TRANSLATORS Status of a signer who had not signed when the signing workflow of the document was canceled.
		return t('libresign', 'No longer able to sign')
	}

	if (signer.displayStatus === 'not_signed') {
		// TRANSLATORS Neutral status of a signer who has not signed, shown when the viewer may not know more about them.
		return t('libresign', 'Not signed')
	}

	return t('libresign', 'Not signed yet')
}
