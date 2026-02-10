/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

const setWorkerPathMock = vi.fn()
const generateFilePathMock = vi.fn(() => '/apps/libresign')

vi.mock('@libresign/pdf-elements/src/utils/asyncReader.js', () => ({
	setWorkerPath: setWorkerPathMock,
}))

vi.mock('@nextcloud/router', () => ({
	generateFilePath: generateFilePathMock,
}))

const loadModule = async () => {
	vi.resetModules()
	setWorkerPathMock.mockClear()
	generateFilePathMock.mockClear()
	return await import('../../helpers/pdfWorker.js')
}

describe('ensurePdfWorker', () => {
	afterEach(() => {
		global.window.OC = undefined
	})
	it('configures worker path using app web root when available', async () => {
		global.window.OC = { appswebroots: { libresign: '/custom/libresign' } }
		const { ensurePdfWorker } = await loadModule()

		ensurePdfWorker()

		expect(setWorkerPathMock).toHaveBeenCalledWith('/custom/libresign/js/pdf.worker.min.mjs')
	})

	it('uses generateFilePath when app web root is unavailable', async () => {
		global.window.OC = { appswebroots: {} }
		const { ensurePdfWorker } = await loadModule()

		ensurePdfWorker()

		expect(generateFilePathMock).toHaveBeenCalledWith('libresign', '', '')
		expect(setWorkerPathMock).toHaveBeenCalledWith('/apps/libresign/js/pdf.worker.min.mjs')
	})

	it('only configures once even when called multiple times', async () => {
		global.window.OC = { appswebroots: { libresign: '/custom/libresign' } }
		const { ensurePdfWorker } = await loadModule()

		ensurePdfWorker()
		ensurePdfWorker()

		expect(setWorkerPathMock).toHaveBeenCalledTimes(1)
	})
})
