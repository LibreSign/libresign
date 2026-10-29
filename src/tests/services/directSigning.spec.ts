/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from '@nextcloud/axios'

import { canSignDirectly, signPdfDirectly } from '../../services/directSigning.ts'

vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateOcsUrl: vi.fn((path: string) => path) }))

const post = vi.mocked(axios.post)
const accountMethods = (enabled: boolean) => ({
	factors: [{ name: 'account', enabled: true, signatureMethods: { clickToSign: { enabled } } }],
})

describe('direct PDF signing', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('offers one-call signing only when the account click-to-sign policy allows it', () => {
		expect(canSignDirectly(accountMethods(true))).toBe(true)
		expect(canSignDirectly(accountMethods(false))).toBe(false)
		expect(canSignDirectly({ factors: [{ name: 'email', enabled: true, signatureMethods: { clickToSign: { enabled: true } } }] })).toBe(false)
		expect(canSignDirectly({ factors: [{ name: 'account', enabled: false, signatureMethods: { clickToSign: { enabled: true } } }] })).toBe(false)
	})

	it('sends an existing Nextcloud PDF by node ID and receives its signed bytes', async () => {
		const pdf = new Blob(['%PDF-signed'], { type: 'application/pdf' })
		post.mockResolvedValueOnce({ data: pdf, headers: { 'content-type': 'application/pdf' } })
		await expect(signPdfDirectly({ name: 'document.pdf', nodeId: 123 })).resolves.toEqual({ pdf, filename: 'document.pdf' })
		expect(post).toHaveBeenCalledExactlyOnceWith(
			'/apps/libresign/api/v1/sign/direct',
			{ name: 'document.pdf', file: { nodeId: 123 }, method: 'clickToSign' },
			{ responseType: 'blob', headers: { 'OCS-APIRequest': 'true' } },
		)
	})

	it('encodes a fresh PDF as base64 without first creating a draft', async () => {
		const freshPdf = { arrayBuffer: async () => Uint8Array.of(37, 80, 68, 70, 45, 255).buffer } as File
		post.mockResolvedValueOnce({ data: new Blob(), headers: { 'content-type': 'application/pdf' } })
		await signPdfDirectly({ name: 'new.pdf', pdf: freshPdf })
		expect(post).toHaveBeenCalledExactlyOnceWith(
			'/apps/libresign/api/v1/sign/direct',
			{ name: 'new.pdf', file: { base64: 'JVBERi3/' }, method: 'clickToSign' },
			{ responseType: 'blob', headers: { 'OCS-APIRequest': 'true' } },
		)
	})

	it('refuses invalid or ambiguous inputs without calling the API', async () => {
		await expect(signPdfDirectly({ name: 'contract.txt', nodeId: 1 })).rejects.toThrow('PDF filename')
		await expect(signPdfDirectly({ name: 'contract.pdf' })).rejects.toThrow('exactly one')
		await expect(signPdfDirectly({ name: 'contract.pdf', nodeId: 1, pdf: {} as File })).rejects.toThrow('exactly one')
		expect(post).not.toHaveBeenCalled()
	})

	it('does not accept a non-PDF response as successful signing', async () => {
		post.mockResolvedValueOnce({ data: new Blob(), headers: { 'content-type': 'application/json' } })
		await expect(signPdfDirectly({ name: 'document.pdf', nodeId: 123 })).rejects.toThrow('signed PDF')
	})
})
