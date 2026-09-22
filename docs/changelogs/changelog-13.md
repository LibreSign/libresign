<!--
 - SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
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

## 13.4.2 - 2026-09-21

### Security

- Security fixes and other improvements
## 13.4.1 - 2026-09-20

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations

### Fixed
- use the installed JSignPdf release version as the single source of truth [#8472](https://github.com/LibreSign/libresign/pull/8472)
- validate setup signatures with CA-signed certificates [#8474](https://github.com/LibreSign/libresign/pull/8474)

## 13.4.0 - 2026-09-19

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- support drag-and-drop PDF upload on the Validation page [#7933](https://github.com/LibreSign/libresign/pull/7933)
- add drag-and-drop document upload to the Files page [#7943](https://github.com/LibreSign/libresign/pull/7943)
- update JSignPdf to 3.1.0 with jsignpdf-php 3.0.0 [#8258](https://github.com/LibreSign/libresign/pull/8258)

### Changed
- update translations
- bump dependencies

### Fixed
- correct iOS Safari viewport height on sign requests [#7968](https://github.com/LibreSign/libresign/pull/7968)
- keep the signature-position step visible when the stamp still renders [#7997](https://github.com/LibreSign/libresign/pull/7997)
- improve identification-document LDAP join and approval status handling [#8021](https://github.com/LibreSign/libresign/pull/8021)
- promote certificate validity dates to the signer root [#8041](https://github.com/LibreSign/libresign/pull/8041)
- fix uploaded signature scaling [#8060](https://github.com/LibreSign/libresign/pull/8060)
- expose semantic PDF parser and validator error codes [#8188](https://github.com/LibreSign/libresign/pull/8188)
- improve external PDF validation [#8225](https://github.com/LibreSign/libresign/pull/8225) [#8250](https://github.com/LibreSign/libresign/pull/8250)
- trigger group search on the correct select event [#8243](https://github.com/LibreSign/libresign/pull/8243)
- improve TSA failure diagnostics [#8277](https://github.com/LibreSign/libresign/pull/8277)
- detect when signer search has more results [#8288](https://github.com/LibreSign/libresign/pull/8288)
- enforce envelope ownership when adding files [#8316](https://github.com/LibreSign/libresign/pull/8316)
- populate signer visible elements in validation responses [#8350](https://github.com/LibreSign/libresign/pull/8350)
- store identification documents of external signers under the file owner [#8370](https://github.com/LibreSign/libresign/pull/8370)
- stop overriding the pdf-elements worker [#8385](https://github.com/LibreSign/libresign/pull/8385)
- use the approver's own identity when signing an identification document [#8390](https://github.com/LibreSign/libresign/pull/8390)
- accept string node IDs from the Files sidebar when requesting a signature [#8397](https://github.com/LibreSign/libresign/pull/8397)
- stop deleting LibreSign appdata on every update [#8426](https://github.com/LibreSign/libresign/pull/8426)
- fix external page layout [#8435](https://github.com/LibreSign/libresign/pull/8435)

## 13.3.0 - 2026-07-15

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- validate PDF signatures without requiring Java [#7872](https://github.com/LibreSign/libresign/pull/7872)

### Changed
- update translations

### Fixed
- improve CRL generation performance and reliability [#7889](https://github.com/LibreSign/libresign/pull/7889)
- avoid stale autoload misses during upgrades [#7898](https://github.com/LibreSign/libresign/pull/7898)

## 13.2.7 - 2026-07-06

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations
- bump dependencies [#7804](https://github.com/LibreSign/libresign/pull/7804) [#7817](https://github.com/LibreSign/libresign/pull/7817) [#7849](https://github.com/LibreSign/libresign/pull/7849)
- surface setup checks in the admin overview and `occ setupchecks:check` [#7840](https://github.com/LibreSign/libresign/pull/7840)

### Fixed
- avoid autoload issues from the authoritative classmap configuration [#7775](https://github.com/LibreSign/libresign/pull/7775)
- cache generated CRL data in appdata correctly [#7782](https://github.com/LibreSign/libresign/pull/7782)
- prevent invalid user element rows from breaking node ID writes [#7810](https://github.com/LibreSign/libresign/pull/7810)
- honor notification preferences more consistently for email notifications [#7834](https://github.com/LibreSign/libresign/pull/7834)
- avoid transient CFSSL startup failures during setup [#7837](https://github.com/LibreSign/libresign/pull/7837)
- clarify the error shown when a signing link is opened in the wrong authenticated session [#7856](https://github.com/LibreSign/libresign/pull/7856)

## 13.2.6 - 2026-06-14

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Fixed
- skip non-signature /Contents entries while preserving valid signature extraction [#7734](https://github.com/LibreSign/libresign/pull/7734)
- run playwright in official container [#7744](https://github.com/LibreSign/libresign/pull/7744)
- psalm fixes [#7747](https://github.com/LibreSign/libresign/pull/7747)
- match local CRL distribution points by path, not by request host [#7751](https://github.com/LibreSign/libresign/pull/7751)

## 13.2.5 - 2026-06-12

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations
- bump dependencies [#7634](https://github.com/LibreSign/libresign/pull/7634) [#7637](https://github.com/LibreSign/libresign/pull/7637) [#7639](https://github.com/LibreSign/libresign/pull/7639)

### Fixed
- align getArchitectures typing with main [#7683](https://github.com/LibreSign/libresign/pull/7683)
- keep groups_request_sign JSON unicode serialization consistent [#7681](https://github.com/LibreSign/libresign/pull/7681)
- use default DataResponse success constructor in id docs [#7663](https://github.com/LibreSign/libresign/pull/7663)
- harden openapi contracts for sdk generators [#7665](https://github.com/LibreSign/libresign/pull/7665)
- align openapi file metadata contract with runtime payloads [#7660](https://github.com/LibreSign/libresign/pull/7660)
- expose ValidationURL and qrcode in signature stamp templates [#7658](https://github.com/LibreSign/libresign/pull/7658)
- render envelope validation data correctly for multi-file requests [#7649](https://github.com/LibreSign/libresign/pull/7649)
- remove stale addStyle icons call from TemplateLoader [#7642](https://github.com/LibreSign/libresign/pull/7642)
- support Twig date filter for ServerSignatureDate in JSign [#7646](https://github.com/LibreSign/libresign/pull/7646)

## 13.2.4 - 2026-04-25

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations

### Fixes
- harden signed file validation handling [#7601](https://github.com/LibreSign/libresign/pull/7601)
- hide account identifier from signer common name [#7610](https://github.com/LibreSign/libresign/pull/7610)
- allow signing for legacy certificates missing crl metadata [#7607](https://github.com/LibreSign/libresign/pull/7607)
- align signer email contract with api runtime behavior [#7603](https://github.com/LibreSign/libresign/pull/7603)
- simplify signer tsa and crl validation messaging [#7614](https://github.com/LibreSign/libresign/pull/7614)

## 13.2.3 - 2026-04-23

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies

### Fixes
- avoid crash on hostless ldap crl urls [#7527](https://github.com/LibreSign/libresign/pull/7527)
- stabilize root csr generation on openssl 3 [#7530](https://github.com/LibreSign/libresign/pull/7530)
- return 404 when pdf node is missing [#7535](https://github.com/LibreSign/libresign/pull/7535)
- enforce crl metadata with scoped legacy backfill [#7532](https://github.com/LibreSign/libresign/pull/7532)
- restore folderservice userid in throwiffilenotfound finally block [#7542](https://github.com/LibreSign/libresign/pull/7542)
- handle private validation url redirect and string error messages [#7546](https://github.com/LibreSign/libresign/pull/7546)
- allow signer thumbnail access and prefer file_id preview urls [#7548](https://github.com/LibreSign/libresign/pull/7548)
- restore horizontal pdf scroll on mobile [#7550](https://github.com/LibreSign/libresign/pull/7550)
- remove mobile orientation signing hint [#7553](https://github.com/LibreSign/libresign/pull/7553)
- dependency version stable33 [#7582](https://github.com/LibreSign/libresign/pull/7582)

## 13.2.2 - 2026-04-08

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies [#7452](https://github.com/LibreSign/libresign/pull/7452) [#7453](https://github.com/LibreSign/libresign/pull/7453) [#7456](https://github.com/LibreSign/libresign/pull/7456) [#7459](https://github.com/LibreSign/libresign/pull/7459) [#7457](https://github.com/LibreSign/libresign/pull/7457) [#7461](https://github.com/LibreSign/libresign/pull/7461) [#7463](https://github.com/LibreSign/libresign/pull/7463) [#7469](https://github.com/LibreSign/libresign/pull/7469) [#7471](https://github.com/LibreSign/libresign/pull/7471)

### Fixes
- fix: improve TSA DNS/network error guidance [#7430](https://github.com/LibreSign/libresign/pull/7430)
- fix: align signer and file UUID contracts [#7441](https://github.com/LibreSign/libresign/pull/7441)
- fix(validation): harden unified files contract [#7445](https://github.com/LibreSign/libresign/pull/7445)
- fix(Sign): submit each envelope file independently with its UUID [#7448](https://github.com/LibreSign/libresign/pull/7448)

## 13.2.1 - 2026-04-05

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- Update translations
- Bump dependencies [#7382](https://github.com/LibreSign/libresign/pull/7382)

### Fixes
- fix: interpolate sign-request uuid into account create URL [#7388](https://github.com/LibreSign/libresign/pull/7388)
- fix: handle visible signatures for envelope child files [#7385](https://github.com/LibreSign/libresign/pull/7385)
- fix: extract file descriptor when items have nested `file` key [#7391](https://github.com/LibreSign/libresign/pull/7391)
- fix: lazy load files sidebar tab [#7401](https://github.com/LibreSign/libresign/pull/7401)
- fix: CRL disabled flow and signing error UX [#7404](https://github.com/LibreSign/libresign/pull/7404)
- fix: prevent Imagick crash caused by invalid signature box dimensions [#7407](https://github.com/LibreSign/libresign/pull/7407)
- fix: validate engine name in migration to prevent installation failures [#7413](https://github.com/LibreSign/libresign/pull/7413)
- feat: mobile signature placement flow improvements [#7416](https://github.com/LibreSign/libresign/pull/7416)

## 13.2.0 - 2026-03-17

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- Advance the Vue 3 and TypeScript migration across signature flows, the files list and shared frontend infrastructure [#7166](https://github.com/LibreSign/libresign/pull/7166) [#7169](https://github.com/LibreSign/libresign/pull/7169) [#7173](https://github.com/LibreSign/libresign/pull/7173) [#7201](https://github.com/LibreSign/libresign/pull/7201) [#7251](https://github.com/LibreSign/libresign/pull/7251)
- Improve Playwright feedback, frontend test coverage and l10n mocking stability [#7195](https://github.com/LibreSign/libresign/pull/7195) [#7210](https://github.com/LibreSign/libresign/pull/7210) [#7272](https://github.com/LibreSign/libresign/pull/7272) [#7282](https://github.com/LibreSign/libresign/pull/7282)
- Update translations
- Bump dependencies [#7261](https://github.com/LibreSign/libresign/pull/7261) [#7286](https://github.com/LibreSign/libresign/pull/7286) [#7288](https://github.com/LibreSign/libresign/pull/7288)

### Fixes
- fix: restore signing flow after 13.1.x [#7175](https://github.com/LibreSign/libresign/pull/7175)
- fix(files): keep files list and validation state synchronized after signing [#7294](https://github.com/LibreSign/libresign/pull/7294)

## 13.1.3 - 2026-03-06

### Fixes
- fix: avoid router resolution in request signature tab modal
- test: cover modal urls in request signature tab

## 13.1.2 - 2026-03-06

### Fixes
- fix: include dist assets in appstore package
- ci: verify appstore package in release workflow
- ci: verify appstore package in nightly release

## 13.1.1 - 2026-03-06

### Fixes
- fix: include css assets in appstore package

## 13.1.0 - 2026-03-05

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- feat: vue3 typescript migration [#6995](https://github.com/LibreSign/libresign/pull/6995)
- feat: playwright e2e tests [#7007](https://github.com/LibreSign/libresign/pull/7007)
- feat: files list header restructure [#7011](https://github.com/LibreSign/libresign/pull/7011)
- feat: file list filters [#7019](https://github.com/LibreSign/libresign/pull/7019)
- feat: signature confirmation steps [#7041](https://github.com/LibreSign/libresign/pull/7041)
- feat: sign usign only php [#7073](https://github.com/LibreSign/libresign/pull/7073)
- feat: implement more e2e tests [#7080](https://github.com/LibreSign/libresign/pull/7080)
- feat: show confetti setting [#7085](https://github.com/LibreSign/libresign/pull/7085)
- feat: crl revocation checker [#7084](https://github.com/LibreSign/libresign/pull/7084)

### Changed
- Update translations
- Bump dependencies

### Fixes
- fix: vue3 component api migration [#7003](https://github.com/LibreSign/libresign/pull/7003)
- fix: nextcloud vue v9 compat [#7005](https://github.com/LibreSign/libresign/pull/7005)
- fix: files list grid toggle and status chip [#7009](https://github.com/LibreSign/libresign/pull/7009)
- fix: vue router 5 non path params [#7015](https://github.com/LibreSign/libresign/pull/7015)
- fix: confetti vue router 5 params [#7014](https://github.com/LibreSign/libresign/pull/7014)
- fix(fileupload): fix oversized preview image in confirm signature dialog [#7017](https://github.com/LibreSign/libresign/pull/7017)
- fix: a11y improvements [#7052](https://github.com/LibreSign/libresign/pull/7052)
- fix: files list sort accessibility [#7057](https://github.com/LibreSign/libresign/pull/7057)
- fix: draw signature tab accessibility [#7059](https://github.com/LibreSign/libresign/pull/7059)
- fix: use legacy pdfjs worker for browser compat [#7064](https://github.com/LibreSign/libresign/pull/7064)
- fix: signature engine key [#7067](https://github.com/LibreSign/libresign/pull/7067)
- fix: files integration actions and propfind [#7138](https://github.com/LibreSign/libresign/pull/7138)
- fix: orphan file delete null nodeid [#7140](https://github.com/LibreSign/libresign/pull/7140)
- fix(files): load libresign inline status icons with esm imports [#7147](https://github.com/LibreSign/libresign/pull/7147)

## 13.0.3 - 2026-02-20
### Fixes
- fix: prevent CA configuration loss during migrations [#6982](https://github.com/LibreSign/libresign/pull/6982)
- fix(migration): prevent CA file loss in Version13000Date20251031165700 [#6982](https://github.com/LibreSign/libresign/pull/6982)
- fix: add pki directory to DeleteOldBinaries whitelist [#6982](https://github.com/LibreSign/libresign/pull/6982)

### Changed
- feat(migration): add repair migration for CA structure [#6982](https://github.com/LibreSign/libresign/pull/6982)

## 13.0.2 - 2026-02-20
### Fixes
- fix: store signature at right user [#6972](https://github.com/LibreSign/libresign/pull/6972)
- fix: prevent double HTML escaping in footer template [#6967](https://github.com/LibreSign/libresign/pull/6967)
- fix: upper case first at status [#6963](https://github.com/LibreSign/libresign/pull/6963)

### Changed
- refactor: improve English text [#6954](https://github.com/LibreSign/libresign/pull/6954)
- refactor: improve text [#6952](https://github.com/LibreSign/libresign/pull/6952)

## 13.0.1 - 2026-02-18
### Fixes
- fix: docmdp first signature allow [#6944](https://github.com/LibreSign/libresign/pull/6944)
- fix: signature status propfind [#6945](https://github.com/LibreSign/libresign/pull/6945)
- fix: avoid empty crl engine default [#6940](https://github.com/LibreSign/libresign/pull/6940)

## 13.0.0 - 2026-02-17

⚠️ **MAJOR RELEASE - Breaking Changes** — The API has been completely redesigned. If you have existing API integrations, review the new API documentation before upgrading.

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

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
