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

## 8.3.2 - 2024-09-14
### Fixes
* fix: ajust condition to filter file list [3700](https://github.com/LibreSign/libresign/pull/3700)
* fix: prevent warning when check if array has key [3690](https://github.com/LibreSign/libresign/pull/3690)
* fix: prevent duplicate text [3687](https://github.com/LibreSign/libresign/pull/3687)
* fix: notification parameters need to be string [3681](https://github.com/LibreSign/libresign/pull/3681)

### Changes
* chore: adjust filter condition [3703](https://github.com/LibreSign/libresign/pull/3703)
* chore: validation setup improvement [3695](https://github.com/LibreSign/libresign/pull/3695)
* bump dependencies
* Update translations

## 8.3.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3653](https://github.com/LibreSign/libresign/pull/3653)

## 8.3.0 - 2024-09-10
### New feature
* Support to GitHub codespace and devcontainers
* Add filter by status to listing at API side [3604](https://github.com/LibreSign/libresign/pull/3604)

### Changes
* chore: Test signature proccess [3580](https://github.com/LibreSign/libresign/pull/3580)
* chore: add unit tests [3503](https://github.com/LibreSign/libresign/pull/3503)

### Fixes
* fix: prevent error when resync sequence of other apps [3607](https://github.com/LibreSign/libresign/pull/3607)
* fix: internal route [3625](https://github.com/LibreSign/libresign/pull/3625)
* fix: js linter warning [3578](https://github.com/LibreSign/libresign/pull/3578)
* fix: draw width [3545](https://github.com/LibreSign/libresign/pull/3545)
* fix: handle error when is invalid password [3483](https://github.com/LibreSign/libresign/pull/3483)
* fix: prevent js error when disabled for user [3486](https://github.com/LibreSign/libresign/pull/3486)
* fix: git safe directory [3450](https://github.com/LibreSign/libresign/pull/3450)

## 8.2.4 - 2024-07-24
### New feature
* feat: implement support to devcontainer [3397](https://github.com/LibreSign/libresign/pull/3397)
* feat: implement endpoint to disable hate limit [3393](https://github.com/LibreSign/libresign/pull/3393)

### Changes
* chore: add tsconfig by @Any97Cris [3444](https://github.com/LibreSign/libresign/pull/3444)
* chore: remove unecessary string [3420](https://github.com/LibreSign/libresign/pull/3420)
* chore: remove unecessary var [3413](https://github.com/LibreSign/libresign/pull/3413)
* chore: prevent error when try to delete user that haven't uid [3391](https://github.com/LibreSign/libresign/pull/3391)
* chore: replace way to identify Alpine Linux [3389](https://github.com/LibreSign/libresign/pull/3389)

### Fixes
* fix: imporve validation [3437](https://github.com/LibreSign/libresign/pull/3437)
* fix: cfsslUri is optional value [3439](https://github.com/LibreSign/libresign/pull/3439)
* fix: run test in separated proccess [3441](https://github.com/LibreSign/libresign/pull/3441)
* fix: name of button after generate OpenSSL certificate [3429](https://github.com/LibreSign/libresign/pull/3429)
* fix: prevent error when use relative path [3417](https://github.com/LibreSign/libresign/pull/3417)
* fix: set linux distro before validate downloaded files [3415](https://github.com/LibreSign/libresign/pull/3415)
* fix: check if certificate was generated [3407](https://github.com/LibreSign/libresign/pull/3407)
* fix: resynchronize database sequences [3401](https://github.com/LibreSign/libresign/pull/3401)
* fix: use linux distro when build [3366](https://github.com/LibreSign/libresign/pull/3366)
* fix: Java setup [3361](https://github.com/LibreSign/libresign/pull/3361)
* fix: setup at alpine [3353](https://github.com/LibreSign/libresign/pull/3353)

## 8.2.3 - 2024-07-12
### Fixes
* fix: use linux distro when build [3366]https://github.com/LibreSign/libresign/pull/3366

## 8.2.2 - 2024-07-12
### Fixes
* fix: Java setup [3361]https://github.com/LibreSign/libresign/pull/3361

## 8.2.1 - 2024-07-11
### Fixes
* fix: setup at alpine [#3354](https://github.com/LibreSign/libresign/pull/3354)

## 8.2.0 - 2024-07-11
### Changed
* apply cs fixer [#3335](https://github.com/LibreSign/libresign/pull/3335)
* bump cs fixer [#3327](https://github.com/LibreSign/libresign/pull/3327)
* ui improvements [#3330](https://github.com/LibreSign/libresign/pull/3330)
* js optimizations [#3322](https://github.com/LibreSign/libresign/pull/3322)
* reduce a query when delete file [#3319](https://github.com/LibreSign/libresign/pull/3319)
* bump dependencies [#3308](https://github.com/LibreSign/libresign/pull/3308)
* use engine name at tip [#3305](https://github.com/LibreSign/libresign/pull/3305)

### Fixes
* install and check process [#3341](https://github.com/LibreSign/libresign/pull/3341)
* prevent error when try to create folder and alreay exists [#3339](https://github.com/LibreSign/libresign/pull/3339)
* prevent error when access method of Nextcloud 29 [#3317](https://github.com/LibreSign/libresign/pull/3317)
* Prevent error when haven't ps command [#3315](https://github.com/LibreSign/libresign/pull/3315)

## 8.1.3 - 2024-07-08
### Changed
* chore: update workflows [3255](https://github.com/LibreSign/libresign/pull/3255)
* chore: bump dependencies of integration tests [3267](https://github.com/LibreSign/libresign/pull/3267)

### Fixes
* fix: pack openapi json file [3247](https://github.com/LibreSign/libresign/pull/3247)
* fix: use equal to option [3257](https://github.com/LibreSign/libresign/pull/3257)
* fix: sign setup when build [3262](https://github.com/LibreSign/libresign/pull/3262)
* fix: pagination [3270](https://github.com/LibreSign/libresign/pull/3270)

## 8.1.2 - 2024-06-28
### Fixes
* fix: Internal error when signing [#3238](github.com/libresign/libresign/pull/3238)

## 8.1.1 - 2024-06-26
### Changed
* Disable sign button when is loading [#3224](https://github.com/libresign/libresign/pull/3224)
* Bump dependencies

### Fixes
* signing dependencies at deploy to Nextcloud app store [#3233](https://github.com/libresign/libresign/pull/3233)
* Make possible use multiple signatures of same signer [#3228](https://github.com/libresign/libresign/pull/3228)
* neutralize deleted users [#3221](https://github.com/libresign/libresign/pull/3221)

## 8.1.0 - 2024-06-24
### New feature
* Clean old setup binaries
* API documentation generated by OpenAPI moved to Nextcloud pattern
* Hide sidebar when is incomplete setup
### Changed
* Update translations
* Bump packages
* Clean code
### Fixes
* Prevent error when synchonize with windows
* Prevent error when delete visible signature

## 8.0.2 - 2024-05-10
### New feature
* feat: finish setup in https://github.com/LibreSign/libresign/pull/3039

### Changed
* Updated translations

### Fixed
* fix: check if is alpine by @backportbot-libresign in https://github.com/LibreSign/libresign/pull/3049

## 8.0.1 - 2024-05-10
### Changed
* Update translations
* Make possible customize the document footer using HTML [#2970](https://github.com/LibreSign/libresign/pull/2970)
* Update dependencies at front and backend

### Fixed
* Fix position of components when preview document before sign

## 8.0.0 - 2024-04-24
### ✨Big changes to a new moment
* 📝 Allow you to sign documents without creating an account
* 🔒 Create root certificate with OpenSSL
* 📜 Possibility to send and sign with your own certificate
* 🛠️ Simplified setup
