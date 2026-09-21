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

## 10.10.1 - 2025-09-13
### Changes
- Update translations
- Bump dependencies

### Fixes
- fix: typo [#5442](https://github.com/LibreSign/libresign/pull/5442)

## 10.10.0 - 2025-09-12
### Features
- feat: implement reminders to signers [#5433](https://github.com/LibreSign/libresign/pull/5433)

### Changes
- Update translations
- chore: add log to make possible debug issues at certificate chain [#5410](https://github.com/LibreSign/libresign/pull/5410)

### Fixes
- fix: isolate PHP-pdftk dependency [#5412](https://github.com/LibreSign/libresign/pull/5412)
- fix: prevent error when try to create a folder two times [#5420](https://github.com/LibreSign/libresign/pull/5420)
- fix: set TZ=UTC for pdfsig [#5428](https://github.com/LibreSign/libresign/pull/5428)
- fix: use utc as timezone when read data from signed document [#5431](https://github.com/LibreSign/libresign/pull/5431)

## 10.9.2 - 2025-09-02
### Changes
- Update translations
- Bump dependencies
- chore: update contributing [#5394](https://github.com/LibreSign/libresign/pull/5394)

### Fixes
- fix: timezone of preview signature stamp [#5386](https://github.com/LibreSign/libresign/pull/5386)
- fix: propagate timezone [#5392](https://github.com/LibreSign/libresign/pull/5392)
- fix: use UTC into all dates [#5397](https://github.com/LibreSign/libresign/pull/5397)
- fix: set default value to initial state [#5400](https://github.com/LibreSign/libresign/pull/5400)

## 10.9.1 - 2025-09-01
### Changes
- Update translations
- Bump dependencies
- chore: add log to track McFly [#5354](https://github.com/LibreSign/libresign/pull/5354)
- Docs/add GitHub codespaces steps to pr template [#5360](https://github.com/LibreSign/libresign/pull/5360)
- chore: convert the date object to json [#5366](https://github.com/LibreSign/libresign/pull/5366)
- chore: Improvements at response from API when generate root certificate [#5376](https://github.com/LibreSign/libresign/pull/5376)

### Fixes
- fix: remove condition that restricts LibreSign tab to LibreSign files [#5373](https://github.com/LibreSign/libresign/pull/5373)
- fix: open file at app files [#5371](https://github.com/LibreSign/libresign/pull/5371)

## 10.9.0 - 2025-08-21
### Features
- feat:button open file [#5239](https://github.com/LibreSign/libresign/pull/5239)

### Changes
- Update translations
- Bump dependencies
- chore: cover the sign method using pkcs7 engine [#5234](https://github.com/LibreSign/libresign/pull/5234)
- chore: Replace getById by getFirstNodeById [#5243](https://github.com/LibreSign/libresign/pull/5243)
- refactor: replace deprecated PHPUnit returnValue() with willReturn()  [#5246](https://github.com/LibreSign/libresign/pull/5246)
- chore: update behat [#5254](https://github.com/LibreSign/libresign/pull/5254)
- Replace deprecated PHPUnit methods [#5300](https://github.com/LibreSign/libresign/pull/5300)
- chore: remove unecessary check if node exists [#5338](https://github.com/LibreSign/libresign/pull/5338)

### Fixes
- fix: indent using taps [#5227](https://github.com/LibreSign/libresign/pull/5227)
- fix: remove duplicated step [#5232](https://github.com/LibreSign/libresign/pull/5232)
- fix: store the date that the file was signed [#5278](https://github.com/LibreSign/libresign/pull/5278)
- fix: email token with camel case email [#5309](https://github.com/LibreSign/libresign/pull/5309)
- fix: configure check with poppler [#5319](https://github.com/LibreSign/libresign/pull/5319)
- fix: always return the owner of the file to be signed [#5324](https://github.com/LibreSign/libresign/pull/5324)
- fix: prevent show add signers early [#5333](https://github.com/LibreSign/libresign/pull/5333)

## 10.8.5 - 2025-07-21
### Changes
- Update translations
- Bump dependencies
- chore: cover scenario of two accoutns with same email [#5210](https://github.com/LibreSign/libresign/pull/5210)

### Fixes
- fix: display draw signature in full mode [#5202](https://github.com/LibreSign/libresign/pull/5202)
- fix: Workflow does not contain permissions [#5191](https://github.com/LibreSign/libresign/pull/5191)
- fix: Workflow does not contain permissions [#5185](https://github.com/LibreSign/libresign/pull/5185)
- fix: validate file answer [#5180](https://github.com/LibreSign/libresign/pull/5180)
- fix: ignore vendor bin at transifex sync [#5174](https://github.com/LibreSign/libresign/pull/5174)
- fix: replace heredoc by string concat [#5173](https://github.com/LibreSign/libresign/pull/5173)

## 10.8.4 - 2025-06-16
### Changes
- Update translations
- Bump dependencies
- docs: add donation link to appear on Nextcloud appstore [#5152](https://github.com/LibreSign/libresign/pull/5152)
- chore: edit visible signatures [#5150](https://github.com/LibreSign/libresign/pull/5150)
- chore: start to move methods to Helper class [#5141](https://github.com/LibreSign/libresign/pull/5141)
- docs: add Star History image at README.md file [#5130](https://github.com/LibreSign/libresign/pull/5130)
- chore: remove unused var reported by Rector [#5107](https://github.com/LibreSign/libresign/pull/5107)
- chore: test set visible elements [#5102](https://github.com/LibreSign/libresign/pull/5102)
- chore: implement unit tests at visible elements class [#5095](https://github.com/LibreSign/libresign/pull/5095)
- chore: improve Release Drafter config with categories, changelog temp… [#5081](https://github.com/LibreSign/libresign/pull/5081)
- chore: Convert bug_report.md and feature_request.md to yaml [#5070](https://github.com/LibreSign/libresign/pull/5070)

### Fixes
- fix: link in README.md to feature_request.yml form [#5077](https://github.com/LibreSign/libresign/pull/5077)
- fix: change display name [#5154](https://github.com/LibreSign/libresign/pull/5154)
- fix: change lang environment when is possible [#5148](https://github.com/LibreSign/libresign/pull/5148)
- fix: implemented debounce on signer search field [#5133](https://github.com/LibreSign/libresign/pull/5133)

## 10.8.3 - 2025-05-28
### Changes
- chore: improve tip to sysadmin [#5054](https://github.com/LibreSign/libresign/pull/5054)

### Fixes
- fix: prevent error when check binaries of JSignPdf [#5065](https://github.com/LibreSign/libresign/pull/5065)
- fix: prevent warning when install first time [#5063](https://github.com/LibreSign/libresign/pull/5063)

## 10.8.2 - 2025-05-26
### Changes
- chore: add more ways to get signer email [#5047](https://github.com/LibreSign/libresign/pull/5047)

### Fixes
- fix: fallback when system haven't a TTF font [#5044](https://github.com/LibreSign/libresign/pull/5044)
- fix: show 'Dismiss notification' button on signed file notification [#5041](https://github.com/LibreSign/libresign/pull/5041)

## 10.8.1 - 2025-05-25
### Changes
- Update translations
- chore: remove unused property [#5028](https://github.com/LibreSign/libresign/pull/5028)
- chore: hide JSignPDF config check [#5024](https://github.com/LibreSign/libresign/pull/5024)
- chore: translators tips [#5019](https://github.com/LibreSign/libresign/pull/5019)
- chore: implement Rector [#5002](https://github.com/LibreSign/libresign/pull/5002)
- chore: move php tests to php folder [#5000](https://github.com/LibreSign/libresign/pull/5000)
- chore: rename method [#4997](https://github.com/LibreSign/libresign/pull/4997)

### Fixes
- fix: prevent destroy temp files [#5026](https://github.com/LibreSign/libresign/pull/5026)
- fix: prevent flakiness at scenario with time [#5018](https://github.com/LibreSign/libresign/pull/5018)
- fix: notification and activity parameters [#5013](https://github.com/LibreSign/libresign/pull/5013)
- fix: throw error when identify by email is disabled [#4993](https://github.com/LibreSign/libresign/pull/4993)
- fix: display error message instead of json [#4991](https://github.com/LibreSign/libresign/pull/4991)

## 10.8.0 - 2025-05-20
### Features
- feat: adding new activity configuration [#4984](https://github.com/LibreSign/libresign/pull/4984)
- feat: manage certificate policy [#4969](https://github.com/LibreSign/libresign/pull/4969)
- feat: add administration settings to settings menu [#4967](https://github.com/LibreSign/libresign/pull/4967)

### Changes
- Update translations
- Bump dependencies
- chore: move strings to constants [#4977](https://github.com/LibreSign/libresign/pull/4977)
- chore: valdiate password before send to sign [#4965](https://github.com/LibreSign/libresign/pull/4965)
- chore: text improvement [#4960](https://github.com/LibreSign/libresign/pull/4960)
- chore: change save signed file logic [#4945](https://github.com/LibreSign/libresign/pull/4945)
- chore: improve error message [#4942](https://github.com/LibreSign/libresign/pull/4942)
- chore: improve feedback of configure check [#4915](https://github.com/LibreSign/libresign/pull/4915)
- chore: reduce configure check time [#4878](https://github.com/LibreSign/libresign/pull/4878)
- chore: remove unecessary else [#4870](https://github.com/LibreSign/libresign/pull/4870)

### Fixes
- fix: allow to sign without account [#4982](https://github.com/LibreSign/libresign/pull/4982)
- fix: prevent merge when haven't a signature [#4974](https://github.com/LibreSign/libresign/pull/4974)
- fix: css at validation page [#4953](https://github.com/LibreSign/libresign/pull/4953)
- fix: send fileSrc to PDF Editor [#4950](https://github.com/LibreSign/libresign/pull/4950)
- fix: test identify method [#4930](https://github.com/LibreSign/libresign/pull/4930)
- fix: order signers by id [#4928](https://github.com/LibreSign/libresign/pull/4928)
- fix: main license file [#4868](https://github.com/LibreSign/libresign/pull/4868)

## 10.7.2 - 2025-04-14
### Changes
- Update translations

### Fixes
- fix: user userId when validate file [#4856](https://github.com/LibreSign/libresign/pull/4856)
- fix: only notify when is not draft [#4854](https://github.com/LibreSign/libresign/pull/4854)
- fix: prevent json decode null [#4851](https://github.com/LibreSign/libresign/pull/4851)

## 10.7.1 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4840](https://github.com/LibreSign/libresign/pull/4840)
- fix typo: descriptin -> description [#4836](https://github.com/LibreSign/libresign/pull/4836)

## 10.7.0 - 2025-04-11
### Features
- feat: customize signature stamp [#4826](https://github.com/LibreSign/libresign/pull/4826)
- feat: add group activity [#4792](https://github.com/LibreSign/libresign/pull/4792)

### Changes
- Update translations
- Bump dependencies
- chore: replace deprecated properties [#4807](https://github.com/LibreSign/libresign/pull/4807)
- chore: cover jsignparam with tests [#4789](https://github.com/LibreSign/libresign/pull/4789)
- refactor: adding types to entities [#4775](https://github.com/LibreSign/libresign/pull/4775)

### Fixes
- fix: time stamp when then document signed [#4822](https://github.com/LibreSign/libresign/pull/4822)
- fix: optimize file loading [#4821](https://github.com/LibreSign/libresign/pull/4821)
- fix: check if preview is available [#4817](https://github.com/LibreSign/libresign/pull/4817)
- fix: prevent error when output of pdfsig is empty [#4815](https://github.com/LibreSign/libresign/pull/4815)
- fix: prevent error when collect metadata [#4786](https://github.com/LibreSign/libresign/pull/4786)
- refactor: fix var typo [#4778](https://github.com/LibreSign/libresign/pull/4778)
- fix: psalm issue [#4760](https://github.com/LibreSign/libresign/pull/4760)

## 10.6.4 - 2025-03-20
### Changes
- Update translations
- Bump dependencies

## 10.6.3 - 2025-02-28
### Changes
- Update translations
- Bump dependencies
- chore: update openapi [#4695](https://github.com/LibreSign/libresign/pull/4695)

### Fixes
- fix: restrict access to validation endpoints [#4701](https://github.com/LibreSign/libresign/pull/4701)
- fix: add information note about visible signature [#4687](https://github.com/LibreSign/libresign/pull/4687)

## 10.6.2 - 2025-02-21
### Changes
- Update translations
- Bump dependencies
- chore: replace deprecated function [#4647](https://github.com/LibreSign/libresign/pull/4647)
- chore: remove wrong annotation [#4603](https://github.com/LibreSign/libresign/pull/4603)
- chore: check if user exists [#4600](https://github.com/LibreSign/libresign/pull/4600)
- chore: prevent create cfssl config path every time [#4586](https://github.com/LibreSign/libresign/pull/4586)
- chore: tests improvement [#4584](https://github.com/LibreSign/libresign/pull/4584)
- chore: small tests improvement [#4579](https://github.com/LibreSign/libresign/pull/4579)
- refactor: moved hashes to be close to version number [#4575](https://github.com/LibreSign/libresign/pull/4575)
- refactor: moved version of JSignPdf to InstallService [#4568](https://github.com/LibreSign/libresign/pull/4568)

### Fixes
- fix: add function that only exists at nextcloud 32 [#4653](https://github.com/LibreSign/libresign/pull/4653)
- fix: prevent success when signature file dont exists [#4641](https://github.com/LibreSign/libresign/pull/4641)
- fix: ltr language [#4630](https://github.com/LibreSign/libresign/pull/4630)
- fix: use entities instead of char convertoing [#4628](https://github.com/LibreSign/libresign/pull/4628)
- fix: hide request button to anauthorized account [#4622](https://github.com/LibreSign/libresign/pull/4622)
- fix: add maxlength to names of cert [#4607](https://github.com/LibreSign/libresign/pull/4607)
- fix: prevent call other autoload before libresign [#4596](https://github.com/LibreSign/libresign/pull/4596)
- fix: typo [#4590](https://github.com/LibreSign/libresign/pull/4590)
- fix: prevent generate a path without existing folder [#4564](https://github.com/LibreSign/libresign/pull/4564)
- fix: prevent warning when haven't names [#4561](https://github.com/LibreSign/libresign/pull/4561)
- fix: prevent warning of fsockopen [#4553](https://github.com/LibreSign/libresign/pull/4553)
- fix: prevent delete binary files when execute unit tests [#4543](https://github.com/LibreSign/libresign/pull/4543)

## 10.6.1 - 2025-01-28
### Changes
- Update translations
- Bump dependencies
- chore: bump java [#4532](https://github.com/LibreSign/libresign/pull/4532)
- refactor: convert to promisse [#4524](https://github.com/LibreSign/libresign/pull/4524)
- refactor: force typing [#4505](https://github.com/LibreSign/libresign/pull/4505)

### Fixes
- fix: prevent error when enpty data from backend [#4527](https://github.com/LibreSign/libresign/pull/4527)
- fix: use async await [#4523](https://github.com/LibreSign/libresign/pull/4523)
- fix: retrieve saved data [#4534](https://github.com/LibreSign/libresign/pull/4534)
- fix: display errors at error page [#4513](https://github.com/LibreSign/libresign/pull/4513)
- fix: display error at same route [#4509](https://github.com/LibreSign/libresign/pull/4509)
- fix: hide sidebar when is not necessary [#4502](https://github.com/LibreSign/libresign/pull/4502)
- fix: redirect to login when validation page is not public [#4498](https://github.com/LibreSign/libresign/pull/4498)
- fix: logout if is using different account [#4495](https://github.com/LibreSign/libresign/pull/4495)
- fix: prevent error when validate signed file using cfssl cert [#4477](https://github.com/LibreSign/libresign/pull/4477)
- fix: remove licence file [#4473](https://github.com/LibreSign/libresign/pull/4473)

## 10.6.0 - 2025-01-27
### Features
- feat: add extracerts to generated cert [#4427](https://github.com/LibreSign/libresign/pull/4427)
- feat: parse extracerts content [#4415](https://github.com/LibreSign/libresign/pull/4415)

### Changes
- Update translations
- Bump dependencies
- chore: use fallback to get page dimension [#4462](https://github.com/LibreSign/libresign/pull/4462)
- chore: remove vuex [#4429](https://github.com/LibreSign/libresign/pull/4429)
- chore(i18n): Fixed grammar [#4411](https://github.com/LibreSign/libresign/pull/4411)
- chore: remove unused property [#4407](https://github.com/LibreSign/libresign/pull/4407)
- chore: validate signer of signed pdf file [#4404](https://github.com/LibreSign/libresign/pull/4404)
- chore: ignore warning of Nextcloud [#4403](https://github.com/LibreSign/libresign/pull/4403)
- chore: only display div of chains if chain exists [#4400](https://github.com/LibreSign/libresign/pull/4400)

### Fixes
- fix: save as 0 or 1 [#4451](https://github.com/LibreSign/libresign/pull/4451)
- fix: consider different values of settings [#4448](https://github.com/LibreSign/libresign/pull/4448)
- fix: path of url [#4438](https://github.com/LibreSign/libresign/pull/4438)

## 10.5.3 - 2025-01-19
### Changes
- chore: bump dependencies
- chore: prevent generate unecessary temp file [#4393](https://github.com/LibreSign/libresign/pull/4393)

### fixes
- fix: view pdf at validation page [#4391](https://github.com/LibreSign/libresign/pull/4391)
- fix: list files with deleted signer [#4385](https://github.com/LibreSign/libresign/pull/4385)

## 10.5.2 - 2025-01-16
### fixes
- fix: handle settings after backend upgrade [#4372](https://github.com/LibreSign/libresign/pull/4372)
- fix: prevent error when is empty files [#4369](https://github.com/LibreSign/libresign/pull/4369)
- fix: validation url [#4364](https://github.com/LibreSign/libresign/pull/4364)
- chore: add more details to pdf viewer [#4362](https://github.com/LibreSign/libresign/pull/4362)
- fix: prevent error when get timeout from api [#4359](https://github.com/LibreSign/libresign/pull/4359)
- fix: close dialog after submit [#4356](https://github.com/LibreSign/libresign/pull/4356)

## 10.5.1 - 2025-01-16
### fixes
- fix: submit on click [#4343](https://github.com/LibreSign/libresign/pull/4343)
- fix: prevent error when have not identify method [#4339](https://github.com/LibreSign/libresign/pull/4339)
- fix: validate with success when signer account was deleted [#4334](https://github.com/LibreSign/libresign/pull/4334)
- fix: When only have a signature, consider that who signed is who need… [#4317](https://github.com/LibreSign/libresign/pull/4317)
- fix: show that file not found when validate file [#4316](https://github.com/LibreSign/libresign/pull/4316)
- fix: match signature from file with libresign [#4309](https://github.com/LibreSign/libresign/pull/4309)
- fix: match signers from cert with signers from LibreSign [#4305](https://github.com/LibreSign/libresign/pull/4305)
- fix: load success icon when cert is valid [#4304](https://github.com/LibreSign/libresign/pull/4304)

## 10.5.0 - 2025-01-13
### Changes
- Update translations
- Bump dependencies
- feat: validate from uploaded file [#4253](https://github.com/LibreSign/libresign/pull/4253)
- feat: validate pdf [#4234](https://github.com/LibreSign/libresign/pull/4234)
- feat: change expirity [#4232](https://github.com/LibreSign/libresign/pull/4232)
- feat: rewrite validation page [#4204](https://github.com/LibreSign/libresign/pull/4204)
- feat: add rate LibreSign [#4203](https://github.com/LibreSign/libresign/pull/4203)
- feat: allow to change signature hash algorithm [#4190](https://github.com/LibreSign/libresign/pull/4190)
- chore: display signature issue when haven't proppler [#4297](https://github.com/LibreSign/libresign/pull/4297)
- chore: make possible press enter to submit some forms [#4237](https://github.com/LibreSign/libresign/pull/4237)

### Fixes
- fix: prevent error when add new signer [#4295](https://github.com/LibreSign/libresign/pull/4295)
- fix: prevent js error [#4290](https://github.com/LibreSign/libresign/pull/4290)
- fix: notify by email when is not authenticated [#4280](https://github.com/LibreSign/libresign/pull/4280)
- fix: method name [#4278](https://github.com/LibreSign/libresign/pull/4278)
- fix: center component [#4274](https://github.com/LibreSign/libresign/pull/4274)
- fix: method name [#4271](https://github.com/LibreSign/libresign/pull/4271)
- fix: path of renew url [#4269](https://github.com/LibreSign/libresign/pull/4269)
- fix: ignore order of array [#4257](https://github.com/LibreSign/libresign/pull/4257)
- fix: load cert custom options [#4245](https://github.com/LibreSign/libresign/pull/4245)
- fix: display cfssl settings [#4229](https://github.com/LibreSign/libresign/pull/4229)
- fix: display certificate data after regenerate certificate [#4197](https://github.com/LibreSign/libresign/pull/4197)
- fix: fetch signature methods [#4188](https://github.com/LibreSign/libresign/pull/4188)
- fix: remove extension from filename [#4187](https://github.com/LibreSign/libresign/pull/4187)

## 10.4.4 - 2024-12-13
### Fixes
* fix: load signature methods [4144](https://github.com/LibreSign/libresign/pull/4144)
* fix: footer in pages with different sizes [4142](https://github.com/LibreSign/libresign/pull/4142)
* fix: change error class [4136](https://github.com/LibreSign/libresign/pull/4136)
* fix: consider filter status to toggle components [4102](https://github.com/LibreSign/libresign/pull/4102)
* fix: block access to route when isn't allowed by admin [4096](https://github.com/LibreSign/libresign/pull/4096)

### Changes
* Update translations
* Bump dependencies
* chore: clean code [4088](https://github.com/LibreSign/libresign/pull/4088)

## 10.4.3 - 2024-11-30
### Fixes
* fix: prevent error when using PostgreSQL [4082](https://github.com/LibreSign/libresign/pull/4082)

### Changes
* Update translations

## 10.4.2 - 2024-11-29
### Fixes
* fix: list files from PostgreSQL [4076](https://github.com/LibreSign/libresign/pull/4076)

### Changes
* Update translations

## 10.4.1 - 2024-11-26
### Fixes
* fix: Icon color att app files [4058](https://github.com/LibreSign/libresign/pull/4058)
* fix: prevent error when click at signer to add to document [4056](https://github.com/LibreSign/libresign/pull/4056)
* fix: toggle loading [4054](https://github.com/LibreSign/libresign/pull/4054)
* fix: prevent don't delete file when folder is deleted [4061](https://github.com/LibreSign/libresign/pull/4061)

## 10.4.0 - 2024-11-25
### Fixes
* fix: JS error when upload file [4035](https://github.com/LibreSign/libresign/pull/4035)
* fix: show message when file list is empty [4033](https://github.com/LibreSign/libresign/pull/4033)

### Changes
* Update translations
* feat: delete multiple files [4028](https://github.com/LibreSign/libresign/pull/4028)

### Chore
* chore: bump dependencies [4045](https://github.com/LibreSign/libresign/pull/4045)
* chore: show loading before finish load file list [4043](https://github.com/LibreSign/libresign/pull/4043)
* chore: disable Actions menu when click in an action [4039](https://github.com/LibreSign/libresign/pull/4039)
* chore: unify code into a new component [4037](https://github.com/LibreSign/libresign/pull/4037)

## 10.3.1 - 2024-11-23
### Fixes
* fix: assure that all signers will have an unique id [4018](https://github.com/LibreSign/libresign/pull/4018)
* fix: show actions at signer list [4015](https://github.com/LibreSign/libresign/pull/4015)

### Changes
* feat: add footer to file list [4021](https://github.com/LibreSign/libresign/pull/4021)
* feat: only show name and allow save signer when have signer [4008](https://github.com/LibreSign/libresign/pull/4008)
* Update translations

### Chore
* chore(deps): Bump @nextcloud/vue from 8.20.0 to 8.21.0 [4012](https://github.com/LibreSign/libresign/pull/4012)

## 10.3.0 - 2024-11-20
### Fixes
* fix: retrieve file when request to sign from file list [3998](https://github.com/LibreSign/libresign/pull/3998)

### Changes
* feat: make possible choose the page [3984](https://github.com/LibreSign/libresign/pull/3984)
* Update translations

### Chore
* chore: bump dependencies at PHP and JS side
* chore: refresh file list every when load view [4001](https://github.com/LibreSign/libresign/pull/4001)

## 10.2.0 - 2024-11-15
### Fixes
* fix: only show return when come from validation button [3970](https://github.com/LibreSign/libresign/pull/3970)
* fix: go ahead if the file is not found [3972](https://github.com/LibreSign/libresign/pull/3972)
* fix: filter files by signer uuid [3963](https://github.com/LibreSign/libresign/pull/3963)
* fix: toggle sidebar [3959](https://github.com/LibreSign/libresign/pull/3959)
* fix: Use unicode signer name [3930](https://github.com/LibreSign/libresign/pull/3930)
* fix: add back the contition to write_qrcode_on_footer [3932](https://github.com/LibreSign/libresign/pull/3932)

### Changes
* feat: request to sign from files [3947](https://github.com/LibreSign/libresign/pull/3947)
* feat: write success after end of configure [3934](https://github.com/LibreSign/libresign/pull/3934)
* Update translations

### Chore
* Bump dependencies
* chore: feedback improvement [3975](https://github.com/LibreSign/libresign/pull/3975)
* chore: disable buttons when is processing the action [3966](https://github.com/LibreSign/libresign/pull/3966)
* chore: replace :value.sync by v-model [3954](https://github.com/LibreSign/libresign/pull/3954)
* chore: improve cfssl validation [3942](https://github.com/LibreSign/libresign/pull/3942)
* chore: Optimize svg image [3939](https://github.com/LibreSign/libresign/pull/3939)

## 10.1.0 - 2024-11-07
### Fixes
* fix: open notification as internal url [3713](https://github.com/LibreSign/libresign/pull/3713)
* fix: close button [3724](https://github.com/LibreSign/libresign/pull/3724)
* fix: typo [3735](https://github.com/LibreSign/libresign/pull/3735)
* fix: i18n; Fixed grammar [3785](https://github.com/LibreSign/libresign/pull/3785)
* fix: prevent don't match extension when the file have uppercase name [3819](https://github.com/LibreSign/libresign/pull/3819)
* fix: open settings together with cms_pico [3824](https://github.com/LibreSign/libresign/pull/3824)
* fix: replace deprecated code [3899](https://github.com/LibreSign/libresign/pull/3899)

### Changes
* feat: use Viewer to open pdf [3727](https://github.com/LibreSign/libresign/pull/3727)
* feat: add spdx headers https://github.com/LibreSign/libresign/pull/3877
* feat: rewrite file list [3898](https://github.com/LibreSign/libresign/pull/3898)
* feat: add OWASP dependency check [3914](https://github.com/LibreSign/libresign/pull/3914)

### Chore
* chore: remove unused packages and code
* chore: convert indent size and apply updated linter rules
* chore: update api documentation [3903](https://github.com/LibreSign/libresign/pull/3903)

## 10.0.2 - 2024-09-14
### Fixes
* fix: ajust condition to filter file list[3702](https://github.com/LibreSign/libresign/pull/3702)
* fix: prevent warning when check if array has key[3692](https://github.com/LibreSign/libresign/pull/3692)
* fix: prevent duplicate text[3688](https://github.com/LibreSign/libresign/pull/3688)
* fix: notification parameters need to be string[3683](https://github.com/LibreSign/libresign/pull/3683)

### Changes
* chore: adjust filter condition[3705](https://github.com/LibreSign/libresign/pull/3705)
* chore: validation setup improvement[3697](https://github.com/LibreSign/libresign/pull/3697)
* bump dependencies
* Update translations

## 10.0.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3655](https://github.com/LibreSign/libresign/pull/3655)

## 10.0.0 - 2024-09-10
* Say hello to Nextcloud 30 🎉
