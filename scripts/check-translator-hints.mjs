/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud associates each translator hint with only the first translation
 * call on the next line. Guard against multiple calls on a source line.
 */
import { execFileSync } from 'node:child_process'
import process from 'node:process'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const translationCall = /(?<![\w$])(?:t|n)\s*\(\s*(['"])libresign\1\s*,/g

/**
 * @param {string} source
 * @return {number[]}
 */
export function findAmbiguousTranslatorLines(source) {
	const badLines = []
	const lines = source.split(/\r?\n/)
	for (let index = 0; index < lines.length; index++) {
		const line = lines[index]
		if (/^\s*(?:\/\/|\*|<!--)/.test(line)) {
			continue
		}
		translationCall.lastIndex = 0
		const count = [...line.matchAll(translationCall)].length
		if (count > 1) {
			badLines.push(index + 1)
		}
	}
	return badLines
}

const translatorHint = /^\s*(?:\/\/|#|<!--|\/\*+|\*)\s*TRANSLATORS\b(.*)$/

/**
 * Detect the LibreSign convention: colonless and one hint per translation.
 *
 * @param {string} source
 * @return {{ line: number, message: string }[]}
 */
export function findTranslatorHintProblems(source) {
	const problems = []
	let previousWasHint = false
	const lines = source.split(/\r?\n/)
	for (let index = 0; index < lines.length; index++) {
		const line = lines[index]
		const hint = line.match(translatorHint)
		if (!hint) {
			if (line.trim()) {
				previousWasHint = false
			}
			continue
		}
		if (/^\s*:/.test(hint[1])) {
			problems.push({ line: index + 1, message: 'Use TRANSLATORS without a colon.' })
		}
		if (previousWasHint) {
			problems.push({ line: index + 1, message: 'Use only one TRANSLATORS hint for a translation.' })
		}
		previousWasHint = true
	}
	return problems
}

export function checkTrackedSourceFiles() {
	const files = execFileSync('git', ['ls-files', '-z', '--', 'src', 'lib', 'appinfo', 'tests', 'scripts'], { encoding: 'utf8' })
		.split('\0')
		.filter(path => /\.(?:vue|js|mjs|ts|php)$/.test(path) && !path.startsWith('src/types/openapi/') && !path.startsWith('scripts/tests/'))
	const errors = []
	for (const file of files) {
		const content = readFileSync(file, 'utf8')
		for (const line of findAmbiguousTranslatorLines(content)) {
			errors.push(`${file}:${line}: multiple t()/n() calls on one line; split the calls and add separate TRANSLATORS hints.`)
		}
		for (const problem of findTranslatorHintProblems(content)) {
			errors.push(`${file}:${problem.line}: ${problem.message}`)
		}
	}
	return errors
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const errors = checkTrackedSourceFiles()
	if (errors.length > 0) {
		process.stderr.write(errors.join('\n') + '\n')
		process.exitCode = 1
	} else {
		process.stdout.write('Translator-hint structure check passed.\n')
	}
}
