// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

export function readReleaseMetadata(root = '.') {
	const infoPath = path.join(root, 'appinfo/info.xml')
	const packagePath = path.join(root, 'package.json')
	const lockPath = path.join(root, 'package-lock.json')

	const info = fs.readFileSync(infoPath, 'utf8')
	const match = info.match(/<version>([^<]+)<\/version>/)
	if (!match) {
		throw new Error('Unable to read app version from appinfo/info.xml')
	}

	const version = match[1]
	const packageVersion = JSON.parse(fs.readFileSync(packagePath, 'utf8')).version
	const lockVersion = JSON.parse(fs.readFileSync(lockPath, 'utf8')).version

	if (packageVersion !== version || lockVersion !== version) {
		throw new Error(`Release version mismatch: info.xml=${version} package.json=${packageVersion} package-lock.json=${lockVersion}`)
	}

	const major = version.split('.', 1)[0]
	if (!/^\d+$/.test(major)) {
		throw new Error(`Invalid app major in release version: ${version}`)
	}

	const changelog = path.join(root, 'docs/changelogs', `changelog-${major}.md`)
	if (!fs.existsSync(changelog)) {
		throw new Error(`Missing changelog for app major ${major}: ${changelog}`)
	}

	if (!/-dev(?:\.|$)/.test(version)) {
		const content = fs.readFileSync(changelog, 'utf8')
		const escaped = version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
		const heading = new RegExp(`^## \\[?${escaped}\\]?(?:\\s+-|\\s*$)`, 'm')
		if (!heading.test(content)) {
			throw new Error(`Release changelog does not contain version ${version}: ${changelog}`)
		}
	}

	return { version, major: Number(major), changelog }
}

function main() {
	const command = process.argv[2] ?? 'verify'
	const root = process.argv[3] ?? '.'
	const metadata = readReleaseMetadata(root)

	switch (command) {
	case 'verify':
		console.log(metadata.changelog)
		break
	case 'version':
		console.log(metadata.version)
		break
	case 'changelog':
		console.log(metadata.changelog)
		break
	default:
		throw new Error(`Unknown release metadata command: ${command}`)
	}
}

if (process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1])) {
	try {
		main()
	} catch (error) {
		console.error(error instanceof Error ? error.message : error)
		process.exit(1)
	}
}
