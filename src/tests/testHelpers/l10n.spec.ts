/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { createL10nMock, interpolateL10n } from './l10n.js'

describe('l10n test helper', () => {
	it('interpolates placeholders without dropping unknown tokens', () => {
		expect(interpolateL10n('Hello {name}, file {missing}', { name: 'Jane' })).toBe('Hello Jane, file {missing}')
	})

	it('provides the complete l10n contract by default', () => {
		const mock = createL10nMock()

		expect(mock.t('libresign', 'Hello {name}', { name: 'Jane' })).toBe('Hello Jane')
		expect(mock.translate('libresign', 'Welcome')).toBe('Welcome')
		expect(mock.n('libresign', '{count} file', '{count} files', 2)).toBe('2 files')
		expect(mock.translatePlural('libresign', '{count} item', '{count} items', 1)).toBe('1 item')
		expect(mock.getLanguage()).toBe('en')
		expect(mock.getLocale()).toBe('en')
		expect(mock.isRTL()).toBe(false)
	})

	it('allows overriding only the behavior a spec cares about', () => {
		const mock = createL10nMock({
			t: (_app, message, params) => `${interpolateL10n(message, params)}!`,
		})

		expect(mock.t('libresign', 'Signed by {name}', { name: 'Jane' })).toBe('Signed by Jane!')
		expect(mock.translate('libresign', 'Signed by {name}', { name: 'Jane' })).toBe('Signed by Jane!')
		expect(mock.n('libresign', '{count} file', '{count} files', 3)).toBe('3 files')
	})
})
