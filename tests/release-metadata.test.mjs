// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import test from 'node:test'
import { readReleaseMetadata } from '../scripts/release-metadata.mjs'

function fixture(version, changelog = '') {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'libresign-release-metadata-'))
	fs.mkdirSync(path.join(root, 'appinfo'), { recursive: true })
	fs.mkdirSync(path.join(root, 'docs/changelogs'), { recursive: true })
	fs.writeFileSync(path.join(root, 'appinfo/info.xml'), `<info><version>${version}</version></info>\n`)
	fs.writeFileSync(path.join(root, 'package.json'), JSON.stringify({ version }) + '\n')
	fs.writeFileSync(path.join(root, 'package-lock.json'), JSON.stringify({ version }) + '\n')
	const major = version.split('.')[0]
	fs.writeFileSync(path.join(root, 'docs/changelogs', `changelog-${major}.md`), changelog)
	return root
}

test('development versions only require the per-major changelog file', () => {
	const root = fixture('16.0.0-dev.2', '# Changelog\n')
	try {
		const metadata = readReleaseMetadata(root)
		assert.equal(metadata.major, 16)
		assert.equal(path.basename(metadata.changelog), 'changelog-16.md')
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
})

test('final releases require their exact section', () => {
	const root = fixture('15.0.4', '# Changelog\n\n## 15.0.4 - 2026-09-21\n')
	try {
		assert.equal(readReleaseMetadata(root).version, '15.0.4')
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
})

test('final releases fail when the release section is absent', () => {
	const root = fixture('15.0.4', '# Changelog\n\n## 15.0.3 - 2026-09-20\n')
	try {
		assert.throws(() => readReleaseMetadata(root), /does not contain version 15\.0\.4/)
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
})

test('version mirrors must match appinfo', () => {
	const root = fixture('15.0.4', '# Changelog\n\n## 15.0.4 - 2026-09-21\n')
	try {
		fs.writeFileSync(path.join(root, 'package.json'), JSON.stringify({ version: '15.0.3' }) + '\n')
		assert.throws(() => readReleaseMetadata(root), /Release version mismatch/)
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
})
