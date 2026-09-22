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

## 14.2.2 - 2026-09-22

### Added
- integrate reusable release tooling
  [#8507](https://github.com/LibreSign/libresign/pull/8507)

### Changed
- Update translations
- Internal maintenance

### Fixed
- stop nested develop/pdf fetches causing cURL 52 flakes
  [#8488](https://github.com/LibreSign/libresign/pull/8488)
- align file id authorization contract
  [#8516](https://github.com/LibreSign/libresign/pull/8516)

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
