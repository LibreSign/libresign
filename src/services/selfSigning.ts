/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'

import { SIGN_REQUEST_STATUS } from '../constants.js'
import { useFilesStore } from '../store/files.js'
import type { EffectivePolicyValue } from '../types/index'
import { getSignRequestStatusText } from '../utils/getSignRequestStatusText.ts'
import { isSigningParticipant, PARTICIPANT_ROLE } from '../utils/participantRole.ts'
import { normalizeIdentifyMethodsPolicy } from '../views/Settings/PolicyWorkbench/settings/identify-methods/model.ts'

type FilesStore = ReturnType<typeof useFilesStore>
type StoredSigner = NonNullable<ReturnType<FilesStore['getFile']>['signers']>[number]

export function isAccountIdentificationEnabled(value: EffectivePolicyValue): boolean {
	return normalizeIdentifyMethodsPolicy(value)
		.some(method => method.name === 'account' && method.enabled)
}

export function canIncludeCurrentAccount(
	filesStore: FilesStore,
	identifyMethodsPolicy: EffectivePolicyValue,
): boolean {
	const userId = getCurrentUser()?.uid
	if (!userId || !isAccountIdentificationEnabled(identifyMethodsPolicy) || !filesStore.canAddSigner()) {
		return false
	}

	return !(filesStore.getFile()?.signers ?? []).some(signer =>
		isSigningParticipant(signer)
		&& signer.identifyMethods?.some(method => method.method === 'account' && method.value === userId),
	)
}

/**
 * Create a regular draft signing participant. This never signs the document,
 * sends a request, or bypasses backend permissions and request policies.
 */
export async function addCurrentAccountAsSigner(
	filesStore: FilesStore,
	identifyMethodsPolicy: EffectivePolicyValue,
) {
	if (!canIncludeCurrentAccount(filesStore, identifyMethodsPolicy)) {
		return { success: false as const }
	}

	const user = getCurrentUser()!
	const signers: StoredSigner[] = [...(filesStore.getFile()?.signers ?? [])]
	signers.push({
		displayName: (user.displayName || user.uid).slice(0, 64),
		description: '',
		participantRole: PARTICIPANT_ROLE.SIGNER,
		status: SIGN_REQUEST_STATUS.DRAFT,
		statusText: getSignRequestStatusText(SIGN_REQUEST_STATUS.DRAFT),
		identifyMethods: [{
			method: 'account',
			value: user.uid,
			requirement: 'optional',
		}],
	})

	return filesStore.saveOrUpdateSignatureRequest({ signers })
}
