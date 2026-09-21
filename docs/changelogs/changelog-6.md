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

## 6.2.9 - 2023-03-21
### Changed
* Bump dependencies
* Collect metadata of signers
* Log CLI exceptions
* Limit execution of backend tests

### Fixed
* Fix missing signed htaccess
* Make possible to approvers can sign identification documents

## 6.2.8 - 2023-02-22
### Fixed
* Fix app files signing

## 6.2.7 - 2023-02-21
### Changed
* Bump dependencies

### Fixed
* Fix binaries download
* Fix grammar

## 6.2.6 - 2023-02-14
### Changed
* Frontend improvements to generate root cert
* Bump dependencies

### Fixed
* Fix composer autoload bug
* Show progress bar when havent memcache
* Minor bugfixes and translation fix

## 6.2.5 - 2023-01-23
### Fixed
* Fix hide previous when haven't previous
* Bump dependencies

## 6.2.4 - 2023-01-14
### Changed
* Add message "Nothing to do" in tab of app Files when have nothing to do. #1356

### Fixed

* Handle error when update dependencies #1329
* Fix command name #1335
* Change icon color by theme #1354
* Read metadata of shared file #1352

## 6.2.3 - 2022-12-31

* **Happy new year!**
* Bump dependencies
* TCPDF updates:
  * Bumped version of TCPDF that could solve problem when add qrcode in specific cases. #1299
  * Added backtrace to admins identify when TCPDF throw an error when sign a file
* Improvements to verify dependency versions. Now will show error when is incompatible version of CFSSL and JSignPdf. Thanks to @tasagore

## 6.2.2 - 2022-12-17
* Fix temp directory separator, thanks to @cabaseira

## 6.2.1 - 2022-12-17
* Fix temp dir, thanks to @unnilennium
* Check if have ghostscript
* Bump dependencies

## 6.2.0 - 2022-12-04
### Changed
* Change the default java version
* Make compatible with arm
* Toogle enable identification documents flow
* Hide features if havent certificate
* Open tab in folder

### Fixed
* Fix command to configure root cert
* Fix newcert request when names array is empty
* Fix overflow
* Fix rule to display button
* Fix generate validate url

**Full Changelog**: https://github.com/LibreSign/libresign/compare/v6.1.2...v6.2.0

## 6.1.1 - 2022-10-29
* Fix wrong migration

## 6.1.0 - 2022-10-26
* Make possible generate root cert with custom values
* display with line break and prevent to use java when not available

## 6.0.2 - 2022-10-23
* Fix css class of password change modal

## 6.0.0 - 2022-10-23
### Changed
* Big refactor to upgrade frontend components
* Easy setup without necessity to run commands in server
* Updated translations
* Bump JSignPdf
* Prevent delete signed file when original file was deleted
