/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/// <reference types="@nextcloud/typings" />

declare global {
	interface LibreSignGlobalNamespace {
		fileInfo?: unknown
		pendingEnvelope?: {
			nodeType?: string
			files?: Array<{
				fileId?: number | string
				[key: string]: unknown
			}>
			filesCount?: number
			signers?: unknown[]
			settings?: {
				path?: string
				[key: string]: unknown
			}
			[key: string]: unknown
		}
		[key: string]: unknown
	}

	interface OCAGlobalNamespace {
		Libresign: LibreSignGlobalNamespace
		[key: string]: unknown
	}

	interface Window {
		// Nextcloud Globals
		OC: Nextcloud.v29.OC
		OCA: OCAGlobalNamespace
		OCP: Nextcloud.v29.OCP
	}

	const OC: Nextcloud.v29.OC
	const OCA: OCAGlobalNamespace
}

export {}
