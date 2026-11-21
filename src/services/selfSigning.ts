/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'

import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants.js'
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
 * Add the authenticated account using ordinary request persistence.
 * The direct self-sign entry point starts an active request so the existing
 * Sign document action becomes available, but never signs the PDF itself.
 * The regular account picker keeps the default draft behavior.
 */
export async function addCurrentAccountAsSigner(
	filesStore: FilesStore,
	identifyMethodsPolicy: EffectivePolicyValue,
	options: { readyToSign?: boolean } = {},
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
		...(options.readyToSign ? { notify: 0 } : {}),
		status: options.readyToSign ? SIGN_REQUEST_STATUS.ABLE_TO_SIGN : SIGN_REQUEST_STATUS.DRAFT,
		statusText: getSignRequestStatusText(options.readyToSign ? SIGN_REQUEST_STATUS.ABLE_TO_SIGN : SIGN_REQUEST_STATUS.DRAFT),
		identifyMethods: [{
			method: 'account',
			value: user.uid,
			requirement: 'optional',
		}],
	})

	const response = await filesStore.saveOrUpdateSignatureRequest({
		signers,
		...(options.readyToSign ? { status: FILE_STATUS.ABLE_TO_SIGN } : {}),
	})
	if (options.readyToSign && response && 'id' in response && typeof response.id === 'number') {
		// The validate endpoint supplies the authenticated signer's UUID,
		// identity settings, and canSign permission for the normal sign flow.
		await filesStore.fetchFileDetail({ fileId: response.id, force: true })
	}
	return response
}
