/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ensureWorkerReady } from '@libresign/pdf-elements'

let configured = false

export const ensurePdfWorker = (): void => {
	if (configured) {
		return
	}
	configured = true
	void ensureWorkerReady()
}
