<!--
 - SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and follows the requirements of the [Nextcloud Appstore Metadata specification](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#changelog).

Types of changes:
- *Added* for new features.
- *Changed* for changes in existing functionality.
- *Deprecated* for soon-to-be removed features.
- *Removed* for now removed features.
- *Fixed* for any bug fixes.
- *Security* in case of vulnerabilities. 

<!-- changelog-linker -->
<!-- changelog-linker -->
## 12.5.0 - 2026-07-15

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations

### Fixed
- improve CRL generation performance and reliability [#7888](https://github.com/LibreSign/libresign/pull/7888)
- avoid stale autoload misses during upgrades [#7897](https://github.com/LibreSign/libresign/pull/7897)

## 12.4.7 - 2026-07-06

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations
- bump dependencies [#7803](https://github.com/LibreSign/libresign/pull/7803) [#7816](https://github.com/LibreSign/libresign/pull/7816) [#7848](https://github.com/LibreSign/libresign/pull/7848)
- surface setup checks in the admin overview and `occ setupchecks:check` [#7839](https://github.com/LibreSign/libresign/pull/7839)

### Fixed
- avoid autoload issues from the authoritative classmap configuration [#7777](https://github.com/LibreSign/libresign/pull/7777)
- cache generated CRL data in appdata correctly [#7781](https://github.com/LibreSign/libresign/pull/7781)
- prevent invalid user element rows from breaking node ID writes [#7809](https://github.com/LibreSign/libresign/pull/7809)
- honor notification preferences more consistently for email notifications [#7833](https://github.com/LibreSign/libresign/pull/7833)
- avoid transient CFSSL startup failures during setup [#7836](https://github.com/LibreSign/libresign/pull/7836)
- clarify the error shown when a signing link is opened in the wrong authenticated session [#7859](https://github.com/LibreSign/libresign/pull/7859)

## 12.4.6 - 2026-06-14

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Fixes
- skip non-signature /Contents entries while preserving valid signature extraction [#7739](https://github.com/LibreSign/libresign/pull/7739)
- run playwright in official container [#7745](https://github.com/LibreSign/libresign/pull/7745)
- psalm fixes [#7748](https://github.com/LibreSign/libresign/pull/7748)
- match local CRL distribution points by path, not by request host [#7750](https://github.com/LibreSign/libresign/pull/7750)

## 12.4.5 - 2026-06-12

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- update loading message in request signature tab [#7723](https://github.com/LibreSign/libresign/pull/7723)
- Bump dependencies [#7633](https://github.com/LibreSign/libresign/pull/7633) [#7638](https://github.com/LibreSign/libresign/pull/7638) [#7640](https://github.com/LibreSign/libresign/pull/7640)

### Fixes
- cache generated CRL DER to avoid DB queries during validation [#7719](https://github.com/LibreSign/libresign/pull/7719)
- align getArchitectures typing with main [#7684](https://github.com/LibreSign/libresign/pull/7684)
- keep groups_request_sign JSON unicode serialization consistent [#7675](https://github.com/LibreSign/libresign/pull/7675)
- use default DataResponse success constructor in id docs [#7666](https://github.com/LibreSign/libresign/pull/7666)
- harden openapi contracts for sdk generators [#7664](https://github.com/LibreSign/libresign/pull/7664)
- align openapi file metadata contract with runtime payloads [#7659](https://github.com/LibreSign/libresign/pull/7659)
- expose ValidationURL and qrcode in signature stamp templates [#7657](https://github.com/LibreSign/libresign/pull/7657)
- render envelope validation data correctly for multi-file requests [#7648](https://github.com/LibreSign/libresign/pull/7648)
- remove stale addStyle icons call from TemplateLoader [#7643](https://github.com/LibreSign/libresign/pull/7643)
- support Twig date filter for ServerSignatureDate in JSign [#7645](https://github.com/LibreSign/libresign/pull/7645)

## 12.4.4 - 2026-04-26

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations

### Fixes
- harden signed file validation handling [#7600](https://github.com/LibreSign/libresign/pull/7600)
- hide account identifier from signer common name [#7609](https://github.com/LibreSign/libresign/pull/7609)
- allow signing for legacy certificates missing crl metadata [#7606](https://github.com/LibreSign/libresign/pull/7606)
- align signer email contract with api runtime behavior [#7604](https://github.com/LibreSign/libresign/pull/7604)
- simplify signer tsa and crl validation messaging [#7613](https://github.com/LibreSign/libresign/pull/7613)

## 12.4.3 - 2026-04-23

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies

### Fixes
- avoid crash on hostless ldap crl urls [#7526](https://github.com/LibreSign/libresign/pull/7526)
- stabilize root csr generation on openssl 3 [#7529](https://github.com/LibreSign/libresign/pull/7529)
- return 404 when pdf node is missing [#7536](https://github.com/LibreSign/libresign/pull/7536)
- enforce crl metadata with scoped legacy backfill [#7533](https://github.com/LibreSign/libresign/pull/7533)
- restore folderservice userid in throwiffilenotfound finally block [#7541](https://github.com/LibreSign/libresign/pull/7541)
- handle private validation url redirect and string error messages [#7545](https://github.com/LibreSign/libresign/pull/7545)
- allow signer thumbnail access and prefer file_id preview urls [#7547](https://github.com/LibreSign/libresign/pull/7547)
- restore horizontal pdf scroll on mobile [#7549](https://github.com/LibreSign/libresign/pull/7549)
- remove mobile orientation signing hint [#7552](https://github.com/LibreSign/libresign/pull/7552)
- fix min and max version [#7579](https://github.com/LibreSign/libresign/pull/7579)
- fix version [#7580](https://github.com/LibreSign/libresign/pull/7580)

## 12.4.2 - 2026-04-08

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies [#7451](https://github.com/LibreSign/libresign/pull/7451) [#7454](https://github.com/LibreSign/libresign/pull/7454) [#7455](https://github.com/LibreSign/libresign/pull/7455) [#7460](https://github.com/LibreSign/libresign/pull/7460) [#7458](https://github.com/LibreSign/libresign/pull/7458) [#7462](https://github.com/LibreSign/libresign/pull/7462) [#7464](https://github.com/LibreSign/libresign/pull/7464) [#7472](https://github.com/LibreSign/libresign/pull/7472) [#7470](https://github.com/LibreSign/libresign/pull/7470)

### Fixes
- fix: align signer and file UUID contracts [#7440](https://github.com/LibreSign/libresign/pull/7440)
- fix(validation): harden unified files contract [#7444](https://github.com/LibreSign/libresign/pull/7444)
- fix(Sign): submit each envelope file independently with its UUID [#7447](https://github.com/LibreSign/libresign/pull/7447)

## 12.4.1 - 2026-04-05

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies [#7381](https://github.com/LibreSign/libresign/pull/7381)

### Fixes
- fix: files menu shortcut and icons [#7340](https://github.com/LibreSign/libresign/pull/7340)
- fix: interpolate sign-request uuid into account create URL [#7387](https://github.com/LibreSign/libresign/pull/7387)
- fix: handle visible signatures for envelope child files [#7386](https://github.com/LibreSign/libresign/pull/7386)
- fix: extract file descriptor when items have nested `file` key [#7390](https://github.com/LibreSign/libresign/pull/7390)
- fix: lazy load files sidebar tab [#7400](https://github.com/LibreSign/libresign/pull/7400)
- fix: CRL disabled flow and signing error UX [#7403](https://github.com/LibreSign/libresign/pull/7403)
- fix: prevent Imagick crash caused by invalid signature box dimensions [#7406](https://github.com/LibreSign/libresign/pull/7406)
- fix: validate engine name in migration to prevent installation failures [#7412](https://github.com/LibreSign/libresign/pull/7412)

## 12.4.0 - 2026-03-17

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Changed
- Advance the Vue 3 and TypeScript migration across signature flows, the files list and shared frontend infrastructure [#7165](https://github.com/LibreSign/libresign/pull/7165) [#7168](https://github.com/LibreSign/libresign/pull/7168) [#7172](https://github.com/LibreSign/libresign/pull/7172) [#7200](https://github.com/LibreSign/libresign/pull/7200) [#7250](https://github.com/LibreSign/libresign/pull/7250)
- Improve Playwright feedback, frontend test coverage and l10n mocking stability [#7194](https://github.com/LibreSign/libresign/pull/7194) [#7211](https://github.com/LibreSign/libresign/pull/7211) [#7271](https://github.com/LibreSign/libresign/pull/7271) [#7281](https://github.com/LibreSign/libresign/pull/7281)
- Update translations
- Bump dependencies [#7263](https://github.com/LibreSign/libresign/pull/7263) [#7285](https://github.com/LibreSign/libresign/pull/7285) [#7287](https://github.com/LibreSign/libresign/pull/7287)

### Fixes
- fix: restore signing flow after 12.3.x [#7176](https://github.com/LibreSign/libresign/pull/7176)
- fix(files): keep files list and validation state synchronized after signing [#7293](https://github.com/LibreSign/libresign/pull/7293)

## 12.3.3 - 2026-03-06

### Fixes
- fix: avoid router resolution in request signature tab modal
- test: cover modal urls in request signature tab

## 12.3.2 - 2026-03-06

### Fixes
- fix: include dist assets in appstore package
- ci: verify appstore package in release workflow
- ci: verify appstore package in nightly release

## 12.3.1 - 2026-03-06

### Fixes
- fix: include css assets in appstore package

## 12.3.0 - 2026-03-05

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Added
- feat: vue3 typescript migration [#7089](https://github.com/LibreSign/libresign/pull/7089)
- feat: playwright e2e tests [#7091](https://github.com/LibreSign/libresign/pull/7091)
- feat: signature confirmation steps [#7092](https://github.com/LibreSign/libresign/pull/7092)
- feat: files list header restructure [#7098](https://github.com/LibreSign/libresign/pull/7098)
- feat: file list filters [#7102](https://github.com/LibreSign/libresign/pull/7102)
- feat: crl revocation checker [#7122](https://github.com/LibreSign/libresign/pull/7122)
- feat: show confetti setting [#7124](https://github.com/LibreSign/libresign/pull/7124)
- feat: sign usign only php [#7125](https://github.com/LibreSign/libresign/pull/7125)
- feat: implement more e2e tests [#7127](https://github.com/LibreSign/libresign/pull/7127)

### Changed
- Update translations
- Bump dependencies

### Fixes
- fix: vue3 component api migration [#7095](https://github.com/LibreSign/libresign/pull/7095)
- fix: nextcloud vue v9 compat [#7096](https://github.com/LibreSign/libresign/pull/7096)
- fix: files list grid toggle and status chip [#7097](https://github.com/LibreSign/libresign/pull/7097)
- fix: confetti vue router 5 params [#7099](https://github.com/LibreSign/libresign/pull/7099)
- fix: vue router 5 non path params [#7100](https://github.com/LibreSign/libresign/pull/7100)
- fix(fileupload): fix oversized preview image in confirm signature dialog [#7101](https://github.com/LibreSign/libresign/pull/7101)
- fix: external page vue3 migration [#7106](https://github.com/LibreSign/libresign/pull/7106)
- fix: a11y improvements [#7112](https://github.com/LibreSign/libresign/pull/7112)
- fix: files list sort accessibility [#7114](https://github.com/LibreSign/libresign/pull/7114)
- fix: draw signature tab accessibility [#7115](https://github.com/LibreSign/libresign/pull/7115)
- fix: use legacy pdfjs worker for browser compat [#7118](https://github.com/LibreSign/libresign/pull/7118)
- fix: signature engine key [#7119](https://github.com/LibreSign/libresign/pull/7119)
- fix: add override [#7123](https://github.com/LibreSign/libresign/pull/7123)
- fix: add pending code from main [#7128](https://github.com/LibreSign/libresign/pull/7128)
- fix: composer-lock [#7129](https://github.com/LibreSign/libresign/pull/7129)
- fix: update package-lock [#7130](https://github.com/LibreSign/libresign/pull/7130)
- fix: orphan file delete null nodeid [#7141](https://github.com/LibreSign/libresign/pull/7141)
- fix(files): load libresign inline status icons with esm imports [#7148](https://github.com/LibreSign/libresign/pull/7148)

## 12.2.3 - 2026-02-20
### Fixes
- fix: prevent CA configuration loss during migrations [#6981](https://github.com/LibreSign/libresign/pull/6981)
- fix(migration): prevent CA file loss in Version13000Date20251031165700 [#6981](https://github.com/LibreSign/libresign/pull/6981)
- fix: add pki directory to DeleteOldBinaries whitelist [#6981](https://github.com/LibreSign/libresign/pull/6981)

### Changed
- feat(migration): add repair migration for CA structure [#6981](https://github.com/LibreSign/libresign/pull/6981)

## 12.2.2 - 2026-02-20
### Fixes
- fix: store signature at right user [#6971](https://github.com/LibreSign/libresign/pull/6971)
- fix: prevent double HTML escaping in footer template [#6968](https://github.com/LibreSign/libresign/pull/6968)
- fix: upper case first at status [#6964](https://github.com/LibreSign/libresign/pull/6964)

### Changed
- refactor: improve English text [#6955](https://github.com/LibreSign/libresign/pull/6955)
- refactor: improve text [#6953](https://github.com/LibreSign/libresign/pull/6953)

## 12.2.1 - 2026-02-18
### Fixes
- fix: docmdp first signature allow [#6943](https://github.com/LibreSign/libresign/pull/6943)
- fix: signature status propfind [#6946](https://github.com/LibreSign/libresign/pull/6946)
- fix: avoid empty crl engine default [#6939](https://github.com/LibreSign/libresign/pull/6939)

## 12.2.0 - 2026-02-17

⚠️ **MAJOR RELEASE - Breaking Changes** — The API has been completely redesigned. If you have existing API integrations, review the new API documentation before upgrading.

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help upgrading or custom implementations? Contact us: contact@librecode.coop

### Added
- Envelopes to organize multiple signature workflows
- DocMDP (Document Modification Detection and Prevention)
- CRL (Certificate Revocation Lists) support
- TSA (Time Stamp Authority) integration
- Parallel and synchronous signatures
- Rich text editor for signature stamps
- Customizable footer
- Multi-channel notifications: WhatsApp, Telegram, Signal, XMPP, SMS
- Redesigned document identification flow
- CPS (Certification Practice Statement) support

### Changed
- Complete API redesign (breaking change)
- UI/UX improvements
- Performance optimizations
- Security enhancements

### Fixed
- Signature validation improvements
- Better error handling
- PDF compatibility fixes

---

## 12.1.0 - 2025-11-28
### Features
- feat: implement TSA [#5582](https://github.com/LibreSign/libresign/pull/5582)
- feat: display more informatin about certificate [#5590](https://github.com/LibreSign/libresign/pull/5590)
- feat: implement serial number with random number [#5595](https://github.com/LibreSign/libresign/pull/5595)
- feat: implement crl [#5626](https://github.com/LibreSign/libresign/pull/5626)
- Add suport message and button [#5640](https://github.com/LibreSign/libresign/pull/5640)
- feat: implement aki and ski [#5612](https://github.com/LibreSign/libresign/pull/5612)

### Changes
- Update translations
- Bump dependencies
- chore: valdiate display name at API side [#5564](https://github.com/LibreSign/libresign/pull/5564)
- chore: Refactor certificate chain processing with ordering [#5586](https://github.com/LibreSign/libresign/pull/5586)
- refactor: separate CA and leaf certificate configuration in OpenSSL e… [#5602](https://github.com/LibreSign/libresign/pull/5602)
- chore: remove unnecessary comment [#5609](https://github.com/LibreSign/libresign/pull/5609)
- chore: improve UX at sign screen [#5630](https://github.com/LibreSign/libresign/pull/5630)
- chore: improve error handler about Imagick [#5636](https://github.com/LibreSign/libresign/pull/5636)
- chore: remove to-do [#5647](https://github.com/LibreSign/libresign/pull/5647)

### Fixes
- fix: disable ocp at behat tests [#5581](https://github.com/LibreSign/libresign/pull/5581)
- fix(i18n): Fixed grammar [#5593](https://github.com/LibreSign/libresign/pull/5593)
- fix: replace keyCertSign with nonRepudiation in leaf certificate keyUsage [#5600](https://github.com/LibreSign/libresign/pull/5600)
- fix: use sha256 insteadof sha1 to leaf cert [#5608](https://github.com/LibreSign/libresign/pull/5608)
- fix: unit tests at PHP >= 8.4 [#5616](https://github.com/LibreSign/libresign/pull/5616)
- fix: prevent warning when send notifications [#5619](https://github.com/LibreSign/libresign/pull/5619)
- fix: use only classes compatible with old Nextcloud server versions [#5622](https://github.com/LibreSign/libresign/pull/5622)
- fix: prevent error when send reminders [#5637](https://github.com/LibreSign/libresign/pull/5637)
- fix: Only accept pfx files. [#5644](https://github.com/LibreSign/libresign/pull/5644)

## 12.0.1 - 2025-11-13
### Fixes
- fix: prevent error when the response dont have data [#5554](https://github.com/LibreSign/libresign/pull/5554)
- fix: workaround to make compatible with different structures [#5556](https://github.com/LibreSign/libresign/pull/5556)

## 12.0.0 - 2025-11-10
### Features
- feat(dependabot): add missing composer paths to config [#5468](https://github.com/LibreSign/libresign/pull/5468)
- feat: sign usign twofactor_gateway [#5499](https://github.com/LibreSign/libresign/pull/5499)
- feat: return next scheduled date [#5527](https://github.com/LibreSign/libresign/pull/5527)

### Changes
- Update translations
- Bump dependencies
- chore: add link to logs [#5457](https://github.com/LibreSign/libresign/pull/5457)
- chore: gridViewButtonLabel [#5463](https://github.com/LibreSign/libresign/pull/5463)
- chore: update workflows [#5486](https://github.com/LibreSign/libresign/pull/5486)
- chore: replace vendor by 3rdparty [#5513](https://github.com/LibreSign/libresign/pull/5513)
- chore: cover with more scenarios [#5524](https://github.com/LibreSign/libresign/pull/5524)
- chore: handle error and cover with tests [#5537](https://github.com/LibreSign/libresign/pull/5537)

### Fixes
- fix: ignore newest server config [#5461](https://github.com/LibreSign/libresign/pull/5461)
- fix: error at CI with PHP 8.3 [#5480](https://github.com/LibreSign/libresign/pull/5480)
- fix: rollback previous commit [#5483](https://github.com/LibreSign/libresign/pull/5483)
- fix: isolate all dependencies [#5491](https://github.com/LibreSign/libresign/pull/5491)
- fix: patcher for mpdf [#5496](https://github.com/LibreSign/libresign/pull/5496)
- fix: apply rector [#5502](https://github.com/LibreSign/libresign/pull/5502)
- fix: unit test after translation update [#5517](https://github.com/LibreSign/libresign/pull/5517)
- fix: unit test after implement submodule [#5522](https://github.com/LibreSign/libresign/pull/5522)
- fix: error handler to prevent JS error when receive 4xx from API [#5532](https://github.com/LibreSign/libresign/pull/5532)
- fix: make possible to test with dates [#5539](https://github.com/LibreSign/libresign/pull/5539)
- fix: add maxlength [#5541](https://github.com/LibreSign/libresign/pull/5541)
- fix: make the error message more specific [#5544](https://github.com/LibreSign/libresign/pull/5544)

## 12.0.0-beta.2 - 2025-09-13
### Changes
- Update translations
- Bump dependencies

### Fixes
- fix: typo [#5444](https://github.com/LibreSign/libresign/pull/5444)

## 12.0.0-beta.1 - 2025-09-12
### Changes
- Say hello to Nextcloud 32
