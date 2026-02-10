/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Import helpers in correct order
import './testHelpers/jsdomMocks.js'
import './testHelpers/nextcloudMocks.js'
import './testHelpers/vueMocks.js'

// Suppress expected error logs from tests that verify error handling
// These are intentional - tests mock component error handlers and verify they log properly
import { vi } from 'vitest'
vi.spyOn(console, 'error').mockImplementation(() => {})
