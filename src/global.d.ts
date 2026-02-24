/*
 * SPDX-FileCopyrightText: 2024 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/// <reference types="@nextcloud/typings" />

declare global {
	interface Window {
		// Nextcloud Globals
		OC: Nextcloud.v29.OC
		OCA: Record<string, any>
		OCP: Nextcloud.v29.OCP
	}

	const OC: Nextcloud.v29.OC
	const OCA: Record<string, any>
}

export {}
