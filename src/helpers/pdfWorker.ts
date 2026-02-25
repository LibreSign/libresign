/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setWorkerPath } from '@libresign/pdf-elements/src/utils/asyncReader'

let configured = false

export const ensurePdfWorker = (): void => {
	if (configured) {
		return
	}
	configured = true
	import('pdfjs-dist/build/pdf.worker.min.mjs?url')
		.then((mod) => {
			setWorkerPath(mod.default as string)
		})
		.catch((error) => {
			console.error('Failed to load pdf.js worker URL:', error)
		})
}
