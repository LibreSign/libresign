/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type LibreSignRuntimeConfig = {
	nextcloudUrl?: string
	openMode?: 'new-tab' | 'same-tab'
}

type LibreSignBridgeMessage = {
	type: 'libresign:requestSignature'
	fileId?: number | string
}

type LibreSignBridgeResponse =
	| { type: 'libresign:requestCreated', uuid: string }
	| { type: 'libresign:requestError', error: string }

declare global {
	interface Window {
		__LIBRESIGN_CONFIG__?: LibreSignRuntimeConfig
	}
}

window.__LIBRESIGN_CONFIG__ = {
	...(window.__LIBRESIGN_CONFIG__ ?? {}),
	nextcloudUrl: window.location.origin,
	openMode: window.__LIBRESIGN_CONFIG__?.openMode ?? 'new-tab',
}

const APP_ORIGIN = window.location.origin

function getEditorOrigin(): string | null {
	const onlyoffice = window.OCA?.Onlyoffice as { frameSelector?: string } | undefined
	const frameSelector = onlyoffice?.frameSelector
	if (!frameSelector) {
		return null
	}

	const editorIframe = document.querySelector<HTMLIFrameElement>(frameSelector)
	if (!editorIframe?.src) {
		return null
	}

	try {
		return new URL(editorIframe.src).origin
	} catch {
		return null
	}
}

function getFilenameFromHeaders(headers: Headers, fallbackName: string): string {
	const contentDisposition = headers.get('content-disposition') ?? ''
	const utf8NameMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)
	if (utf8NameMatch?.[1]) {
		return decodeURIComponent(utf8NameMatch[1])
	}

	const plainNameMatch = contentDisposition.match(/filename="?([^";]+)"?/i)
	if (plainNameMatch?.[1]) {
		return plainNameMatch[1]
	}

	return fallbackName
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
	let binary = ''
	const bytes = new Uint8Array(buffer)
	const chunkSize = 0x8000

	for (let i = 0; i < bytes.length; i += chunkSize) {
		const chunk = bytes.subarray(i, i + chunkSize)
		binary += String.fromCharCode(...chunk)
	}

	return btoa(binary)
}

async function convertToPdfBase64(fileId: number): Promise<{ name: string, base64: string }> {
	const downloadUrl = `${APP_ORIGIN}/apps/eurooffice/downloadas?fileId=${encodeURIComponent(String(fileId))}&toExtension=pdf`
	const downloadResponse = await fetch(downloadUrl, {
		credentials: 'include',
	})

	if (!downloadResponse.ok) {
		throw new Error(`Failed to convert file to PDF (HTTP ${downloadResponse.status})`)
	}

	const fallbackName = `document-${fileId}.pdf`
	const fileName = getFilenameFromHeaders(downloadResponse.headers, fallbackName)
	const pdfArrayBuffer = await downloadResponse.arrayBuffer()
	const base64 = arrayBufferToBase64(pdfArrayBuffer)

	return {
		name: fileName,
		base64: `data:application/pdf;base64,${base64}`,
	}
}

async function createSignRequestFromPdf(pdf: { name: string, base64: string }): Promise<string> {
	const requestToken = window.OC.requestToken
	const response = await fetch(`${APP_ORIGIN}/apps/libresign/api/v1/request-signature`, {
		method: 'POST',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			...(requestToken ? { requesttoken: requestToken } : {}),
		},
		body: JSON.stringify({
			name: pdf.name,
			status: 0,
			file: {
				name: pdf.name,
				base64: pdf.base64,
			},
		}),
	})

	const payload = await response.json().catch(() => ({})) as { uuid?: string, message?: string }
	if (!response.ok || !payload.uuid) {
		throw new Error(payload.message ?? `Failed to create signature request (HTTP ${response.status})`)
	}

	return payload.uuid
}

function isWindowProxy(source: MessageEventSource | null): source is WindowProxy {
	return !!source && 'closed' in source
}

function postResponse(targetWindow: MessageEventSource | null, targetOrigin: string, payload: LibreSignBridgeResponse): void {
	if (!isWindowProxy(targetWindow)) {
		return
	}
	targetWindow.postMessage(payload, targetOrigin)
}

async function handleRequestSignature(event: MessageEvent<LibreSignBridgeMessage>): Promise<void> {
	const sourceWindow = event.source
	const sourceOrigin = event.origin

	const rawFileId = event.data.fileId
	const fileId = Number(rawFileId)
	if (!Number.isInteger(fileId) || fileId <= 0) {
		postResponse(sourceWindow, sourceOrigin, {
			type: 'libresign:requestError',
			error: 'Invalid fileId',
		})
		return
	}

	try {
		const pdf = await convertToPdfBase64(fileId)
		const uuid = await createSignRequestFromPdf(pdf)

		postResponse(sourceWindow, sourceOrigin, {
			type: 'libresign:requestCreated',
			uuid,
		})
	} catch (error) {
		postResponse(sourceWindow, sourceOrigin, {
			type: 'libresign:requestError',
			error: error instanceof Error ? error.message : 'Unexpected error while creating signature request',
		})
	}
}

window.addEventListener('message', (event: MessageEvent<LibreSignBridgeMessage>) => {
	if (!event.data || event.data.type !== 'libresign:requestSignature') {
		return
	}

	const editorOrigin = getEditorOrigin()
	if (editorOrigin && event.origin !== editorOrigin) {
		return
	}

	void handleRequestSignature(event)
})
