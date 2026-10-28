/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import { getCurrentUser } from '@nextcloud/auth'
import { addCurrentAccountAsSigner, canIncludeCurrentAccount, isAccountIdentificationEnabled } from '../../services/selfSigning.ts'
import { PARTICIPANT_ROLE } from '../../utils/participantRole.ts'
import type { useFilesStore } from '../../store/files.js'

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({ uid: 'admin', displayName: 'Administrator' })),
}))
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const allowedPolicy = [{ name: 'account', enabled: true, requirement: 'required' }]
const disabledPolicy = [{ name: 'account', enabled: false }, { name: 'email', enabled: true }]

describe('self-signing preparation', () => {
	const save = vi.fn(async () => ({ id: 7, success: true }))
	const fakeStore = {
		canAddSigner: vi.fn(() => true),
		getFile: vi.fn(() => ({ signers: [] as unknown[] })),
		saveOrUpdateSignatureRequest: save,
		fetchFileDetail: vi.fn(async () => ({ id: 7 })),
	}

	beforeEach(() => {
		vi.clearAllMocks()
		fakeStore.canAddSigner.mockReturnValue(true)
		fakeStore.getFile.mockReturnValue({ signers: [] })
		vi.mocked(getCurrentUser).mockReturnValue({ uid: 'admin', displayName: 'Administrator' } as ReturnType<typeof getCurrentUser>)
	})

	function store() {
		return fakeStore as unknown as ReturnType<typeof useFilesStore>
	}

	it('requires a policy-enabled account identity', () => {
		expect(isAccountIdentificationEnabled(allowedPolicy)).toBe(true)
		expect(isAccountIdentificationEnabled(disabledPolicy)).toBe(false)
		expect(canIncludeCurrentAccount(store(), disabledPolicy)).toBe(false)
	})

	it('does not offer a second signer for the same account', () => {
		fakeStore.getFile.mockReturnValue({ signers: [{
			participantRole: PARTICIPANT_ROLE.SIGNER,
			identifyMethods: [{ method: 'account', value: 'admin' }],
		}] } as never)
		expect(canIncludeCurrentAccount(store(), allowedPolicy)).toBe(false)
	})

	it('respects request authorization and document state', () => {
		fakeStore.canAddSigner.mockReturnValue(false)
		expect(canIncludeCurrentAccount(store(), allowedPolicy)).toBe(false)
	})

	it('creates only an ordinary draft signer and leaves signing to the existing flow', async () => {
		const result = await addCurrentAccountAsSigner(store(), allowedPolicy)
		expect(result).toMatchObject({ success: true })
		expect(save).toHaveBeenCalledExactlyOnceWith({
			signers: [expect.objectContaining({
				displayName: 'Administrator',
				participantRole: 'signer',
				status: 0,
				identifyMethods: [{ method: 'account', value: 'admin', requirement: 'optional' }],
			})],
		})
	})

	it('activates self-signing with no notification using centralized permission refresh', async () => {
		const result = await addCurrentAccountAsSigner(store(), allowedPolicy, { readyToSign: true })
		expect(result).toMatchObject({ success: true })
		expect(save).toHaveBeenCalledExactlyOnceWith({
			status: 1,
			signers: [expect.objectContaining({
				status: 1,
				notify: 0,
				participantRole: 'signer',
				identifyMethods: [{ method: 'account', value: 'admin', requirement: 'optional' }],
			})],
		})
		expect(fakeStore.fetchFileDetail).not.toHaveBeenCalled()
	})

	it('does not fetch a signing session if activation was rejected', async () => {
		save.mockResolvedValueOnce({ success: false, message: 'Denied' } as never)
		await expect(addCurrentAccountAsSigner(store(), allowedPolicy, { readyToSign: true }))
			.resolves.toMatchObject({ success: false })
		expect(fakeStore.fetchFileDetail).not.toHaveBeenCalled()
	})

	it('does not write when the identity policy denies account signing', async () => {
		await expect(addCurrentAccountAsSigner(store(), disabledPolicy)).resolves.toEqual({ success: false })
		expect(save).not.toHaveBeenCalled()
	})
})
