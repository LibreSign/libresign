/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { EffectivePolicyValue } from '../types/index'
import { normalizeIdentifyMethodsPolicy } from '../views/Settings/PolicyWorkbench/settings/identify-methods/model.ts'

export type DirectSignInput = {
	name: string
	nodeId?: number
	pdf?: File
}

export function canSignDirectly(value: EffectivePolicyValue): boolean {
	return normalizeIdentifyMethodsPolicy(value).some(factor =>
		factor.name === 'account'
		&& factor.enabled
		&& factor.signatureMethods.clickToSign?.enabled === true,
	)
}

async function encodePdf(pdf: File): Promise<string> {
	const bytes = new Uint8Array(await pdf.arrayBuffer())
	const segments: string[] = []
	for (let offset = 0; offset < bytes.length; offset += 8192) {
		segments.push(String.fromCharCode(...bytes.subarray(offset, offset + 8192)))
	}
	return btoa(segments.join(''))
}

export async function signPdfDirectly(input: DirectSignInput): Promise<{ pdf: Blob; filename: string }> {
	if (!input.name.toLowerCase().endsWith('.pdf') || !input.name.trim()) {
		throw new Error('A PDF filename is required.')
	}
	if ((input.nodeId === undefined) === (input.pdf === undefined)
		|| (input.nodeId !== undefined && (!Number.isSafeInteger(input.nodeId) || input.nodeId <= 0))) {
		throw new Error('Select exactly one PDF.')
	}
	const file = input.pdf
		? { base64: await encodePdf(input.pdf) }
		: { nodeId: input.nodeId }
	const response = await axios.post<Blob>(
		generateOcsUrl('/apps/libresign/api/v1/sign/direct'),
		{ name: input.name, file, method: 'clickToSign' },
		{ responseType: 'blob', headers: { 'OCS-APIRequest': 'true' } },
	)
	if (!String(response.headers['content-type'] ?? '').includes('application/pdf')) {
		throw new Error('The server did not return a signed PDF.')
	}
	return { pdf: response.data, filename: input.name }
}

export function downloadSignedPdf(pdf: Blob, filename: string): void {
	const url = URL.createObjectURL(pdf)
	const link = document.createElement('a')
	link.href = url
	link.download = filename
	document.body.appendChild(link)
	link.click()
	link.remove()
	setTimeout(() => URL.revokeObjectURL(url), 1000)
}
