/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'

global.t = (app, text) => text
global.n = (app, singular, plural, count) => count === 1 ? singular : plural

global.console = {
	...console,
	warn: vi.fn(),
	error: vi.fn(),
	log: vi.fn(),
	debug: vi.fn(),
}
