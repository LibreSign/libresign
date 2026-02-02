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

describe('configureCheck store - regras de negócio essenciais', () => {
	let useConfigureCheckStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.resetModules()

		const module = await import('./configureCheck.js')
		useConfigureCheckStore = module.useConfigureCheckStore
	})

	describe('REGRA: checkSetup valida se recursos estão configurados corretamente', () => {
		it('java com erro muda estado para "need download"', async () => {
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

		it('todos recursos ok muda estado para "done"', async () => {
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

	describe('REGRA: certificateEngine determina modo de operação', () => {
		it('isNoneEngine retorna true quando engine é "none"', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({ payload: [] }))

			const store = useConfigureCheckStore()
			store.setCertificateEngine('none')

			expect(store.isNoneEngine).toBe(true)
		})

		it('saveCertificateEngine persiste engine e atualiza identifyMethods', async () => {
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

	describe('REGRA: isConfigureOk valida configuração específica de engine', () => {
		it('retorna true quando engine está configurado sem erros', async () => {
			axios.get.mockResolvedValue(generateOCSResponse({
				payload: [
				{ resource: 'openssl-configure', status: 'success' },
			],
		}))

			const store = useConfigureCheckStore()
			await vi.waitUntil(() => store.items.length > 0, { timeout: 1000 })

			expect(store.isConfigureOk('openssl')).toBe(true)
		})

		it('retorna false quando engine tem erro de configuração', async () => {
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
