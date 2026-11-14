/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { spawnSync } = require('node:child_process')
const { test } = require('node:test')

test('generation clears only its output and protects inputs and build siblings', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sdk-generation-'))
  try {
    fs.mkdirSync(path.join(root, 'tests/sdk-generation'), { recursive: true })
    fs.mkdirSync(path.join(root, 'build/sdk-generation/typescript'), { recursive: true })
    fs.mkdirSync(path.join(root, 'bin'))
    const script = path.join(root, 'tests/sdk-generation/generate.sh')
    fs.copyFileSync(path.join(__dirname, 'generate.sh'), script)
    // Stub Docker: test filesystem behavior without generating or editing a client.
    fs.writeFileSync(path.join(root, 'bin/docker'), '#!/bin/sh\nexit 0\n', { mode: 0o755 })
    fs.writeFileSync(path.join(root, 'build/sdk-generation/keep'), 'sibling')
    const output = path.join(root, 'build/sdk-generation/typescript')
    fs.writeFileSync(path.join(output, 'stale.ts'), 'stale')
    const run = (...args) => spawnSync('sh', [script, ...args], {
      encoding: 'utf8', env: { ...process.env, PATH: `${root}/bin:${process.env.PATH}` },
    })
    assert.equal(run().status, 0)
    assert.equal(fs.existsSync(path.join(output, 'stale.ts')), false)
    assert.equal(fs.readFileSync(path.join(root, 'build/sdk-generation/keep'), 'utf8'), 'sibling')
    for (const invalid of ['build/sdk-generation/', 'build/sdk-generation/.', 'build/sdk-generation//', 'build/sdk-generation/./', 'build/sdk-generation/../', 'src']) {
      assert.notEqual(run(invalid).status, 0, invalid)
    }
    fs.writeFileSync(path.join(output, 'input.json'), '{}')
    assert.notEqual(run('build/sdk-generation/typescript', 'build/sdk-generation/typescript/input.json').status, 0)
    assert.equal(fs.existsSync(path.join(output, 'input.json')), true)
    assert.notEqual(run('build/sdk-generation/typescript', 'build/sdk-generation/./typescript/input.json').status, 0)
    fs.symlinkSync(output, path.join(root, 'build/sdk-generation/link'))
    assert.notEqual(run('build/sdk-generation/link/child').status, 0)
    assert.notEqual(run('build/sdk-generation/typescript', 'build/sdk-generation/link/input.json').status, 0)
    assert.equal(fs.existsSync(path.join(output, 'input.json')), true)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})
