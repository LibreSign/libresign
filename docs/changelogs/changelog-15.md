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

## 15.0.4 - 2026-09-22

### Changed
- Update translations
- Internal maintenance

### Fixed
- stop nested develop/pdf fetches causing cURL 52 flakes
  [#8487](https://github.com/LibreSign/libresign/pull/8487)
- align file id authorization contract
  [#8515](https://github.com/LibreSign/libresign/pull/8515)
- prefer pull requests in nightly release notes
  [#8567](https://github.com/LibreSign/libresign/pull/8567)

## 15.0.3 - 2026-09-20

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Changed
- update translations

### Fixed
- use the installed JSignPdf release version as the single source of truth [#8470](https://github.com/LibreSign/libresign/pull/8470)

## 15.0.2 - 2026-09-19

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Fixed
- fix identification documents migration for typed app config values [#8457](https://github.com/LibreSign/libresign/pull/8457)

## 15.0.1 - 2026-09-19

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Fixed
- fix binary integrity validation for CA-signed setup certificates [#8449](https://github.com/LibreSign/libresign/pull/8449)

## 15.0.0 - 2026-09-19

💝 **SUPPORT LIBRESIGN** — If you find this project useful, please consider supporting its development: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need help with migration or custom implementations? Contact us: contact@librecode.coop

### Added
- add signer geolocation support [#8221](https://github.com/LibreSign/libresign/pull/8221) [#8359](https://github.com/LibreSign/libresign/pull/8359)
- add `mail_sender_strategy` policy to send notifications as the requester [#8206](https://github.com/LibreSign/libresign/pull/8206)
- add backend support for signature rejection [#8319](https://github.com/LibreSign/libresign/pull/8319)
- allow administrators to choose the TSA timestamp-query hash algorithm [#8283](https://github.com/LibreSign/libresign/pull/8283)
- update JSignPdf to 3.1.0 with jsignpdf-php 3.0.0 [#8260](https://github.com/LibreSign/libresign/pull/8260)

### Changed
- update translations
- bump dependencies

### Fixed
- improve external PDF signature validation [#8227](https://github.com/LibreSign/libresign/pull/8227) [#8252](https://github.com/LibreSign/libresign/pull/8252)
- improve TSA failure diagnostics [#8279](https://github.com/LibreSign/libresign/pull/8279)
- detect when signer search has more results [#8290](https://github.com/LibreSign/libresign/pull/8290)
- enforce envelope ownership when adding files [#8318](https://github.com/LibreSign/libresign/pull/8318)
- populate signer visible elements in validation responses [#8352](https://github.com/LibreSign/libresign/pull/8352)
- store identification documents of external signers under the file owner [#8372](https://github.com/LibreSign/libresign/pull/8372)
- stop overriding the pdf-elements worker [#8387](https://github.com/LibreSign/libresign/pull/8387)
- use the approver's own identity when signing an identification document [#8392](https://github.com/LibreSign/libresign/pull/8392)
- accept string node IDs from the Files sidebar when requesting a signature [#8395](https://github.com/LibreSign/libresign/pull/8395)
- stop deleting LibreSign appdata on every update [#8428](https://github.com/LibreSign/libresign/pull/8428)
- fix external page layout [#8437](https://github.com/LibreSign/libresign/pull/8437)
