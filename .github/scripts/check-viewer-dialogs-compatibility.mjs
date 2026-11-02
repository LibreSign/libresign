/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { spawnSync } from 'node:child_process'
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import process from 'node:process'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const viewerManifest = join(root, 'src/viewer/package.json')

export function viewerCandidates(tags) {
	// The v2 Viewer is currently published under "next", not "latest".
	return [...new Set([tags.latest, tags.next].filter(
		version => typeof version === 'string' && Number(version.split('.')[0]) >= 2,
	))]
}

export function usesOnlyDialogs8(lock) {
	const packages = lock.packages ?? {}
	return Boolean(packages['node_modules/@nextcloud/viewer']
		&& packages['node_modules/@nextcloud/dialogs']?.version?.startsWith('8.')
		&& Object.entries(packages).every(([path, entry]) =>
			!path.endsWith('/@nextcloud/dialogs') || entry.version?.startsWith('8.')))
}

function npm(args, cwd = root) {
	const result = spawnSync('npm', args, { cwd, encoding: 'utf8', timeout: 180_000 })
	if (result.error) {
		throw result.error
	}
	return result
}

function appendSummary(message) {
	if (process.env.GITHUB_STEP_SUMMARY) {
		writeFileSync(process.env.GITHUB_STEP_SUMMARY, message + '\n', { flag: 'a' })
	}
}

function probe(version, dialogsRange) {
	const directory = mkdtempSync(join(tmpdir(), 'libresign-viewer-compat-'))
	try {
		writeFileSync(join(directory, 'package.json'), JSON.stringify({
			name: 'libresign-viewer-compatibility-check',
			version: '0.0.0',
			private: true,
			dependencies: {
				'@nextcloud/dialogs': dialogsRange,
				'@nextcloud/viewer': version,
			},
		}))
		const install = npm([
			'install', '--package-lock-only', '--strict-peer-deps', '--ignore-scripts',
			'--no-audit', '--no-fund',
		], directory)
		if (install.status !== 0) {
			const output = install.stderr + install.stdout
			if (/\bERESOLVE\b/.test(output)) {
				return false
			}
			throw new Error(`Could not check Viewer ${version}: ${output.slice(-1600)}`)
		}

		const lock = JSON.parse(readFileSync(join(directory, 'package-lock.json'), 'utf8'))
		if (!usesOnlyDialogs8(lock)) {
			return false
		}

		// A valid lockfile alone is insufficient: verify actual installation.
		const cleanInstall = npm(['ci', '--ignore-scripts', '--no-audit', '--no-fund'], directory)
		if (cleanInstall.status !== 0) {
			throw new Error(`Viewer ${version} install failed: ${cleanInstall.stderr.slice(-1600)}`)
		}
		const tree = npm(['ls', '@nextcloud/viewer', '@nextcloud/image-editor', '@nextcloud/dialogs', '--all'], directory)
		if (tree.status !== 0) {
			throw new Error(`Viewer ${version} dependency tree failed: ${tree.stderr.slice(-1600)}`)
		}
		return true
	} finally {
		rmSync(directory, { recursive: true, force: true })
	}
}

function main() {
	if (!existsSync(viewerManifest)) {
		process.stdout.write('No isolated Viewer package; compatibility cleanup has already happened.\n')
		return
	}

	const dialogsRange = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'))
		.dependencies['@nextcloud/dialogs']
	const tagsResult = npm(['view', '@nextcloud/viewer', 'dist-tags', '--json'])
	if (tagsResult.status !== 0) {
		throw new Error(`Could not retrieve Viewer versions: ${tagsResult.stderr.slice(-1600)}`)
	}

	const candidates = viewerCandidates(JSON.parse(tagsResult.stdout))
	if (candidates.length === 0) {
		throw new Error('No Nextcloud Viewer v2+ release tag found to check.')
	}
	for (const version of candidates) {
		process.stdout.write(`Checking Viewer ${version} against dialogs ${dialogsRange} and Image Editor...\n`)
		if (probe(version, dialogsRange)) {
			const message = [
				'## Native Viewer can potentially share dialogs 8',
				`Viewer ${version} resolved and installed with dialogs ${dialogsRange} without peer overrides.`,
				'**Review and remove the temporary Viewer isolation:**',
				'- Install the compatible Viewer directly in the root `package.json` and regenerate `package-lock.json`.',
				'- Remove `src/viewer/` and the root `postinstall`.',
				'- Restore the direct import in `src/utils/viewer.js` and its mock in `src/tests/utils/viewer.spec.ts`.',
				'- Remove the nested Dependabot directory and obsolete REUSE exemptions.',
				'- Reassess Vue deduplication and remove this monitor after cleanup.',
				'- Verify signed-UUID PDF privacy, unit tests, Playwright, TypeScript and build.',
			].join('\n')
			appendSummary(message)
			process.stderr.write('::error title=Viewer migration cleanup available::Remove the isolated Viewer package; see the job summary.\n')
			process.exitCode = 1
			return
		}
	}
	process.stdout.write('No compatible Viewer dependency tree yet; the temporary isolation remains necessary.\n')
	appendSummary('No Viewer release currently resolves with dialogs 8. No changes needed.')
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
	try {
		main()
	} catch (error) {
		process.stderr.write(`${error.stack ?? error}\n`)
		process.exitCode = 1
	}
}
