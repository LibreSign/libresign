/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')

const root = resolve(__dirname, '../..')
const manifest = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8'))
const lock = JSON.parse(readFileSync(resolve(root, 'package-lock.json'), 'utf8'))
const compose = readFileSync(resolve(root, '.devcontainer/docker-compose.yml'), 'utf8')

const installed = lock.packages['node_modules/@playwright/test']?.version
assert.ok(installed, 'The lockfile must include @playwright/test')
assert.equal(
	lock.packages[''].devDependencies['@playwright/test'],
	manifest.devDependencies['@playwright/test'],
	'Update the Playwright manifest and lockfile together',
)

for (const dependency of ['playwright', 'playwright-core']) {
	assert.equal(
		lock.packages[`node_modules/${dependency}`]?.version,
		installed,
		`${dependency} must match @playwright/test in the lockfile`,
	)
}

const playwrightService = compose.match(/^  playwright:\s*\n((?:^    .*\n|^\s*\n)*)/m)?.[1]
assert.ok(playwrightService, 'Missing Playwright service in devcontainer Compose override')
const image = playwrightService.match(/^    image:\s*(\S+)\s*$/m)?.[1]
assert.ok(image, 'Pin the Playwright image in the devcontainer Compose override')
assert.equal(
	image,
	`mcr.microsoft.com/playwright:v${installed}-noble`,
	'Playwright Docker image must match package-lock.json',
)

console.log(`Playwright package and Dev Container image match: ${installed}`)
