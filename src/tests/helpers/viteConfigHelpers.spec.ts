/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { isBuildWatchArgv } from '../../helpers/isBuildWatchArgv.js'

describe('vite config watch gating', () => {
	it('does not enable watch mode for a regular build command', () => {
		expect(isBuildWatchArgv(['node', 'vite', 'build'])).toBe(false)
	})

	it('enables watch mode for the long watch flag', () => {
		expect(isBuildWatchArgv(['node', 'vite', 'build', '--watch'])).toBe(true)
	})

	it('enables watch mode for the short watch flag', () => {
		expect(isBuildWatchArgv(['node', 'vite', 'build', '-w'])).toBe(true)
	})
})
