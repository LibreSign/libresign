/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const SMALL_VALID_PDF_PATH = resolve(process.cwd(), 'tests/php/fixtures/pdfs/small_valid.pdf')

let smallValidPdfBufferPromise: Promise<Buffer> | null = null
let smallValidPdfBase64Promise: Promise<string> | null = null

export function getSmallValidPdfBuffer(): Promise<Buffer> {
	if (!smallValidPdfBufferPromise) {
		smallValidPdfBufferPromise = readFile(SMALL_VALID_PDF_PATH)
	}
	return smallValidPdfBufferPromise
}

export function getSmallValidPdfBase64(): Promise<string> {
	if (!smallValidPdfBase64Promise) {
		smallValidPdfBase64Promise = getSmallValidPdfBuffer().then((buffer) => buffer.toString('base64'))
	}
	return smallValidPdfBase64Promise
}