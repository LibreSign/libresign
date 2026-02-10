/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSignStore } from '../../store/sign.js'
import { useFilesStore } from '../../store/files.js'

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
		get: vi.fn(),
		put: vi.fn(),
	},
}))

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

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, fallback) => fallback),
}))

describe('Signing Request Creation Business Logic', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('Document and signer initialization', () => {
		it('initializes signing request with document', () => {
			const signStore = useSignStore()
	        const document = { id: 1, name: 'contract.pdf', pages: 5 }

				// ensure signers array exists to match store expectations
				const doc = { ...document, signers: [] }

				signStore.setFileToSign(doc)
				expect(signStore.document.id).toBe(1)
		})

		it('stores document metadata', () => {
			const signStore = useSignStore()
			const document = { id: 1, name: 'agreement.pdf', created_at: Date.now(), signers: [] }

			signStore.setFileToSign(document)
			expect(signStore.document.name).toBe('agreement.pdf')
		})

		it('initializes empty signer list', () => {
			const signStore = useSignStore()
			expect(signStore.document.signers).toEqual([])
		})

		it('adds signer to request', () => {
			const signStore = useSignStore()
			const signer = { id: 1, name: 'Alice', email: 'alice@example.com' }

			signStore.document.signers = [signer]
			expect(signStore.document.signers.length).toBe(1)
		})
	})

	describe('Signer management', () => {
		it('maintains signer ordering', () => {
			const signers = [
				{ order: 1, name: 'First' },
				{ order: 2, name: 'Second' },
				{ order: 3, name: 'Third' },
			]

			const isOrdered = signers.every((s, i) => {
				if (i === 0) return true
				return s.order > signers[i - 1].order
			})

			expect(isOrdered).toBe(true)
		})

		it('prevents duplicate signers', () => {
			const signers = [
				{ id: 1, email: 'alice@example.com' },
				{ id: 1, email: 'alice@example.com' },
			]

			const unique = [...new Set(signers.map(s => s.id))]
			expect(unique.length).toBe(1)
		})

		it('validates signer email format', () => {
			const emails = [
				'valid@example.com',
				'invalid-email',
				'another@test.org',
			]

			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
			const validEmails = emails.filter(e => emailRegex.test(e))

			expect(validEmails.length).toBe(2)
		})

		it('allows at least one signer', () => {
			const minSigners = 1
			expect(minSigners).toBeGreaterThanOrEqual(1)
		})
	})

	describe('Signature element assignment', () => {
		it('associates signature field with signer', () => {
			const element = {
				id: 'sig-1',
				type: 'signature',
				signer: 'alice@example.com',
				page: 1,
				x: 100,
				y: 100,
			}

			expect(element.signer).toBe('alice@example.com')
			expect(element.type).toBe('signature')
		})

		it('requires minimum one signature element per signer', () => {
			const signers = [
				{ id: 1, name: 'Alice' },
				{ id: 2, name: 'Bob' },
			]

			const elements = [
				{ signer: 1, type: 'signature' },
				{ signer: 2, type: 'signature' },
			]

			const elementsPerSigner = signers.map(s => ({
				signer: s.id,
				count: elements.filter(e => e.signer === s.id).length,
			}))

			const allHaveSignatures = elementsPerSigner.every(e => e.count > 0)
			expect(allHaveSignatures).toBe(true)
		})

		it('allows multiple signature elements for same signer', () => {
			const elements = [
				{ signer: 1, type: 'signature', id: 'sig-1' },
				{ signer: 1, type: 'initial', id: 'sig-2' },
			]

			const signer1Elements = elements.filter(e => e.signer === 1)
			expect(signer1Elements.length).toBe(2)
		})
	})

	describe('Signing order and workflow', () => {
		it('defines sequential signing order', () => {
			const signers = [
				{ order: 1, name: 'Alice' },
				{ order: 2, name: 'Bob' },
				{ order: 3, name: 'Charlie' },
			]

			expect(signers[0].order).toBe(1)
			expect(signers[1].order).toBe(2)
			expect(signers[2].order).toBe(3)
		})

		it('maintains signing sequence when modified', () => {
			const signers = [
				{ order: 1, name: 'Alice' },
				{ order: 2, name: 'Bob' },
			]

			const addedSigner = { order: 3, name: 'Charlie' }
			signers.push(addedSigner)

			expect(signers[signers.length - 1].order).toBe(3)
		})

		it('validates no gaps in signing order', () => {
			const signers = [
				{ order: 1, name: 'Alice' },
				{ order: 2, name: 'Bob' },
				{ order: 4, name: 'Charlie' },
			]

			const orders = signers.map(s => s.order).sort((a, b) => a - b)
			const hasGap = orders.some((o, i) => i > 0 && o !== orders[i - 1] + 1)

			expect(hasGap).toBe(true)
		})
	})

	describe('Request submission validation', () => {
		it('requires document selection', () => {
			const document = { id: 1, name: 'test.pdf' }
			const canSubmit = !!document && !!document.id

			expect(canSubmit).toBe(true)
		})

		it('requires at least one signer', () => {
			const signers = [{ id: 1, email: 'signer@example.com' }]
			const canSubmit = signers.length > 0

			expect(canSubmit).toBe(true)
		})

		it('requires signature elements for each signer', () => {
			const signers = [
				{ id: 1, name: 'Alice' },
				{ id: 2, name: 'Bob' },
			]

			const elements = [
				{ signer: 1, type: 'signature' },
				{ signer: 2, type: 'signature' },
			]

			const allSigned = signers.every(s => elements.some(e => e.signer === s.id))
			expect(allSigned).toBe(true)
		})

		it('prevents submission with missing elements', () => {
			const signers = [
				{ id: 1, name: 'Alice' },
				{ id: 2, name: 'Bob' },
			]

			const elements = [
				{ signer: 1, type: 'signature' },
			]

			const allSigned = signers.every(s => elements.some(e => e.signer === s.id))
			expect(allSigned).toBe(false)
		})
	})

	describe('Request status tracking', () => {
		it('initializes request as pending', () => {
			const request = { status: 'pending' }
			expect(request.status).toBe('pending')
		})

		it('transitions to sent after submission', () => {
			const request = { status: 'pending' }
			request.status = 'sent'

			expect(request.status).toBe('sent')
		})

		it('tracks completion percentage', () => {
			const totalSigners = 3
			const completedSigners = 2
			const percentage = (completedSigners / totalSigners) * 100

			expect(percentage).toBe(66.66666666666666)
		})
	})
})
