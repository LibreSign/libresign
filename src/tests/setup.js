/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Test setup following Nextcloud apps pattern (mail, calendar, text)
 * 
 * Setup files should contain ONLY global environment configuration.
 * Package mocks (vi.mock) must be in individual test files, not here.
 */

// Import helpers in correct order
import './testHelpers/jsdomMocks.js'
import './testHelpers/nextcloudMocks.js'
import './testHelpers/vueMocks.js'

