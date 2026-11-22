/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createExternalSigningAdminOcsAdapter, ExternalSigningAdminError } from '../../services/externalSigningAdminOcs'
import type { ExternalSigningDraft } from '../../services/externalSigningAdmin'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string, variables?: { id: string }) => variables ? path.replace('{id}', variables.id) : path,
}))

const firstId = 'a'.repeat(32)
const secondId = 'b'.repeat(32)
const endpoint = '/apps/libresign/api/v1/admin/signing/integrations'

function summary(overrides: Record<string, unknown> = {}) {
	return {
		id: firstId,
		driverId: 'csc-v1',
		name: 'Corporate signing',
		configuration: { baseUrl: 'https://csc.example.org' },
		enabled: false,
		hasSecrets: true,
		driverAvailable: false,
		revision: 3,
		...overrides,
	}
}

function detail(overrides: Record<string, unknown> = {}) {
	return { ...summary(), secretFields: [{ key: 'accessToken', isConfigured: true }], ...overrides }
}

function ocs(data: unknown) {
	return { data: { ocs: { data } } }
}

function draft(overrides: Partial<ExternalSigningDraft> = {}): ExternalSigningDraft {
	return {
		id: firstId,
		driverId: 'csc-v1',
		name: 'Corporate signing',
		revision: 3,
		configuration: { baseUrl: 'https://csc.example.org' },
		secretReplacements: {},
		...overrides,
	}
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('external signing administrative OCS adapter', () => {
	it('iterates paginated results without conflating two instances of the same driver', async () => {
		vi.mocked(axios.get)
			.mockResolvedValueOnce(ocs({ items: [summary()], nextCursor: 8 }))
			.mockResolvedValueOnce(ocs({ items: [summary({ id: secondId, name: 'Second CSC' })], nextCursor: null }))
		const instances = await createExternalSigningAdminOcsAdapter().list()
		expect(instances.map(item => item.id)).toEqual([firstId, secondId])
		expect(instances[0].configuration.baseUrl).toBe('https://csc.example.org')
		expect(vi.mocked(axios.get).mock.calls[0]).toEqual([endpoint, { params: { afterId: 0, pageSize: 50 } }])
		expect(vi.mocked(axios.get).mock.calls[1]).toEqual([endpoint, { params: { afterId: 8, pageSize: 50 } }])
	})

	it('loads configured secret indicators on demand without exposing stored credentials', async () => {
		vi.mocked(axios.get).mockResolvedValue(ocs(detail()))
		const adapter = createExternalSigningAdminOcsAdapter()
		const item = await adapter.get!(await Promise.resolve({
			...summary(),
			configuredSecretFields: [],
		}))
		expect(item.configuredSecretFields).toEqual(['accessToken'])
		expect(item.hasSecrets).toBe(true)
		expect(item.driverAvailable).toBe(false)
		expect(JSON.stringify(item)).not.toContain('plain-token')
		expect(axios.get).toHaveBeenCalledWith(endpoint + '/' + firstId)
	})

	it('creates a disabled configuration using server field names and write-only secrets', async () => {
		vi.mocked(axios.post).mockResolvedValue(ocs(detail({ revision: 1 })))
		const result = await createExternalSigningAdminOcsAdapter().save(draft({ id: undefined, revision: undefined, secretReplacements: { accessToken: 'synthetic-token' } }))
		expect(result.enabled).toBe(false)
		expect(axios.post).toHaveBeenCalledWith(endpoint, {
			driverId: 'csc-v1',
			name: 'Corporate signing',
			configuration: { baseUrl: 'https://csc.example.org' },
			secrets: { accessToken: 'synthetic-token' },
		})
	})

	it('omits unchanged credentials during edits and preserves optimistic concurrency revision', async () => {
		vi.mocked(axios.patch).mockResolvedValue(ocs(detail({ revision: 4 })))
		await createExternalSigningAdminOcsAdapter().save(draft())
		expect(axios.patch).toHaveBeenCalledWith(endpoint + '/' + firstId, {
			revision: 3,
			name: 'Corporate signing',
			configuration: { baseUrl: 'https://csc.example.org' },
		})
	})

	it.each([
		[{ accessToken: 'replacement-secret' }, { accessToken: 'replacement-secret' }],
		[{ accessToken: null }, { accessToken: null }],
	])('sends only explicit secret changes %j', async (secretReplacements, secrets) => {
		vi.mocked(axios.patch).mockResolvedValue(ocs(detail({ revision: 4 })))
		await createExternalSigningAdminOcsAdapter().save(draft({ secretReplacements }))
		expect(axios.patch).toHaveBeenCalledWith(endpoint + '/' + firstId, expect.objectContaining({ revision: 3, secrets }))
	})

	it('sends versioned enable, disable and removal operations without overriding the global flag', async () => {
		vi.mocked(axios.post).mockResolvedValue(ocs(detail({ revision: 4 })))
		vi.mocked(axios.delete).mockResolvedValue(ocs({ deleted: true }))
		const adapter = createExternalSigningAdminOcsAdapter()
		const instance = { ...summary(), configuredSecretFields: [] }
		await adapter.setEnabled(instance, true)
		await adapter.setEnabled(instance, false)
		await adapter.remove(instance)
		expect(axios.post).toHaveBeenNthCalledWith(1, endpoint + '/' + firstId + '/enable', { revision: 3 })
		expect(axios.post).toHaveBeenNthCalledWith(2, endpoint + '/' + firstId + '/disable', { revision: 3 })
		expect(axios.delete).toHaveBeenCalledWith(endpoint + '/' + firstId, { params: { revision: 3 } })
	})

	it('rejects stale revisions without logging or returning any credential values', async () => {
		vi.mocked(axios.patch).mockRejectedValue({ response: { status: 409 }, config: { data: 'private-token' } })
		const adapter = createExternalSigningAdminOcsAdapter()
		await expect(adapter.save(draft({ secretReplacements: { accessToken: 'private-token' } })))
			.rejects.toMatchObject({ status: 409 })
		try {
			await adapter.save(draft({ secretReplacements: { accessToken: 'private-token' } }))
		} catch (error) {
			expect(error).toBeInstanceOf(ExternalSigningAdminError)
			expect(String(error)).not.toContain('private-token')
		}
	})

	it('rejects broken pagination instead of looping or silently dropping configured integrations', async () => {
		vi.mocked(axios.get).mockResolvedValue(ocs({ items: [summary()], nextCursor: 0 }))
		await expect(createExternalSigningAdminOcsAdapter().list()).rejects.toThrow('Invalid external integration page cursor')
		expect(axios.get).toHaveBeenCalledTimes(1)
	})
})
