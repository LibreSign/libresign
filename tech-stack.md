<!--
 - SPDX-FileCopyrightText: 2024-2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
---
title: "Technology Stack"
type: analysis
status: active
domain: infra
layer: environment
owner: engineering
date: 2026-05-29
commit: "HEAD"
supersedes: []
superseded_by: ""
depends_on: []
related: []
---

# Technology Stack

## Runtime

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | PHP | 8.2+ |
| Framework | Nextcloud OCP | Nextcloud 34 |
| Frontend language | TypeScript | 5.x |
| Frontend framework | Vue 3 | Composition API |
| Build tool | Vite | `@nextcloud/vite-config` |
| Runtime | Node.js | ^24.0.0 |

## Core Dependencies

| Component | Technology | Purpose |
|-----------|-----------|---------|
| PDF engine | pdfjs-dist | v5.x — PDF rendering and signing |
| State management | Pinia | Vue state management |
| Validation | Vuelidate | v2 — form validation |
| Crypto (PHP) | phpseclib/phpseclib | Crypto primitives |
| Signing (PHP) | jeidison/signer-php | Signing operations |
| Routing | vue-router | v5 — SPA routing |

## Build & Dev Tools

| Tool | Purpose |
|------|---------|
| `npm run build` | Production JS/CSS build |
| `npm run dev` | Development build |
| `npm run watch` | Development build with watcher |
| `npm run serve` | Vite dev server |
| `npm run ts:check` | TypeScript type checking |
| `npm run lint` | ESLint |
| `npm run stylelint` | Stylelint on SCSS/Vue |
| `composer run lint` | PHP syntax lint |
| `composer run cs:check` | PHP-CS-Fixer dry-run |
| `composer run cs:fix` | PHP-CS-Fixer fix |
| `composer run psalm` | Psalm static analysis |
| `composer run openapi` | Regenerate OpenAPI specs + TS types |
| `composer run test:unit` | PHPUnit unit tests |

## Policy Enforcement

| Tool | Policy Section | Enforcement |
|------|---------------|-------------|
| `scripts/policy-check.sh` | §4, §16 | Pre-commit + manual |
| `.github/workflows/policy-enforcement.yml` | §4, §16 | CI blocking |
| `.github/workflows/gate-enforcement.yml` | §5 | CI blocking |
| `.github/workflows/ai-validation.yml` | §16 | CI blocking |

## External Services

| Service | Purpose | Status |
|---------|---------|--------|
| cfssl | Certificate generation and management | Required |
| JSignPdf | PDF signing (Java-based) | Required |
| pdftk | PDF manipulation | Required |
| Java runtime | JSignPdf dependency | Required |

## Secret Management

| Secret Type | Storage | Rotation |
|-------------|---------|----------|
| Database credentials | Nextcloud config | Admin-managed |
| API keys | Nextcloud `IConfig` | On suspicion of leak |
| Certificate keys | Encrypted storage / HSM | Annually or on compromise |
