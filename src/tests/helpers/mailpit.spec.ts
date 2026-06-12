/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { extractSignLink } from '../../../playwright/support/mailpit'

describe('extractSignLink', () => {
	it.each([
		[
			'absolute http URL',
			'Open http://localhost:8080/apps/libresign/p/sign/abc-123 to sign the document.',
			'/apps/libresign/p/sign/abc-123',
		],
		[
			'absolute https URL',
			'Open https://libresign.example/apps/libresign/p/sign/abc-123 to sign the document.',
			'/apps/libresign/p/sign/abc-123',
		],
		[
			'absolute URL with index.php prefix',
			'Open https://libresign.example/index.php/apps/libresign/p/sign/abc-123 to sign the document.',
			'/apps/libresign/p/sign/abc-123',
		],
		[
			'absolute URL with query and hash',
			'Open https://libresign.example/apps/libresign/p/sign/abc-123?from=email#section',
			'/apps/libresign/p/sign/abc-123?from=email#section',
		],
		[
			'relative sign path',
			'Click /apps/libresign/p/sign/abc-123 to continue.',
			'/apps/libresign/p/sign/abc-123',
		],
		[
			'relative sign path with index.php prefix',
			'Click /index.php/apps/libresign/p/sign/abc-123 to continue.',
			'/apps/libresign/p/sign/abc-123',
		],
		[
			'URL with trailing punctuation',
			'Sign now: https://libresign.example/apps/libresign/p/sign/abc-123).',
			'/apps/libresign/p/sign/abc-123',
		],
	])('extracts %s', (_label, body, expectedPath) => {
		expect(extractSignLink(body)).toBe(expectedPath)
	})

	it.each([
		'No LibreSign link here.',
		'https://libresign.example/apps/libresign/not-a-sign-link/abc-123',
		'/apps/libresign/not-a-sign-link/abc-123',
	])('returns null for invalid input: %s', (body) => {
		expect(extractSignLink(body)).toBeNull()
	})
})
