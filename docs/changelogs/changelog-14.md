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

## 14.3.0 - 2026-09-22

### Added
- integrate reusable release tooling (#8507)
- add release-tool consumer configuration
- integrate per-major release metadata
- integrate per-major release metadata
- integrate per-major release metadata

### Changed
- Merge commit from fork
- complete release artifact and publication contract (#8511)
- add production release orchestration (#8519)
- pin release orchestration to github-workflows v0.4.0 (#8523)
- update release workflows to github-workflows v0.5.0 (#8527)
- improve Prepare release workflow help (#8531)
- adopt managed nightly release workflow (#8536)
- keep nightly signing in LibreSign packaging (#8541)
- fix release automation app credentials (#8545)
- fix nightly release note line breaks (#8549)
- use organization release credentials and targeted fetch (#8553)
- pin release workflows to v0.6.5 (#8558)
- propagate 13.4.2 changelog history (#8560)
- fix 13.4.2 changelog spacing (#8564)
- fix 13.4.2 changelog spacing [skip ci] (#8570)
- pin release workflows to v0.6.8 (#8578)
- pin release workflows to v0.6.10 (#8593)
- pin release workflows to v0.6.12 (#8598)
- correct 13.4.2 release date (#8604)
- pin release workflows to v0.6.17 (#8621)
- use release workflows v0.6.18 (#8625)
- use release workflows v0.6.19 (#8629)
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep only stable34 changelog history
- keep root changelog as release history index
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- restore changelog history through app 14
- validate and verify per-major release changelog packaging
- bump nextcloud-behat to 1.6.1 for builtin 0.7.0
- complete release artifact and publication contract
- pin release workflows to v0.6.12
- pin release workflows to v0.6.5
- update Makefile SPDX years
- add production release orchestration (stable34)
- adopt managed nightly release workflow
- consume centrally pinned release-tool
- fix nightly release note line breaks
- fix release automation app credentials
- improve Prepare release workflow help
- keep nightly signing in LibreSign packaging
- pin release orchestration to v0.4.0
- preserve publication step identity
- remove legacy release drafter config
- remove legacy release drafter workflow
- simplify release metadata validation workflow
- sync final managed nightly workflow
- sync final managed nightly workflow
- test per-major release metadata integration
- update release consumer validator
- update release workflows to github-workflows v0.5.0
- use organization release credentials and targeted fetch
- validate consumer config with pinned release-tool
- validate release metadata mapping
- verify changelog mapping dynamically across stable lines
- correct 13.4.2 release date [skip ci]
- fix 13.4.2 changelog spacing
- initialize app major 16 changelog
- migrate changelog history for app major 10
- migrate changelog history for app major 11
- migrate changelog history for app major 12
- migrate changelog history for app major 13
- migrate changelog history for app major 14
- migrate changelog history for app major 15
- migrate changelog history for app major 2
- migrate changelog history for app major 4
- migrate changelog history for app major 5
- migrate changelog history for app major 6
- migrate changelog history for app major 7
- migrate changelog history for app major 8
- migrate changelog history for app major 9
- point root changelog to per-major history
- preserve complete changelog history for app major 10
- preserve complete changelog history for app major 11
- preserve complete changelog history for app major 12
- preserve complete changelog history for app major 13
- preserve complete changelog history for app major 14
- preserve complete changelog history for app major 15
- preserve complete changelog history for app major 2
- preserve complete changelog history for app major 4
- preserve complete changelog history for app major 5
- preserve complete changelog history for app major 6
- preserve complete changelog history for app major 7
- preserve complete changelog history for app major 8
- preserve complete changelog history for app major 9
- propagate 13.4.2 changelog history
- Translation updates.
- delegate release metadata validation to release-tool
- delegate release metadata validation to release-tool
- remove duplicated release metadata logic
- remove duplicated release metadata logic
- revert(behat): remove TransientConnectionRetry workaround
- style(behat): apply php-cs-fixer to FixtureHttpServer
- style: fix import order in file controller test
- add PDF_BASE64 placeholder for demo fixtures
- cover request-signature url via separate fixture server
- disable PHP built-in server workers
- retry ConnectException against PHP built-in server
- add deterministic release metadata validation
- add deterministic release metadata validation
- align file id validation with authorization boundary
- assert file access 404 response shape
- complete file id authorization contract
- complete file id authorization contract
- complete file id authorization contract
- complete file id authorization contract
- complete file id authorization contract
- complete file id authorization contract
- use legacy identify methods setup on stable branch

### Fixed
- stop nested develop/pdf fetches causing cURL 52 flakes (#8488)
- align file id authorization contract (#8516)
- complete #8430 backport for stable34
- complete envelope fixture backport for stable34
- keep two PHP built-in server workers
- restrict validation by internal file id
- keep file access 404 response contract
- preserve access for associated signers
- read release version portably in Makefile
- satisfy release metadata lint rules

## 14.2.1 - 2026-09-20

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations

### Fixed
- use the installed JSignPdf release version as the single source of truth [#8471](https://github.com/LibreSign/libresign/pull/8471)
- validate setup signatures with CA-signed certificates [#8473](https://github.com/LibreSign/libresign/pull/8473)

## 14.2.0 - 2026-09-19

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- support drag-and-drop PDF upload on the Validation page [#7934](https://github.com/LibreSign/libresign/pull/7934)
- add drag-and-drop document upload to the Files page [#7944](https://github.com/LibreSign/libresign/pull/7944)
- update JSignPdf to 3.1.0 with jsignpdf-php 3.0.0 [#8259](https://github.com/LibreSign/libresign/pull/8259)

### Changed
- update translations
- bump dependencies

### Fixed
- correct iOS Safari viewport height on sign requests [#7969](https://github.com/LibreSign/libresign/pull/7969)
- keep the signature-position step visible when the stamp still renders [#7998](https://github.com/LibreSign/libresign/pull/7998)
- improve identification-document LDAP join and approval status handling [#8022](https://github.com/LibreSign/libresign/pull/8022)
- promote certificate validity dates to the signer root [#8042](https://github.com/LibreSign/libresign/pull/8042)
- fix uploaded signature scaling [#8061](https://github.com/LibreSign/libresign/pull/8061)
- expose semantic PDF parser and validator error codes [#8189](https://github.com/LibreSign/libresign/pull/8189)
- improve external PDF validation [#8226](https://github.com/LibreSign/libresign/pull/8226) [#8251](https://github.com/LibreSign/libresign/pull/8251)
- trigger group search on the correct select event [#8244](https://github.com/LibreSign/libresign/pull/8244)
- improve TSA failure diagnostics [#8278](https://github.com/LibreSign/libresign/pull/8278)
- detect when signer search has more results [#8289](https://github.com/LibreSign/libresign/pull/8289)
- enforce envelope ownership when adding files [#8317](https://github.com/LibreSign/libresign/pull/8317)
- populate signer visible elements in validation responses [#8351](https://github.com/LibreSign/libresign/pull/8351)
- store identification documents of external signers under the file owner [#8371](https://github.com/LibreSign/libresign/pull/8371)
- stop overriding the pdf-elements worker [#8386](https://github.com/LibreSign/libresign/pull/8386)
- use the approver's own identity when signing an identification document [#8391](https://github.com/LibreSign/libresign/pull/8391)
- accept string node IDs from the Files sidebar when requesting a signature [#8396](https://github.com/LibreSign/libresign/pull/8396)
- stop deleting LibreSign appdata on every update [#8427](https://github.com/LibreSign/libresign/pull/8427)
- fix external page layout [#8436](https://github.com/LibreSign/libresign/pull/8436)

## 14.1.0 - 2026-07-15

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- validate PDF signatures without requiring Java [#7874](https://github.com/LibreSign/libresign/pull/7874)

### Changed
- update translations

### Fixed
- improve CRL generation performance and reliability [#7890](https://github.com/LibreSign/libresign/pull/7890)
- avoid stale autoload misses during upgrades [#7899](https://github.com/LibreSign/libresign/pull/7899)

## 14.0.2 - 2026-07-06

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations
- bump dependencies [#7780](https://github.com/LibreSign/libresign/pull/7780) [#7805](https://github.com/LibreSign/libresign/pull/7805) [#7818](https://github.com/LibreSign/libresign/pull/7818) [#7850](https://github.com/LibreSign/libresign/pull/7850)
- surface setup checks in the admin overview and `occ setupchecks:check` [#7841](https://github.com/LibreSign/libresign/pull/7841)

### Fixed
- avoid autoload issues from the authoritative classmap configuration [#7776](https://github.com/LibreSign/libresign/pull/7776)
- cache generated CRL data in appdata correctly [#7783](https://github.com/LibreSign/libresign/pull/7783)
- prevent invalid user element rows from breaking node ID writes [#7811](https://github.com/LibreSign/libresign/pull/7811)
- honor notification preferences more consistently for email notifications [#7835](https://github.com/LibreSign/libresign/pull/7835)
- avoid transient CFSSL startup failures during setup [#7838](https://github.com/LibreSign/libresign/pull/7838)
- clarify the error shown when a signing link is opened in the wrong authenticated session [#7857](https://github.com/LibreSign/libresign/pull/7857)

## 14.0.1 - 2026-06-14

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Fixed
- skip non-signature /Contents entries while preserving valid signature extraction [#7735](https://github.com/LibreSign/libresign/pull/7735)
- run playwright in official container [#7743](https://github.com/LibreSign/libresign/pull/7743)
- psalm fixes [#7746](https://github.com/LibreSign/libresign/pull/7746)
- match local CRL distribution points by path, not by request host [#7752](https://github.com/LibreSign/libresign/pull/7752)

## 14.0.0 - 2026-06-12

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations
- bump dependencies [#7636](https://github.com/LibreSign/libresign/pull/7636) [#7620](https://github.com/LibreSign/libresign/pull/7620) [#7618](https://github.com/LibreSign/libresign/pull/7618) [#7633](https://github.com/LibreSign/libresign/pull/7633)
- harden openapi contracts for sdk generators [#7662](https://github.com/LibreSign/libresign/pull/7662)
- use default DataResponse success constructor in id docs [#7661](https://github.com/LibreSign/libresign/pull/7661)

### Fixed
- align getArchitectures typing with main [#7680](https://github.com/LibreSign/libresign/pull/7680)
- keep groups_request_sign JSON unicode serialization consistent [#7675](https://github.com/LibreSign/libresign/pull/7675)
- align openapi signatureflow structure [#7668](https://github.com/LibreSign/libresign/pull/7668)
- align openapi file metadata contract with runtime payloads [#7656](https://github.com/LibreSign/libresign/pull/7656)
- expose ValidationURL and qrcode in signature stamp templates [#7650](https://github.com/LibreSign/libresign/pull/7650)
- render envelope validation data correctly for multi-file requests [#7647](https://github.com/LibreSign/libresign/pull/7647)
- remove stale addStyle icons call from TemplateLoader [#7641](https://github.com/LibreSign/libresign/pull/7641)
- support Twig date filter for ServerSignatureDate in JSign [#7644](https://github.com/LibreSign/libresign/pull/7644)
