/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')
const root = path.resolve(__dirname, '../..')
const dir = path.join(root, 'build/sdk-generation/typescript')
const spec = JSON.parse(fs.readFileSync(path.join(root, 'openapi-full.json')))
const source = ts.createSourceFile('api.ts', fs.readFileSync(path.join(dir, 'api.ts'), 'utf8'), ts.ScriptTarget.Latest, true)
const classes = new Map()
for (const node of source.statements) {
  if (!ts.isClassDeclaration(node) || !node.name) {
    continue
  }
  classes.set(node.name.text, new Set(node.members.filter(ts.isMethodDeclaration).map(m => m.name.getText(source))))
}
const generated = new Map()
for (const tag of ['policies', 'identity', 'documents', 'requests', 'signing', 'appearance', 'verification', 'certificates', 'notifications', 'system']) {
  const name = tag[0].toUpperCase() + tag.slice(1) + 'Api'
  const doc = fs.readFileSync(path.join(dir, 'docs', name + '.md'), 'utf8')
  const rows = [...doc.matchAll(/^\|\[\*\*(\w+)\*\*\]\([^\n]*?\| \*\*(GET|POST|PUT|DELETE|PATCH)\*\* ([^ |]+)/gm)]
  for (const [, method, verb, url] of rows) {
    const key = `${verb} ${url}`
    if (!generated.has(key)) {
      generated.set(key, [])
    }
    generated.get(key).push({ name, method, exists: classes.get(name)?.has(method) === true })
  }
}
const rows = []
const ids = new Set()
let errors = 0
for (const [url, operations] of Object.entries(spec.paths)) {
  for (const [verb, op] of Object.entries(operations)) {
    if (!['get', 'post', 'put', 'delete', 'patch'].includes(verb)) {
      continue
    }
    const key = `${verb.toUpperCase()} ${url}`
    const found = generated.get(key) || []
    const expected = op.tags[0][0].toUpperCase() + op.tags[0].slice(1) + 'Api'
    const ok = !ids.has(op.operationId) && found.length === 1 && found[0].name === expected && found[0].exists
    ids.add(op.operationId)
    if (!ok) {
      errors++
    }
    rows.push(`| ${op.operationId} | ${key} | ${found.map(x => `${x.name}.${x.method}${x.exists ? '' : ' (missing)'}`).join(', ') || 'MISSING'} | ${ok ? 'OK' : 'FAIL'} |`)
    generated.delete(key)
  }
}
errors += generated.size
const output = ['<!--', '  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors', '  - SPDX-License-Identifier: AGPL-3.0-or-later', '-->', '', '# Generated operation inventory', '', '| operationId | HTTP operation | Generated class.method | Result |', '| --- | --- | --- | --- |', ...rows, '', `${rows.length} input operations; ${errors} inventory errors. Presence does not establish serialization compatibility.`, ''].join('\n')
fs.writeFileSync(path.join(root, 'build/sdk-generation/inventory.md'), output)
console.log(`${rows.length} operations; ${errors} inventory errors; build/sdk-generation/inventory.md`)
if (errors) {
  process.exitCode = 1
}
