/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { findAmbiguousTranslatorLines, findTranslatorHintProblems } from '../check-translator-hints.mjs'

test('allows one translation call per line with an independent hint', () => {
	const source = [
		'// TRANSLATORS Action shown before a reviewer starts approving a document.',
		"const approve = t('libresign', 'Approve document')",
		'// TRANSLATORS Label used by an ordinary document signer.',
		"const sign = t('libresign', 'Sign document')",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [])
})

test('requires the LibreSign colonless convention for JavaScript and Vue hints', () => {
	const valid = [
		'// TRANSLATORS Label to confirm a saved document.',
		"const save = t('libresign', 'Save')",
		'<!-- TRANSLATORS Describes the review action. -->',
		"<span>{{ t('libresign', 'Review') }}</span>",
	].join('\n')
	assert.deepEqual(findTranslatorHintProblems(valid), [])

	const colon = ':'
	const invalid = [
		'// TRANSLATORS' + colon + ' Label to confirm a saved document.',
		"const save = t('libresign', 'Save')",
		'<!-- TRANSLATORS' + colon + ' Describes the review action. -->',
		"<span>{{ t('libresign', 'Review') }}</span>",
	].join('\n')
	assert.deepEqual(findTranslatorHintProblems(invalid), [
		{ line: 1, message: 'Use TRANSLATORS without a colon.' },
		{ line: 3, message: 'Use TRANSLATORS without a colon.' },
	])
})

test('blocks triplicated translator hints above one translation', () => {
	const source = [
		'<!-- TRANSLATORS Filter for approved documents. -->',
		'<!-- TRANSLATORS Filter for approved documents. -->',
		'<!-- TRANSLATORS Filter for approved documents. -->',
		"{{ t('libresign', 'Approved') }}",
	].join('\n')
	assert.deepEqual(findTranslatorHintProblems(source), [
		{ line: 2, message: 'Use only one TRANSLATORS hint for a translation.' },
		{ line: 3, message: 'Use only one TRANSLATORS hint for a translation.' },
	])
})

test('blocks two consecutive hints even if their text differs', () => {
	const source = [
		'// TRANSLATORS Describes a document state.',
		'// TRANSLATORS Describes an approval state.',
		"return t('libresign', 'Pending')",
	].join('\n')
	assert.deepEqual(findTranslatorHintProblems(source), [
		{ line: 2, message: 'Use only one TRANSLATORS hint for a translation.' },
	])
})

test('permits repeated context only for distinct translation calls', () => {
	const source = [
		'// TRANSLATORS Label on the approval button.',
		"return t('libresign', 'Approve')",
		'// TRANSLATORS Label on the approval button.',
		"return t('libresign', 'Approve')",
	].join('\n')
	assert.deepEqual(findTranslatorHintProblems(source), [])
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
		'<!-- TRANSLATORS Useful context in Vue -->',
		"<span>{{ t('libresign', 'Ready') }}</span>",
	].join('\n')
	assert.deepEqual(findAmbiguousTranslatorLines(source), [])
})
