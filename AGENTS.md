<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# LibreSign AI Agent Guide

This is the canonical project guide for any AI agent working on LibreSign. `AGENTS.md` is intentionally tool-agnostic and should be followed by AI coding assistants, local agents, CLI agents, and other automation tools.

If your AI platform does not automatically load `AGENTS.md`, read this file manually before making changes. Keep this file as the shared source of truth; any model-specific bridge file should only point here or mirror it without divergent guidance.

## How to Use This File

- Treat this document as the shared contract between human maintainers and AI agents. Prefer it over model-specific defaults whenever there is a conflict.
- Translate tool-specific wording into the equivalent capability of your environment. For example, “run in the container” may mean Docker Compose, a devcontainer task, a local wrapper, or a CI job, depending on the agent runtime.
- If your AI environment provides persistent memory, session history, subagents, plans, or TODO lists, use them to preserve context. For large investigations, follow the long-running task memory guidance below.
- Never rely on hidden context. Before editing, inspect the relevant files in the current checkout and verify assumptions against the code.
- Chat with the user in their preferred language.

## Core Principles

- Treat LibreSign as a Nextcloud app first: follow OCP APIs, Nextcloud filesystem/runtime conventions, and app-store packaging constraints.
- Prefer small, reviewable, tested changes. Do not rewrite unrelated code or reformat files outside the task.
- Read enough context before editing. For non-trivial changes, inspect the target file, nearby tests, and the service/controller/entity involved.
- Use clear, self-documenting names. Comments should explain business rules or why a workaround exists, not restate obvious code.
- Never lower quality gates, skip meaningful tests, suppress static-analysis warnings, or add broad `try/catch` blocks just to make failures disappear.
- When fixing a bug, add or update a focused regression test first whenever practical, then implement the fix.
- Prefer synthetic fixtures or minimal in-memory data for parser/business-rule tests. Avoid unnecessary I/O-heavy fixtures.
- Treat the backend/API as the source of truth for permissions, scopes, defaults, validation, delegation, inheritance, and security rules. Frontend checks are useful for UX, but every API endpoint must enforce the same rules and reject invalid or unauthorized direct requests.
- Keep pull requests and local changes focused on one concern. If a task grows toward multiple subsystems or several thousand lines of diff, recommend splitting it before opening a PR.
- Verify dependency names and packages against real package registries before suggesting or adding them. AI tools can hallucinate package names.
- If you cannot run a required validation command in the current environment, say exactly why and report what narrower checks you did run.

## Nextcloud Ecosystem Contribution Rules

- Follow LibreSign's local `CONTRIBUTING.md`, `SECURITY.md`, REUSE metadata, and release process first. When contributing to repositories under the Nextcloud organization, also follow Nextcloud's AI Contribution Policy and Contribution Guidelines.
- AI agents must not autonomously open issues, submit pull requests, post review comments, update PR metadata, merge, push, or send security reports. Perform outward-facing actions only when a human explicitly requests or approves them in the current conversation and the repository/platform policy allows it; when in doubt, prepare local drafts or changes for human review instead.
- AI-assisted work must be reviewed, cleaned up, understood, and tested by the human contributor before submission. “The AI wrote it” is never an acceptable explanation for code behavior.
- If AI-assisted commits are requested, follow the repository's required trailers and any Git commit template configured for the repository or contributor environment, such as `git config commit.template`. LibreSign requires DCO sign-off.
- Treat AI-disclosure trailers as policy- or template-driven. For example, add `Assisted-by: AGENT_NAME:MODEL_VERSION` only when the target repository's policy, the configured commit template, or the human contributor asks for it; Nextcloud organization repositories may also require PR disclosure of AI assistance.
- Security vulnerabilities must not be reported as public GitHub issues. Use the project's security policy and verified private reporting channel.
- Do not use AI output that reproduces incompatible copyrighted material or code from licenses incompatible with this repository.

## Generated and Vendored Files

- Do not hand-edit generated translations under `l10n/`; they are updated through the translation workflow.
- Do not hand-edit generated frontend build artifacts under `js/` or `css/` unless the task explicitly asks for a release/build artifact update. Change the source under `src/` and regenerate assets with the documented build command.
- Do not hand-edit generated OpenAPI specs or generated TypeScript API types. Regenerate them from backend annotations/contracts.
- Do not modify vendored third-party code under `vendor/`, tool-specific vendor directories, or generated/scoped dependency output unless the task is explicitly about dependency patching and the change is documented.
- Treat `3rdparty/` as its own repository/submodule with strict instructions. Before changing anything inside it, read and follow `3rdparty/README.md`; dependency updates must go through its Composer/PHP-Scoper workflow rather than manual edits to `3rdparty/vendor/` or generated scoped files.
- Every new source file must include the correct SPDX license header for its file type unless the file format cannot carry one; update `REUSE.toml` for exceptions.

## Repository and Environment Awareness

LibreSign is a Nextcloud app and is commonly developed inside a full Nextcloud server checkout, for example under `<nextcloud-root>/apps-extra/libresign` or `<nextcloud-root>/apps/libresign`. The exact host path and environment are contributor-specific and must never be hardcoded in versioned instructions or scripts.

Instead:

1. Determine the current repository root from the workspace.
2. Determine the Nextcloud root by walking up until the directory that contains `occ`, or by using the environment's documented server root mapping.
3. In wrapped or virtualized setups, host paths may be mapped to different paths inside the runtime; treat those mappings as environment details, not repository rules.

If the user says work was done in another checkout, compare the relevant files directly between the current checkout and that path before editing. Keep changes in the checkout the user identified as current.

Do not assume a specific runtime. Contributors may use host PHP, a devcontainer, Docker Compose, Podman, DDEV, Nix, a VM, CI, or another local Nextcloud setup. Use the project's available environment and clearly state when a command could not be run in the current setup.

For commands that may write to the source tree, cache directories, generated files, or mounted volumes, avoid running as `root`. Prefer a user mapping that preserves local file ownership, such as the same UID/GID as the host user, or the environment's documented application user. For commands that intentionally write into Nextcloud runtime data, use the web-server/application user configured by that environment.

Examples below are shown as plain commands from the LibreSign app root. If your environment requires a wrapper, run the equivalent command through that wrapper, for example a devcontainer shell, DDEV/Podman/Docker wrapper, VM shell, CI job, or the environment's documented app-user command.

```bash
composer test:unit -- --filter ClassName
```

Use root in containerized environments only for read-only inspection or when explicitly required and approved. Root-owned generated files can break the workspace.

LibreSign test runs can be destructive to local Nextcloud state. Unit and integration tests may clear runtime data, generated certificates, or app state. Run only focused commands, avoid root-owned writes, and do not casually rerun large suites while diagnosing a small issue.

## Architecture Overview

### Backend: PHP / Nextcloud OCP

- `lib/Service/`: business logic and orchestration (`SignFileService`, `AccountService`, `CertificatePolicyService`, `CrlService`, etc.).
- `lib/Handler/`: specialized engines, including signature handlers (`Pkcs12Handler`, `Pkcs7Handler`) and certificate engines (`OpenSslHandler`, `CfsslHandler`).
- `lib/Controller/`: HTTP and OCS controllers. OCS controllers commonly extend `AEnvironmentAwareController`.
- `lib/Db/`: database entities and mappers. Entities extend Nextcloud entities and mappers generally use `QBMapper`.
- `lib/AppInfo/Application.php`: bootstrap, dependency registration, event listeners, middleware, and integration hooks.

Important backend patterns:

- `CertificateEngineFactory` selects OpenSSL, CFSSL, or no certificate engine from configuration.
- `SignEngineFactory` selects PDF (`Pkcs12Handler`) or CMS/PKCS#7 (`Pkcs7Handler`) signing flows based on file type.
- Identity methods are loaded by namespace convention under `OCA\Libresign\Service\IdentifyMethod\*`.
- Cross-cutting behavior is event-driven with events such as `SignedEvent` and `SendSignNotificationEvent`.
- Use Nextcloud services, OCP interfaces, dependency injection, and typed method signatures. Avoid raw SQL unless there is no mapper/query-builder alternative.

### Policy System and Workbench

The policy system is a high-risk area because it controls signing behavior, validation, identity requirements, delegation, and user-visible preferences.

Backend policy guidelines:

- Keep each policy modular under `lib/Service/Policy/Provider/<PolicyName>/` whenever possible.
- Keep policy-specific rules out of generic services such as `PolicyService`, `PolicyController`, `DefaultPolicyResolver`, and `PolicyRegistry` unless the rule is truly cross-cutting.
- Prefer explicit provider metadata and backend validation over frontend-only checks, following the global rule that direct API calls must be safe even when the UI normally hides or disables an option.
- Model `supportedScopes`, `supportsUserPreference`, `supportsGroupAdminDelegation`, backend-only/helper/composite status, validators, and normalizers in the backend provider/spec.
- Treat infrastructure and trust policies conservatively. Examples such as TSA, CRL validation, worker configuration, and signing mode generally require system-level control unless a product decision says otherwise.
- For identity and document-verification policies, delegation must not silently weaken requirements set by a higher layer. Test attempts to remove mandatory factors or disable required documents.
- For cryptographic policies, do not allow weaker algorithms or downgrade paths without an explicit, tested compatibility decision.
- For legal/audit policies, separate technical implementation from product/legal decisions. If scope, preference visibility, or snapshot semantics are undecided, record the decision gap instead of guessing.

Frontend policy guidelines:

- Keep policy UI modules under `src/views/Settings/PolicyWorkbench/settings/<policy-name>/`.
- Prefer one `realDefinition.ts`, optional `model.ts`, policy-specific editor components, and mirrored tests in the same policy test folder.
- Central catalogs should register/compose policies, not accumulate complex per-policy business rules.
- Avoid growing `useRealPolicyWorkbench.ts` into a god file. If behavior is specific to one policy, keep it near that policy module unless it is shared orchestration.
- Render UI affordances from backend metadata whenever possible. Local frontend definitions may provide presentation details, but should not contradict backend enforcement.

### Frontend: Vue 3 / Vite / Vitest

The current frontend stack is Vue 3 with Vite and Vitest. Do not assume legacy Vue 2/Jest behavior.

- `src/views/`: main application views.
- `src/Components/`: reusable Vue components.
- `src/store/`: Pinia stores.
- `src/router/router.js`: SPA routing.
- `src/tests/`: Vitest tests using `happy-dom` and shared setup in `src/tests/setup.js`.
- OpenAPI-generated TypeScript types are refreshed with `npm run typescript:generate` after backend API/spec changes.

Use the Node and npm versions declared in `package.json` `engines`; do not duplicate those version constraints in agent instructions.

Current frontend scripts from `package.json`:
- Build: `npm run build`
- Development build: `npm run dev`
- Type check: `npm run ts:check`
- Unit tests: `npm test` or focused `npx vitest run <path>`
- E2E tests: `npm run test:e2e`

## Development Commands

Run commands from the app root unless noted. Do not assume PHP, Composer, Node, or Nextcloud commands are available directly on the host. If a command requires a full Nextcloud server checkout, PHP runtime, or configured database and the current environment does not provide one, say so explicitly instead of claiming it passed. Use the equivalent environment wrapper when commands are only available inside a container, devcontainer, VM, DDEV/Podman/Docker setup, CI job, or another configured runtime.

### PHP and Composer

```bash
composer install --prefer-dist
composer dump-autoload -o
composer psalm
composer cs:check
```

After adding or moving PHP classes, refresh Composer autoload with `composer dump-autoload -o` in the same runtime used for PHP checks.

### PHPUnit

Always run focused PHPUnit tests with `--filter`. Never run the entire PHPUnit suite during normal agent work unless the user explicitly asks.

```bash
composer test:unit -- --filter ClassName
composer test:unit -- --filter testMethodName
composer test:coverage -- --filter ClassName
```

Why the filter rule matters:

- Full PHPUnit runs are slow and resource-heavy.
- Focused tests give faster feedback and cleaner debugging output.
- Hidden CI can still run the full matrix later; local agent validation should be targeted.

### Behat Integration Tests

Run only the relevant feature or scenario. Do not run the full integration suite unless explicitly requested.

```bash
cd tests/integration
vendor/bin/behat -dl
vendor/bin/behat features/<path>.feature -v
```

Notes:

- Use `-dl` to discover available steps before writing or changing Behat scenarios.
- Use `-v` when you need `nextcloud.log` context.
- OCC output from steps may be logged to `nextcloud.log` instead of printed in Behat output.

### Frontend

```bash
npm ci
npm run dev
npm run build
npm run lint
npm run stylelint
npm run ts:check
npm test
npx vitest run src/tests/path/to/spec.ts
```

Prefer focused Vitest runs while developing. Use broader runs only when the change affects shared infrastructure.

## Testing Conventions

### PHPUnit Structure

Unit tests mirror the source tree under `tests/php/Unit/`:

```text
lib/Service/CrlService.php
  -> tests/php/Unit/Service/CrlServiceTest.php

lib/Controller/CrlApiController.php
  -> tests/php/Unit/Controller/CrlApiControllerTest.php

lib/Db/CrlMapper.php
  -> tests/php/Unit/Db/CrlMapperTest.php
```

Use PHPUnit mocks for dependencies and keep scenario-specific stubs explicit. If a default `method(...)->willReturn(...)` in `setUp()` needs to vary by test, prefer a callback backed by a mutable test property so later stubs do not get masked.

Prefer data providers for similar business-rule scenarios.

### Certificate and PDF Testing

- Certificate chains must be ordered from end-entity to intermediate(s) to root.
- `OrderCertificatesTrait` handles ordering and chain validation cases.
- Important tests include `testValidateCertificateChain`, `testICPBrasilRealWorldExample`, `testLyseonTechRealWorldExample`, and `OrderCertificatesTraitTest`.
- Prefer minimal synthetic PDFs/snippets for parser and signing-rule tests unless a real fixture is required.

### Frontend Test Pitfalls

- Shared mocks must load before importing the module under test. If a helper is imported after the real module, the test can bypass mocks and hit real localhost URLs.
- For specs that touch `@nextcloud/vue`, use the shared Nextcloud l10n mocks from `src/tests/setup.js` such as `globalThis.mockNextcloudL10n()` / `createL10nMock()` so helpers like `isRTL()` exist in CI.
- In Vue Test Utils, a stubbed child component that forwards `$attrs` and manually emits `click` can trigger parent click handlers twice. Declare `emits: ['click']` on the stub or avoid forwarding the native listener.
- After rebuilding frontend assets, an already-open browser tab may keep stale chunk URLs. Validate with a fresh page load and, when available, an accessibility snapshot.
- Policy Workbench tests should mirror the production module under test. Prefer `settings/<policy>/realDefinition.spec.ts`, `model.spec.ts`, `<Editor>.spec.ts`, or `useRealPolicyWorkbench.spec.ts` beside the relevant policy area.
- For request-sign/files Playwright flows, initialize user home storage before native Files uploads or DAV writes when needed; freshly provisioned users may not have a home yet.

## Code Style and Quality

### PHP

- Use strict, descriptive method names and typed parameters/returns.
- Keep services focused; extract complex logic into well-named private methods.
- Avoid broad exception handling. Catch only when there is an explicit recovery or correction path.
- Resolve static-analysis issues directly. Do not add suppressions as the fix.
- Normalize user/person names carefully. For regex normalization of mixed-case ASCII, lowercase before filtering or use a case-insensitive pattern such as `/[^a-z0-9]+/i`.
- Use `QBMapper` and the Nextcloud query builder for database access.
- In Nextcloud query builder helpers, use `selectAlias($qb->func()->max('table.column'), 'alias')` when an aggregate needs an alias; do not embed raw `MAX(...)` SQL or pass aliases to aggregate helper methods that do not support them.

### JavaScript, TypeScript, and Vue

- Keep Composition API logic readable and local unless reuse is clear.
- Use Pinia stores instead of Vuex.
- Keep translation calls near user-visible strings.
- Place `TRANSLATORS` comments directly before the relevant `t()` call.
- Extract translations into constants only when template/attribute syntax makes an inline translator comment impractical.
- Avoid refactoring text nodes into script constants just for style consistency.
- For long TypeScript barrel files or generated-looking export lists, anchor edits on unique surrounding symbols and re-read the changed region. Partial patches can duplicate blocks and create misleading parse errors far below the edit.
- Use Nextcloud CSS variables for colors, spacing, sizing, and state colors. Avoid hardcoded colors and magic numbers unless there is a deliberate, documented exception.
- Prefer logical CSS properties (`margin-inline-start`, `inset-inline-end`, `text-align: start`, etc.) so UI works in RTL languages.

## OpenAPI Workflow

When backend controller annotations or API contracts change:

```bash
composer openapi
npm run typescript:generate
```

Pattern: PHP controller annotations produce OpenAPI specs, then TypeScript types are regenerated from those specs.

If an API response shape, controller annotation, response definition, `jsonSerialize()` payload, or backend policy metadata contract changes, run both steps and review generated diffs carefully. Do not hand-edit generated OpenAPI specs or generated TypeScript API types.

## OCC Commands

Run OCC from the Nextcloud root using the current environment's wrapper when needed. Do not assume local host PHP is installed. The Nextcloud root is the runtime path that contains `occ`, which may differ from the host path in wrapped or virtualized setups. The examples below show the logical OCC invocation; run the equivalent command through the configured runtime when `php` is not available on the host.

```bash
php occ libresign:configure:check
php occ libresign:configure:cfssl
php occ libresign:configure:openssl
php occ libresign:crl:stats
php occ libresign:crl:cleanup
php occ libresign:crl:revoke <serial>
php occ libresign:developer:reset
php occ libresign:developer:sign-setup
php occ libresign:install --all
php occ libresign:uninstall
```

Environment gotchas:

- If Behat or admin-page scenarios fail with `#[\Override]` errors involving OCP interfaces, compare `vendor/nextcloud/ocp/OCP` with the Nextcloud `../../lib/public` tree and run `make updateocp` from the app root when they drift.
- If the built-in PHP server fails with Redis/distributed-cache bootstrap errors, do not add LibreSign-side hacks to tests. Prefer fixing or disabling the local Nextcloud Redis/distributed cache configuration for that environment.
- For PHPUnit runs that rely on `MockWebServer`, avoid putting `sys_temp_dir` under the managed Nextcloud data temp directory; test/runtime cleanup can remove it. Use a dedicated writable path such as `build/phpunit-tmp` inside the app.

## Integration Points

### Files App

- Sidebar integration is registered through `LoadSidebarListener`.
- Template loading is handled by `FilesTemplateLoader::register()` in application bootstrapping.
- File actions are exposed through the `JSActions` helper.
- Files-app and request-sign E2E tests can be sensitive to shared policy state. Use per-spec setup/restore guards for policies such as `groups_request_sign` so menu visibility and outgoing-mail assertions do not depend on worker order.

### Notifications

The notification flow is multi-channel:

1. `SendSignNotificationEvent` is dispatched.
2. Listeners handle domain-specific delivery, including notifications, email, activity, and two-factor gateway flows.
3. Keep notification changes consistent across all relevant channels.

Notification preference checks must fall back to Activity app/user config keys such as `notify_{channel}_{type}` when `OCA\Activity\UserSettings` is unavailable; local development environments may have notification flags without the Activity app being loaded.

### Background Jobs

- `OCA\Libresign\BackgroundJob\Reminder`: signature reminders.
- `OCA\Libresign\BackgroundJob\UserDeleted`: cleanup after user deletion.
- Jobs are registered in `appinfo/info.xml`.

## Git, GitHub, and Release Safety

- The default branch is `main`. Do not commit directly to `main` or `master` unless the user explicitly approves in the current chat.
- Do not push, open pull requests, update PR metadata, comment on issues/PRs, merge, or perform other outward-facing GitHub actions without explicit approval in the current chat.
- Prepare local changes and present the diff/status for review first.
- All commits must follow Conventional Commits and include DCO sign-off.
- If working on an integration branch or branch stack, verify the branch strategy with the user before committing or pushing. Do not push directly to integration branches unless explicitly approved in the current conversation.

Commit format:

```text
<type>: <short description>

[optional body]

Signed-off-by: Your Name <your.email@example.com>
```

Use `git commit -s` or `git commit --signoff`. Missing sign-off fails LibreSign DCO checks.

If the last local commit is missing the sign-off, fix it before pushing with `git commit --amend -s --no-edit`.

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`.

For outward-facing GitHub text such as PR titles, PR descriptions, issue comments, and review comments, use English unless the user explicitly requests another language.

### Release Work

- Use the published LibreSign release process documentation as the release source of truth: https://docs.libresign.coop/developer_manual/release-process.html
- When changing the release or deployment process, update the source documentation in https://github.com/LibreSign/documentation/ so the published docs remain the source of truth.
- Important behavior and workflows for users, administrators, and developers should be documented in https://github.com/LibreSign/documentation/ and kept current for the active LibreSign major release, following the same living-documentation model used by Nextcloud Server. Avoid creating parallel legacy notes or local workaround documents when the official documentation should be updated instead.
- Keep release PRs scoped to release files such as `CHANGELOG.md`, `appinfo/info.xml`, `package.json`, and `package-lock.json` unless the documented process says otherwise.
- Patch releases generally update the changelog on `main`, backport to stable branches, and publish oldest stable first.
- Before release PRs or backports, fetch current `main`, active `stable*` branches, and tags; stale refs can hide merged backports.
- Use local notes files for multiline GitHub release text instead of inline shell heredocs when possible.

## AI Agent Workflow

Any AI agent working on this repository should follow this loop:

1. Understand the request and identify affected backend/frontend/test areas.
2. Inspect existing code and tests before editing.
3. Add or update focused regression tests when behavior changes.
4. Make the smallest coherent change that satisfies the request and tests.
5. Run the narrowest relevant validation commands.
6. Re-check the diff for accidental generated files, unrelated formatting, root-owned artifacts, or stale build output.
7. Report what changed, what was validated, and what remains unverified.

If the AI environment supports todo lists, maintain one for multi-step work. If it supports subagents, use read-only subagents for broad codebase exploration and keep implementation in the main working context.

If advanced IDE tools are unavailable, fall back to plain repository operations: use `grep`/`rg` for search, read whole relevant files before editing, inspect `git diff`, and run the documented shell commands. Do not skip validation just because a platform-specific tool is missing.

### Long-Running Task Memory

For large audits, release work, branch-stack work, or PRs that span many files:

1. Create a local task folder under `.ai-local/<task>/`; this repository ignores `.ai-local/` in `.gitignore` so agents can safely keep scratch notes there.
2. If you must reuse a different local scratch path, add that path to `.git/info/exclude` rather than versioned `.gitignore`, unless the user explicitly wants a shared ignore rule.
3. Store the original prompt, current branch/HEAD/base, checklist, files reviewed, decisions, validations, known flakes, and next steps.
4. Re-read that note before each major phase or after context compaction/session changes.
5. Before finishing, verify the local memory folder is not staged or tracked.

## Cross-Agent Compatibility Contract

These instructions are designed to be portable across AI tools:

- The file uses plain Markdown with no required tool-specific frontmatter or syntax.
- Commands are standard shell, npm, Composer, PHPUnit, Behat, and OCC examples; environment wrappers are intentionally described as adaptable.
- Project facts are explicit instead of relying on hidden IDE, editor, or tool state.
- Tool-specific behavior is optional and conditional, for example “if the AI environment supports todo lists”.
- Agents that cannot use a named capability should use the closest safe equivalent, or report the limitation and proceed with manual inspection.

Quick compatibility check for any AI agent before work starts:

1. Can it read `AGENTS.md` from the repository root?
2. Can it inspect files before editing and show a diff afterward?
3. Can it run or request the documented PHP/npm validation commands in the configured environment?
4. Can it keep long-running task notes out of Git?
5. Can it follow approval rules for commits, pushes, PRs, and GitHub actions?

If the answer to any item is “no”, the agent must state the limitation and ask for a safe alternative instead of guessing.

If another AI agent cannot auto-discover this file, give it the repository root plus this instruction: “Read `AGENTS.md` and follow it as the LibreSign project guide before making changes.”

---

Key principle: LibreSign integrates tightly with Nextcloud architecture, certificate/signature workflows, and app-store release constraints. Always validate changes in a configured Nextcloud development environment and keep tests focused, reproducible, and meaningful.
