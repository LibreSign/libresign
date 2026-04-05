/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setWorkerPath } from '@libresign/pdf-elements'
import pdfWorkerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'

let configured = false

export const ensurePdfWorker = (): void => {
	if (configured) {
		return
	}
	configured = true
	setWorkerPath(pdfWorkerUrl)
}
