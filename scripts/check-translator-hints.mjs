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

export function checkTrackedSourceFiles() {
	const files = execFileSync('git', ['ls-files', '-z', '--', 'src'], { encoding: 'utf8' })
		.split('\0')
		.filter(path => /\.(?:vue|js|ts)$/.test(path) && !path.startsWith('src/types/openapi/'))
	const errors = []
	for (const file of files) {
		for (const line of findAmbiguousTranslatorLines(readFileSync(file, 'utf8'))) {
			errors.push(`${file}:${line}: multiple t()/n() calls on one line; split the calls and add separate TRANSLATORS hints.`)
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
