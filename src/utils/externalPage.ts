/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import '../styles/external-page.scss'

export function prepareExternalPage() {
	document.documentElement.classList.add('libresign-external-page')
	document.body.classList.add('libresign-external-page')
}
