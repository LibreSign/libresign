/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// Normalized-input experiment: writes a disposable copy of openapi-full.json in
// which every `anyOf` schema keyword is renamed to `oneOf`. `oneOf` requires
// exactly one matching alternative, so the copy is not an equivalent validation
// contract. It is generator input only and must never replace the specification.
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const target = path.join(root, 'build/sdk-generation/normalized/openapi-full.oneof.json')
let rewritten = 0
function normalize(value) {
  if (Array.isArray(value)) return value.map(normalize)
  if (value === null || typeof value !== 'object') return value
  const isUnion = Array.isArray(value.anyOf)
  if (isUnion && 'oneOf' in value) throw new Error('Schema declares both anyOf and oneOf')
  if (isUnion) rewritten++
  return Object.fromEntries(Object.entries(value).map(([key, item]) => [isUnion && key === 'anyOf' ? 'oneOf' : key, normalize(item)]))
}
const output = normalize(JSON.parse(fs.readFileSync(path.join(root, 'openapi-full.json'), 'utf8')))
fs.mkdirSync(path.dirname(target), { recursive: true })
fs.writeFileSync(target, JSON.stringify(output, null, 4) + '\n')
console.log(`${rewritten} anyOf schemas rewritten to oneOf; ${path.relative(root, target)}`)
