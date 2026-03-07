/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/// <reference types="@nextcloud/typings" />

declare global {
<<<<<<< HEAD
	interface OCAFilesSidebarTab {
		id: string
		name: string
		icon?: string
		iconSvg?: string
		enabled: (fileInfo: unknown) => boolean
		mount: (el: HTMLElement, fileInfo: unknown, context?: unknown) => void
		update: (fileInfo: unknown) => void
		destroy: (el?: HTMLElement) => void
	}

	interface OCAFilesSidebar {
		open(path: string): Promise<void>
		close?(): void
		setActiveTab(id: string): void
		Tab?: new (options: Partial<OCAFilesSidebarTab>) => OCAFilesSidebarTab
	}

	interface OCAFilesNamespace {
		Sidebar: OCAFilesSidebar
		[key: string]: unknown
||||||| parent of a84972d6d (chore(types): extend global app config typing)
=======
	interface LibreSignAppConfigApi {
		setValue: (app: string, key: string, value: string | number | boolean, options?: { success?: () => void; error?: () => void }) => void
		deleteKey?: (app: string, key: string) => void
>>>>>>> a84972d6d (chore(types): extend global app config typing)
	}

	interface LibreSignGlobalNamespace {
		fileInfo?: unknown
		pendingEnvelope?: {
			nodeType?: string
			files?: Array<{
				fileId?: number | string
				[key: string]: unknown
			}>
			}

			interface LibreSignAppConfigApi {
				setValue: (app: string, key: string, value: string | number | boolean, options?: { success?: () => void; error?: () => void }) => void
				deleteKey?: (app: string, key: string) => void
			settings?: {
	}

	interface OCAGlobalNamespace {
		Libresign: LibreSignGlobalNamespace
		Files?: OCAFilesNamespace
		[key: string]: unknown
	}

	interface Window {
		// Nextcloud Globals
		OC: Nextcloud.v29.OC
		OCA: OCAGlobalNamespace
		OCP: Nextcloud.v29.OCP & {
			AppConfig: LibreSignAppConfigApi
		}
	}

	const OC: Nextcloud.v29.OC
	const OCA: OCAGlobalNamespace
	const OCP: Nextcloud.v29.OCP & {
		AppConfig: LibreSignAppConfigApi
	}
}

export {}
