/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { generateOCSResponse } from '../test-helpers'

type AxiosMock = {
	get: MockedFunction<(url: string) => Promise<unknown>>
	post: MockedFunction<(url: string, data?: unknown) => Promise<unknown>>
}

// Mock @nextcloud/logger to avoid import-time errors
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => (
		`/ocs/v2.php${path.replace(/{(\w+)}/g, (_match: string, key: string) => params?.[key] ?? '')}`
	)),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('vue', async () => {
	const actual = await vi.importActual('vue')
	const Vue = actual.default ?? actual
	return {
		...actual,
		default: Object.assign(Vue, {
			del: vi.fn((obj, key) => { delete obj[key] }),
			set: vi.fn((obj, key, value) => { obj[key] = value }),
		}),
	}
})

describe('configureCheck store - essential business rules', () => {
	const axiosMock = axios as unknown as AxiosMock
	let useConfigureCheckStore: typeof import('../../store/configureCheck.js').useConfigureCheckStore

	beforeAll(async () => {
		const module = await import('../../store/configureCheck.js')
		useConfigureCheckStore = module.useConfigureCheckStore
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	describe('RULE: checkSetup validates if resources are properly configured', () => {
		it('java with error changes state to "need download"', async () => {
			axiosMock.get.mockResolvedValue(generateOCSResponse({
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
			axiosMock.get.mockResolvedValue(generateOCSResponse({
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
			axiosMock.get.mockResolvedValue(generateOCSResponse({ payload: [] }))

			const store = useConfigureCheckStore()
			store.setCertificateEngine('none')

			expect(store.isNoneEngine).toBe(true)
		})

		it('saveCertificateEngine persists engine and updates identifyMethods', async () => {
			axiosMock.post.mockResolvedValue(generateOCSResponse({
				payload: {
					identify_methods: ['email', 'sms'],
				},
			}))

			axiosMock.get.mockResolvedValue(generateOCSResponse({
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
			axiosMock.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'openssl-configure', status: 'success' },
			],
		}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.items.length > 0, { timeout: 1000 })

			expect(store.isConfigureOk('openssl')).toBe(true)
		})

		it('returns false when engine has configuration error', async () => {
			axiosMock.get.mockResolvedValue(generateOCSResponse({
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
