/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { extractSignLink } from '../../../playwright/support/mailpit'

describe('extractSignLink', () => {
	it('returns the sign route pathname from an absolute email URL', () => {
		const body = 'Open http://localhost:8080/apps/libresign/p/sign/abc-123 to sign the document.'

		expect(extractSignLink(body)).toBe('/apps/libresign/p/sign/abc-123')
	})

	it('preserves index.php when it is present in the public sign URL', () => {
		const body = 'Open http://localhost:8080/index.php/apps/libresign/p/sign/abc-123 to sign the document.'

		expect(extractSignLink(body)).toBe('/index.php/apps/libresign/p/sign/abc-123')
	})

	it('returns null when the email body has no sign link', () => {
		expect(extractSignLink('No LibreSign link here.')).toBeNull()
	})
})
