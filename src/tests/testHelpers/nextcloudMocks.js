/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Global Nextcloud environment mocks
 *
 * NOTE: Do NOT use vi.mock() here! It doesn't work in setupFiles.
 * Instead, add vi.mock() at the TOP of each individual test file that needs it.
 *
 * Example in your test file:
 *   vi.mock('@nextcloud/axios')
 *   vi.mock('@nextcloud/router')
 *   vi.mock('@nextcloud/auth', () => ({
 *     getCurrentUser: vi.fn(() => ({ uid: 'test' }))
 *   }))
 */

// Setup global translation functions
import { vi } from 'vitest'

global.t = vi.fn().mockImplementation((app, text) => text)
global.n = vi.fn().mockImplementation((app, singular, plural, count) =>
	count === 1 ? singular : plural
)
