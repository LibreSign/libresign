/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { generateOCSResponse } from '../test-helpers.js'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path, params) => `/ocs/v2.php${path.replace(/{(\w+)}/g, (_, key) => params[key])}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('vue', () => ({
	del: vi.fn((obj, key) => { delete obj[key] }),
	set: vi.fn((obj, key, value) => { obj[key] = value }),
}))

describe('configureCheck store - essential business rules', () => {
	let useConfigureCheckStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.resetModules()

		const module = await import('./configureCheck.js')
		useConfigureCheckStore = module.useConfigureCheckStore
	})

	describe('RULE: checkSetup validates if resources are properly configured', () => {
		it('java with error changes state to "need download"', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'java', status: 'error' },
				{ resource: 'jsignpdf', status: 'success' },
				{ resource: 'cfssl', status: 'success' },
			],
		}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.state !== 'in progress', { timeout: 1000 })

			expect(store.state).toBe('need download')
		})

		it('all resources ok changes state to "done"', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
					{ resource: 'java', status: 'success' },
					{ resource: 'jsignpdf', status: 'success' },
					{ resource: 'cfssl', status: 'success' },
				],
			}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.state !== 'in progress', { timeout: 1000 })

			expect(store.state).toBe('done')
		})
	})

	describe('RULE: certificateEngine determines operation mode', () => {
		it('isNoneEngine returns true when engine is "none"', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({ payload: [] }))

			const store = useConfigureCheckStore()
			store.setCertificateEngine('none')

			expect(store.isNoneEngine).toBe(true)
		})

		it('saveCertificateEngine persists engine and updates identifyMethods', async () => {
			axios.post.mockResolvedValue(generateOCSResponse({
				payload: {
					identify_methods: ['email', 'sms'],
				},
			}))

			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'java', status: 'success' },
				{ resource: 'jsignpdf', status: 'success' },
				{ resource: 'cfssl', status: 'success' },
			],
		}))

			const store = useConfigureCheckStore()
			const result = await store.saveCertificateEngine('openssl')

			expect(result.success).toBe(true)
			expect(store.certificateEngine).toBe('openssl')
			expect(store.identifyMethods).toEqual(['email', 'sms'])
		})
	})

	describe('RULE: isConfigureOk validates engine-specific configuration', () => {
		it('returns true when engine is configured without errors', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'openssl-configure', status: 'success' },
			],
		}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.items.length > 0, { timeout: 1000 })

			expect(store.isConfigureOk('openssl')).toBe(true)
		})

		it('returns false when engine has configuration error', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'openssl-configure', status: 'error' },
			],
		}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.items.length > 0, { timeout: 1000 })

			expect(store.isConfigureOk('openssl')).toBe(false)
		})
	})
})
