/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateFilePath } from '@nextcloud/router'
import { setWorkerPath } from '@libresign/pdf-elements/src/utils/asyncReader.js'

let configured = false

export const ensurePdfWorker = () => {
	if (configured) {
		return
	}

	if (setWorkerPath) {
		const appWebRoot = (window?.OC?.appswebroots?.libresign)
			|| generateFilePath('libresign', '', '')
		const base = appWebRoot.replace(/\/$/, '')
		setWorkerPath(`${base}/js/pdf.worker.min.mjs`)
	}
	configured = true
}
