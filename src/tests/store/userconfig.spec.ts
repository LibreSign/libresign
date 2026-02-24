/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useUserConfigStore } from '../../store/userconfig.js'
import { generateOCSResponse } from '../test-helpers'

const { putMock, generateOcsUrlMock } = vi.hoisted(() => ({
	putMock: vi.fn(() => Promise.resolve()),
	generateOcsUrlMock: vi.fn(() => '/ocs/config/locale'),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		put: putMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: generateOcsUrlMock,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => ({ locale: 'pt_BR' }),
}))

describe('userconfig store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		putMock.mockClear()
		generateOcsUrlMock.mockClear()
	})

	it('updates local state and persists config', async () => {
		const store = useUserConfigStore()

		putMock.mockResolvedValue(generateOCSResponse({ payload: { value: 'en_US' } }) as any)

		await store.update('locale', 'en_US')

		expect(store.locale).toBe('en_US')
		expect(generateOcsUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/config/{key}', { key: 'locale' })
		expect(putMock).toHaveBeenCalledWith('/ocs/config/locale', { value: 'en_US' })
	})
})
