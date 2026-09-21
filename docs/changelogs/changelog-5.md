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

## 5.2.0 - 2022-10-23
### Changed
* Easy setup without necessity to run commands in server
* Updated translations
* Bump JSignPdf
* Prevent delete signed file when original file was deleted

## 5.1.4 - 2022-08-04
### Fixed
- Update file from master
  [#879](https://github.com/LibreSign/libresign/pull/879)

## 5.1.3 - 2022-08-04
### Fixed
- General adjusts and updates
  [#870](https://github.com/LibreSign/libresign/pull/870)
  - Update dependencies
  - Remove yarn
  - Fix eslint errors and warnings
  - Improve build
  - Fix invalid redirects

## 5.1.2 - 2022-07-30

### Fixed
- Use escapeshellarg to fix path of file
- bump PHP dependencies

## 5.1.1 - 2022-05-05

### Fixed
- Replaced more usages of TCPDI by LibreSignCLI

## 5.1.0 - 2022-04-26

### Added
- Command to install LibreSign cli

## 5.0.0 - 2022-04-25

### Added and updated
- Support to visual signatures
  - upload signature image
  - handmade signature
  - text signature
- Sign usign SMS, email, Telegram or Signal token
- Add files to profile to only enable signature if profile files was signed by an approver
- Simplified setup using commands
- Update JSignPDF version
- more other changes and bugfixes: https://github.com/LibreSign/libresign/compare/v2.4.5...v5.0.0
