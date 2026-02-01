/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

const mockFileAction = vi.fn()
const mockRegisterFileAction = vi.fn()
const mockGetSidebar = vi.fn()
const mockLoadState = vi.fn(() => true)

vi.mock('@nextcloud/files', () => ({
	FileAction: mockFileAction,
	registerFileAction: mockRegisterFileAction,
	getSidebar: mockGetSidebar,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: mockLoadState,
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (app, text) => text,
}))

vi.mock('../constants.js', () => ({
	FILE_STATUS: {
		DRAFT: 0,
		SIGNED: 3,
	},
}))

vi.mock('../utils/fileStatus.js', () => ({
	getStatusLabel: (status) => `Status ${status}`,
	getStatusSvgInline: (status) => `<svg>${status}</svg>`,
}))

describe('showStatusInlineAction', () => {
	it('registers file action', async () => {
		await import('./showStatusInlineAction.js')

		expect(mockFileAction).toHaveBeenCalled()
		expect(mockRegisterFileAction).toHaveBeenCalled()
	})
})
