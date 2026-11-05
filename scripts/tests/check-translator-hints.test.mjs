/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { findAmbiguousTranslatorLines } from '../check-translator-hints.mjs'

test('allows one translation call per line with an independent hint', () => {
	const source = [
		'// TRANSLATORS Action shown before a reviewer starts approving a document.',
		"const approve = t('libresign', 'Approve document')",
		'// TRANSLATORS Label used by an ordinary document signer.',
		"const sign = t('libresign', 'Sign document')",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [])
})

test('accepts both officially documented hint punctuation variants', () => {
	const source = [
		'// TRANSLATORS: Label to confirm a saved document.',
		"const save = t('libresign', 'Save')",
		'// TRANSLATORS Label to cancel a document edit.',
		"const cancel = t('libresign', 'Cancel')",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [])
})

test('blocks a one-line conditional with two translation calls', () => {
	const source = [
		'// TRANSLATORS Only the first translation receives the hint on this source line.',
		"const label = approved ? t('libresign', 'Approved') : t('libresign', 'Pending')",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [2])
})

test('blocks nested translations on a single source line', () => {
	const source = [
		"return t('libresign', 'Status: {status}', { status: t('libresign', 'Unknown') })",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [1])
})

test('does not count commented-out examples as runtime translations', () => {
	const source = [
		"// return [t('libresign', 'Save'), t('libresign', 'Cancel')]",
		'<!-- TRANSLATORS: Useful context in Vue -->',
		"<span>{{ t('libresign', 'Ready') }}</span>",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [])
})
