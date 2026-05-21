/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ensureWorkerReady, setWorkerPath } from '@libresign/pdf-elements'
import pdfWorkerPath from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'

let configured = false

const resolveWorkerPath = (path: string): string => {
	if (typeof process === 'undefined' || !process?.versions?.node) {
		return path
	}

	if (!path.startsWith('/node_modules/')) {
		return path
	}

	const cwd = process.cwd().replace(/\\/g, '/')
	return `file://${cwd}${path}`
}

const isFakeWorkerSetupError = (error: unknown): boolean => {
	const message = error instanceof Error ? error.message : String(error)
	return message.includes('Setting up fake worker failed')
		&& message.includes("Cannot find module '/node_modules/pdfjs-dist/legacy/build/pdf.worker.min.mjs'")
}

const ensureUrlParseLocationSupport = (): void => {
	if (typeof URL.parse !== 'function') {
		return
	}

	const currentParse = URL.parse as unknown as (input: unknown, base?: string | URL) => URL | null
	if ((currentParse as { __libresignPatched?: boolean }).__libresignPatched) {
		return
	}

	const patchedParse = ((input: unknown, base?: string | URL): URL | null => {
		if (typeof Location !== 'undefined' && input instanceof Location) {
			return new URL(input.href)
		}
		return currentParse(input, base)
	}) as unknown as typeof URL.parse

	;(patchedParse as unknown as { __libresignPatched?: boolean }).__libresignPatched = true
	URL.parse = patchedParse
}

export const ensurePdfWorker = (): void => {
	if (configured) {
		return
	}
	ensureUrlParseLocationSupport()
	setWorkerPath(resolveWorkerPath(pdfWorkerPath))
	configured = true
	void Promise.resolve(ensureWorkerReady()).catch((error) => {
		if (isFakeWorkerSetupError(error)) {
			return
		}

		throw error
	})
}
