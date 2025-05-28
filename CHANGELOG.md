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
## 11.2.3 - 2025-05-28
### Changes
- chore: improve tip to sysadmin [#5055](https://github.com/LibreSign/libresign/pull/5055)

### Fixes
- fix: prevent error when check binaries of JSignPdf [#5066](https://github.com/LibreSign/libresign/pull/5066)
- fix: prevent warning when install first time [#5064](https://github.com/LibreSign/libresign/pull/5064)

## 10.8.3 - 2025-05-28
### Changes
- chore: improve tip to sysadmin [#5054](https://github.com/LibreSign/libresign/pull/5054)

### Fixes
- fix: prevent error when check binaries of JSignPdf [#5065](https://github.com/LibreSign/libresign/pull/5065)
- fix: prevent warning when install first time [#5063](https://github.com/LibreSign/libresign/pull/5063)

## 11.2.2 - 2025-05-26
### Changes
- chore: add more ways to get signer email [#5048](https://github.com/LibreSign/libresign/pull/5048)

### Fixes
- fix: fallback when system haven't a TTF font [#5045](https://github.com/LibreSign/libresign/pull/5045)
- fix: show 'Dismiss notification' button on signed file notification [#5042](https://github.com/LibreSign/libresign/pull/5042)

## 10.8.2 - 2025-05-26
### Changes
- chore: add more ways to get signer email [#5047](https://github.com/LibreSign/libresign/pull/5047)

### Fixes
- fix: fallback when system haven't a TTF font [#5044](https://github.com/LibreSign/libresign/pull/5044)
- fix: show 'Dismiss notification' button on signed file notification [#5041](https://github.com/LibreSign/libresign/pull/5041)

## 11.2.1 - 2025-05-25
### Changes
- Update translations
- chore: remove unused property [#5029](https://github.com/LibreSign/libresign/pull/5029)
- chore: hide JSignPDF config check [#5025](https://github.com/LibreSign/libresign/pull/5025)
- chore: translators tips [#5020](https://github.com/LibreSign/libresign/pull/5020)
- chore: implement Rector [#5003](https://github.com/LibreSign/libresign/pull/5003)
- chore: move php tests to php folder [#5001](https://github.com/LibreSign/libresign/pull/5001)
- chore: rename method [#4998](https://github.com/LibreSign/libresign/pull/4998)

### Fixes
- fix: prevent destroy temp files [#5027](https://github.com/LibreSign/libresign/pull/5027)
- fix: prevent flakiness at scenario with time [#5017](https://github.com/LibreSign/libresign/pull/5017)
- fix: notification and activity parameters [#5014](https://github.com/LibreSign/libresign/pull/5014)
- fix: throw error when identify by email is disabled [#4994](https://github.com/LibreSign/libresign/pull/4994)
- fix: display error message instead of json [#4990](https://github.com/LibreSign/libresign/pull/4990)

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

## 11.2.0 - 2025-05-20
### Features
- feat: manage certificate policy [#4970](https://github.com/LibreSign/libresign/pull/4970)
- feat: add administration settings to settings menu [#4968](https://github.com/LibreSign/libresign/pull/4968)
- feat: adding new activity configuration [#4985](https://github.com/LibreSign/libresign/pull/4985)

### Changes
- Update translations
- Bump dependencies
- chore: move strings to constants [#4978](https://github.com/LibreSign/libresign/pull/4978)
- chore: valdiate password before send to sign [#4966](https://github.com/LibreSign/libresign/pull/4966)
- chore: text improvement [#4961](https://github.com/LibreSign/libresign/pull/4961)
- chore: change save signed file logic [#4946](https://github.com/LibreSign/libresign/pull/4946)
- chore: improve error message [#4943](https://github.com/LibreSign/libresign/pull/4943)
- chore: improve feedback of configure check [#4916](https://github.com/LibreSign/libresign/pull/4916)
- chore: reduce configure check time [#4879](https://github.com/LibreSign/libresign/pull/4879)
- chore: remove unecessary else [#4871](https://github.com/LibreSign/libresign/pull/4871)

### Fixes
- fix: allow to sign without account [#4983](https://github.com/LibreSign/libresign/pull/4983)
- fix: prevent merge when haven't a signature [#4975](https://github.com/LibreSign/libresign/pull/4975)
- fix: css at validation page [#4954](https://github.com/LibreSign/libresign/pull/4954)
- fix: send fileSrc to PDF Editor [#4951](https://github.com/LibreSign/libresign/pull/4951)
- fix: test identify method [#4931](https://github.com/LibreSign/libresign/pull/4931)
- fix: order signers by id [#4929](https://github.com/LibreSign/libresign/pull/4929)
- fix: main license file [#4869](https://github.com/LibreSign/libresign/pull/4869)

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

## 11.1.2 - 2025-04-14
### Changes
- Update translations

### Fixes
- fix: user userId when validate file [#4857](https://github.com/LibreSign/libresign/pull/4857)
- fix: only notify when is not draft [#4855](https://github.com/LibreSign/libresign/pull/4855)
- fix: prevent json decode null [#4852](https://github.com/LibreSign/libresign/pull/4852)

## 10.7.2 - 2025-04-14
### Changes
- Update translations

### Fixes
- fix: user userId when validate file [#4856](https://github.com/LibreSign/libresign/pull/4856)
- fix: only notify when is not draft [#4854](https://github.com/LibreSign/libresign/pull/4854)
- fix: prevent json decode null [#4851](https://github.com/LibreSign/libresign/pull/4851)

## 11.1.1 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4841](https://github.com/LibreSign/libresign/pull/4841)
- fix typo: descriptin -> description [#4837](https://github.com/LibreSign/libresign/pull/4837)

## 10.7.1 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4840](https://github.com/LibreSign/libresign/pull/4840)
- fix typo: descriptin -> description [#4836](https://github.com/LibreSign/libresign/pull/4836)

## 9.9.6 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4842](https://github.com/LibreSign/libresign/pull/4842)

## 11.1.0 - 2025-04-11
### Features
- feat: customize signature stamp [#4827](https://github.com/LibreSign/libresign/pull/4827)
- feat: add group activity [#4793](https://github.com/LibreSign/libresign/pull/4793)

### Changes
- Update translations
- Bump dependencies
- chore: replace deprecated properties [#4808](https://github.com/LibreSign/libresign/pull/4808)
- chore: cover jsignparam with tests [#4790](https://github.com/LibreSign/libresign/pull/4790)
- refactor: adding types to entities [#4776](https://github.com/LibreSign/libresign/pull/4776)

### Fixes
- fix: time stamp when then document signed [#4823](https://github.com/LibreSign/libresign/pull/4823)
- fix: optimize file loading [#4820](https://github.com/LibreSign/libresign/pull/4820)
- fix: check if preview is available [#4818](https://github.com/LibreSign/libresign/pull/4818)
- fix: prevent error when output of pdfsig is empty [#4816](https://github.com/LibreSign/libresign/pull/4816)
- fix: prevent error when collect metadata [#4787](https://github.com/LibreSign/libresign/pull/4787)
- refactor: fix var typo [#4779](https://github.com/LibreSign/libresign/pull/4779)
- fix: psalm issue [#4761](https://github.com/LibreSign/libresign/pull/4761)

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

## 9.9.5 - 2025-04-11
### Changes
- Update translations
- Bump dependencies
- chore: cover jsignparam with tests [#4788](https://github.com/LibreSign/libresign/pull/4788)
- refactor: adding types to entities [#4774](https://github.com/LibreSign/libresign/pull/4774)

### Fixes
- fix: optimize file loading [#4825](https://github.com/LibreSign/libresign/pull/4825)
- fix: time stamp when then document signed [#4824](https://github.com/LibreSign/libresign/pull/4824)
- fix: prevent error when collect metadata [#4785](https://github.com/LibreSign/libresign/pull/4785)
- refactor: fix var typo [#4777](https://github.com/LibreSign/libresign/pull/4777)
- fix: psalm issue [#4759](https://github.com/LibreSign/libresign/pull/4759)

## 11.0.4 - 2025-03-20
### Changes
- Update translations
- Bump dependencies

## 10.6.4 - 2025-03-20
### Changes
- Update translations
- Bump dependencies

## 9.9.4 - 2025-03-20
### Changes
- Update translations
- Bump dependencies

## 11.0.3 - 2025-02-28
### Changes
- Update translations
- Bump dependencies
- chore: update openapi [#4696](https://github.com/LibreSign/libresign/pull/4696)

### Fixes
- fix: restrict access to validation endpoints [#4703](https://github.com/LibreSign/libresign/pull/4703)
- fix: add information note about visible signature [#4688](https://github.com/LibreSign/libresign/pull/4688)

## 10.6.3 - 2025-02-28
### Changes
- Update translations
- Bump dependencies
- chore: update openapi [#4695](https://github.com/LibreSign/libresign/pull/4695)

### Fixes
- fix: restrict access to validation endpoints [#4701](https://github.com/LibreSign/libresign/pull/4701)
- fix: add information note about visible signature [#4687](https://github.com/LibreSign/libresign/pull/4687)

## 9.9.3 - 2025-02-28
### Changes
- Update translations
- Bump dependencies

### Fixes
- fix: restrict access to validation endpoints [#4702](https://github.com/LibreSign/libresign/pull/4702)
- fix: add information note about visible signature [#4686](https://github.com/LibreSign/libresign/pull/4686)

## 11.0.2 - 2025-02-21
### Changes
- Update translations
- Bump dependencies
- chore: replace deprecated function [#4648](https://github.com/LibreSign/libresign/pull/4648)
- chore: remove wrong annotation [#4604](https://github.com/LibreSign/libresign/pull/4604)
- chore: check if user exists [#4601](https://github.com/LibreSign/libresign/pull/4601)
- chore: prevent create cfssl config path every time [#4587](https://github.com/LibreSign/libresign/pull/4587)
- chore: tests improvement [#4583](https://github.com/LibreSign/libresign/pull/4583)
- chore: small tests improvement [#4580](https://github.com/LibreSign/libresign/pull/4580)
- refactor: moved hashes to be close to version number [#4574](https://github.com/LibreSign/libresign/pull/4574)
- refactor: moved version of JSignPdf to InstallService [#4570](https://github.com/LibreSign/libresign/pull/4570)

### Fixes
- fix: add pending code [#4654](https://github.com/LibreSign/libresign/pull/4654)
- fix: prevent success when signature file dont exists [#4642](https://github.com/LibreSign/libresign/pull/4642)
- fix: ltr language [#4632](https://github.com/LibreSign/libresign/pull/4632)
- fix: use entities instead of char convertoing [#4629](https://github.com/LibreSign/libresign/pull/4629)
- fix: hide request button to anauthorized account [#4623](https://github.com/LibreSign/libresign/pull/4623)
- fix: add maxlength to names of cert [#4608](https://github.com/LibreSign/libresign/pull/4608)
- fix: typo [#4591](https://github.com/LibreSign/libresign/pull/4591)
- fix: prevent generate a path without existing folder [#4565](https://github.com/LibreSign/libresign/pull/4565)
- fix: prevent warning when haven't names [#4562](https://github.com/LibreSign/libresign/pull/4562)
- fix: prevent warning of fsockopen [#4554](https://github.com/LibreSign/libresign/pull/4554)
- fix: prevent delete binary files when execute unit tests [#4544](https://github.com/LibreSign/libresign/pull/4544)

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

## 9.9.2 - 2025-02-21
### Changes
- Update translations
- Bump dependencies
- chore: replace deprecated function [#4646](https://github.com/LibreSign/libresign/pull/4646)
- chore: remove wrong annotation [#4602](https://github.com/LibreSign/libresign/pull/4602)
- chore: check if user exists [#4599](https://github.com/LibreSign/libresign/pull/4599)
- chore: prevent create cfssl config path every time [#4585](https://github.com/LibreSign/libresign/pull/4585)
- chore: tests improvement [#4582](https://github.com/LibreSign/libresign/pull/4582)
- refactor: moved hashes to be close to version number [#4573](https://github.com/LibreSign/libresign/pull/4573)
- refactor: moved version of JSignPdf to InstallService [#4569](https://github.com/LibreSign/libresign/pull/4569)

### Fixes
- fix: prevent success when signature file dont exists [#4640](https://github.com/LibreSign/libresign/pull/4640)
- fix: ltr language [#4631](https://github.com/LibreSign/libresign/pull/4631)
- fix: use entities instead of char convertoing [#4627](https://github.com/LibreSign/libresign/pull/4627)
- fix: hide request button to anauthorized account [#4621](https://github.com/LibreSign/libresign/pull/4621)
- fix: add maxlength to names of cert [#4606](https://github.com/LibreSign/libresign/pull/4606)
- fix: typo [#4589](https://github.com/LibreSign/libresign/pull/4589)
- fix: prevent generate a path without existing folder [#4563](https://github.com/LibreSign/libresign/pull/4563)
- fix: prevent warning when haven't names [#4560](https://github.com/LibreSign/libresign/pull/4560)
- fix: prevent warning of fsockopen [#4552](https://github.com/LibreSign/libresign/pull/4552)
- fix: prevent delete binary files when execute unit tests [#4545](https://github.com/LibreSign/libresign/pull/4545)

## 11.0.1 - 2025-01-28
### Changes
- Update translations
- Bump dependencies
- chore: bump java [#4533](https://github.com/LibreSign/libresign/pull/4533)
- refactor: convert to promisse [#4525](https://github.com/LibreSign/libresign/pull/4525)
- refactor: force typing [#4506](https://github.com/LibreSign/libresign/pull/4506)

### Fixes
- fix: prevent error when enpty data from backend [#4526](https://github.com/LibreSign/libresign/pull/4526)
- fix: use async await [#4522](https://github.com/LibreSign/libresign/pull/4522)
- fix: retrieve saved data [#4517](https://github.com/LibreSign/libresign/pull/4517)
- fix: display errors at error page [#4514](https://github.com/LibreSign/libresign/pull/4514)
- fix: display error at same route [#4510](https://github.com/LibreSign/libresign/pull/4510)
- fix: hide sidebar when is not necessary [#4503](https://github.com/LibreSign/libresign/pull/4503)
- fix: redirect to login when validation page is not public [#4499](https://github.com/LibreSign/libresign/pull/4499)
- fix: logout if is using different account [#4496](https://github.com/LibreSign/libresign/pull/4496)
- fix: prevent error when validate signed file using cfssl cert [#4478](https://github.com/LibreSign/libresign/pull/4478)
- fix: remove licence file [#4474](https://github.com/LibreSign/libresign/pull/4474)

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

## 9.9.1 - 2025-01-28
### Changes
- Update translations
- Bump dependencies
- chore: bump java [#4531](https://github.com/LibreSign/libresign/pull/4531)
- refactor: convert to promisse [#4529](https://github.com/LibreSign/libresign/pull/4529)
- refactor: force typing [#4504](https://github.com/LibreSign/libresign/pull/4504)

### Fixes
- fix: prevent error when enpty data from backend [#4528](https://github.com/LibreSign/libresign/pull/4528)
- fix: use async await [#4521](https://github.com/LibreSign/libresign/pull/4521)
- fix: retrieve saved data [#4516](https://github.com/LibreSign/libresign/pull/4516)
- fix: display errors at error page [#4512](https://github.com/LibreSign/libresign/pull/4512)
- fix: display error at same route [#4508](https://github.com/LibreSign/libresign/pull/4508)
- fix: hide sidebar when is not necessary [#4501](https://github.com/LibreSign/libresign/pull/4501)
- fix: redirect to login when validation page is not public [#4497](https://github.com/LibreSign/libresign/pull/4497)
- fix: logout if is using different account [#4494](https://github.com/LibreSign/libresign/pull/4494)
- fix: prevent error when validate signed file using cfssl cert [#4476](https://github.com/LibreSign/libresign/pull/4476)

## 11.0.0 - 2025-01-27
### Feature
- Say hello to Nexcloud 31

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

## 9.9.0 - 2025-01-27
### Features
- feat: add extracerts to generated cert [#4426](https://github.com/LibreSign/libresign/pull/4426)
- feat: parse extracerts content [#4414](https://github.com/LibreSign/libresign/pull/4414)

### Changes
- Update translations
- Bump dependencies
- chore: use fallback to get page dimension [#4461](https://github.com/LibreSign/libresign/pull/4461)
- chore: remove vuex [#4431](https://github.com/LibreSign/libresign/pull/4431)
- chore(i18n): Fixed grammar [#4412](https://github.com/LibreSign/libresign/pull/4412)
- chore: remove unused property [#4408](https://github.com/LibreSign/libresign/pull/4408)
- chore: validate signer of signed pdf file [#4405](https://github.com/LibreSign/libresign/pull/4405)
- chore: ignore warning of Nextcloud [#4402](https://github.com/LibreSign/libresign/pull/4402)
- chore: only display div of chains if chain exists [#4399](https://github.com/LibreSign/libresign/pull/4399)

### Fixes
- fix: save as 0 or 1 [#4450](https://github.com/LibreSign/libresign/pull/4450)
- fix: consider different values of settings [#4447](https://github.com/LibreSign/libresign/pull/4447)
- fix: path of url [#4439](https://github.com/LibreSign/libresign/pull/4439)

## 10.5.3 - 2025-01-19
### Changes
- chore: bump dependencies
- chore: prevent generate unecessary temp file [#4393](https://github.com/LibreSign/libresign/pull/4393)

### fixes
- fix: view pdf at validation page [#4391](https://github.com/LibreSign/libresign/pull/4391)
- fix: list files with deleted signer [#4385](https://github.com/LibreSign/libresign/pull/4385)

## 9.8.3 - 2025-01-19
### Changes
- chore: prevent generate unecessary temp file [#4392](https://github.com/LibreSign/libresign/pull/4392)
- chore: bump dependencies [#4381](https://github.com/LibreSign/libresign/pull/4381)

### fixes
- fix: view pdf at validation page [#4390](https://github.com/LibreSign/libresign/pull/4390)
- fix: list files with deleted signer [#4384](https://github.com/LibreSign/libresign/pull/4384)

## 10.5.2 - 2025-01-16
### fixes
- fix: handle settings after backend upgrade [#4372](https://github.com/LibreSign/libresign/pull/4372)
- fix: prevent error when is empty files [#4369](https://github.com/LibreSign/libresign/pull/4369)
- fix: validation url [#4364](https://github.com/LibreSign/libresign/pull/4364)
- chore: add more details to pdf viewer [#4362](https://github.com/LibreSign/libresign/pull/4362)
- fix: prevent error when get timeout from api [#4359](https://github.com/LibreSign/libresign/pull/4359)
- fix: close dialog after submit [#4356](https://github.com/LibreSign/libresign/pull/4356)

## 9.8.2 - 2025-01-16
### fixes
- fix: handle settings after backend upgrade [#4371](https://github.com/LibreSign/libresign/pull/4371)
- fix: prevent error when is empty files [#4370](https://github.com/LibreSign/libresign/pull/4370)
- fix: validation url [#4363](https://github.com/LibreSign/libresign/pull/4363)
- fix: prevent error when get timeout from api [#4358](https://github.com/LibreSign/libresign/pull/4358)
- fix: close dialog after submit [#4355](https://github.com/LibreSign/libresign/pull/4355)

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

## 9.8.1 - 2025-01-16
### fixes
- fix: submit on click [#4342](https://github.com/LibreSign/libresign/pull/4342)
- fix: prevent error when have not identify method [#4338](https://github.com/LibreSign/libresign/pull/4338)
- fix: validate with success when signer account was deleted [#4333](https://github.com/LibreSign/libresign/pull/4333)
- fix: When only have a signature, consider that who signed is who need… [#4319](https://github.com/LibreSign/libresign/pull/4319)
- fix: show that file not found when validate file [#4318](https://github.com/LibreSign/libresign/pull/4318)
- fix: match signature from file with libresign [#4310](https://github.com/LibreSign/libresign/pull/4310)
- fix: load success icon when cert is valid [#4307](https://github.com/LibreSign/libresign/pull/4307)
- fix: match signers from cert with signers from LibreSign [#4306](https://github.com/LibreSign/libresign/pull/4306)

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

## 9.8.0 - 2025-01-13
### Changes
- Update translations
- Bump dependencies
- feat: validate from uploaded file [#4254](https://github.com/LibreSign/libresign/pull/4254)
- feat: validate pdf [#4233](https://github.com/LibreSign/libresign/pull/4233)
- feat: change expirity [#4231](https://github.com/LibreSign/libresign/pull/4231)
- feat: rewrite validation page [#4205](https://github.com/LibreSign/libresign/pull/4205)
- feat: add rate LibreSign [#4202](https://github.com/LibreSign/libresign/pull/4202)
- feat: allow to change signature hash algorithm [#4191](https://github.com/LibreSign/libresign/pull/4191)
- chore: display signature issue when haven't proppler [#4298](https://github.com/LibreSign/libresign/pull/4298)
- chore: make possible press enter to submit some forms [#4236](https://github.com/LibreSign/libresign/pull/4236)

### Fixes
- fix: prevent error when add new signer [#4294](https://github.com/LibreSign/libresign/pull/4294)
- fix: prevent js error [#4289](https://github.com/LibreSign/libresign/pull/4289)
- fix: notify by email when is not authenticated [#4279](https://github.com/LibreSign/libresign/pull/4279)
- fix: method name [#4277](https://github.com/LibreSign/libresign/pull/4277)
- fix: center component [#4273](https://github.com/LibreSign/libresign/pull/4273)
- fix: method name [#4270](https://github.com/LibreSign/libresign/pull/4270)
- fix: path of renew url [#4268](https://github.com/LibreSign/libresign/pull/4268)
- fix: ignore order of array [#4256](https://github.com/LibreSign/libresign/pull/4256)
- fix: load cert custom options [#4244](https://github.com/LibreSign/libresign/pull/4244)
- fix: display cfssl settings [#4228](https://github.com/LibreSign/libresign/pull/4228)
- fix: display certificate data after regenerate certificate [#4196](https://github.com/LibreSign/libresign/pull/4196)
- fix: fetch signature methods [#4189](https://github.com/LibreSign/libresign/pull/4189)
- fix: remove extension from filename [#4186](https://github.com/LibreSign/libresign/pull/4186)

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

## 9.7.4 - 2024-12-13
### Fixes
* fix: load signature methods [4143](https://github.com/LibreSign/libresign/pull/4143)
* fix: footer in pages with different sizes [4141](https://github.com/LibreSign/libresign/pull/4141)
* fix: consider filter status to toggle components [4101](https://github.com/LibreSign/libresign/pull/4101)
* fix: block access to route when isn't allowed by admin [4095](https://github.com/LibreSign/libresign/pull/4095)

### Changes
* Update translations
* Bump dependencies
* chore: clean code [4089](https://github.com/LibreSign/libresign/pull/4089)

## 10.4.3 - 2024-11-30
### Fixes
* fix: prevent error when using PostgreSQL [4082](https://github.com/LibreSign/libresign/pull/4082)

### Changes
* Update translations

## 9.7.3 - 2024-11-30
### Fixes
* fix: prevent error when using PostgreSQL [4081](https://github.com/LibreSign/libresign/pull/4081)

### Changes
* Update translations

## 10.4.2 - 2024-11-29
### Fixes
* fix: list files from PostgreSQL [4076](https://github.com/LibreSign/libresign/pull/4076)

### Changes
* Update translations

## 9.7.2 - 2024-11-29
### Fixes
* fix: list files from PostgreSQL [4075](https://github.com/LibreSign/libresign/pull/4075)

### Changes
* Update translations

## 10.4.1 - 2024-11-26
### Fixes
* fix: Icon color att app files [4058](https://github.com/LibreSign/libresign/pull/4058)
* fix: prevent error when click at signer to add to document [4056](https://github.com/LibreSign/libresign/pull/4056)
* fix: toggle loading [4054](https://github.com/LibreSign/libresign/pull/4054)
* fix: prevent don't delete file when folder is deleted [4061](https://github.com/LibreSign/libresign/pull/4061)

## 9.7.1 - 2024-11-26
### Fixes
* fix: Icon color att app files [4057](https://github.com/LibreSign/libresign/pull/4057)
* fix: prevent error when click at signer to add to document [4055](https://github.com/LibreSign/libresign/pull/4055)
* fix: toggle loading [4053](https://github.com/LibreSign/libresign/pull/4053)
* fix: prevent don't delete file when folder is deleted [4060](https://github.com/LibreSign/libresign/pull/4060)

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

## 9.7.0 - 2024-11-25
### Fixes
* fix: JS error when upload file [4034](https://github.com/LibreSign/libresign/pull/4034)
* fix: show message when file list is empty [4032](https://github.com/LibreSign/libresign/pull/4032)

### Changes
* Update translations
* feat: delete multiple files [4027](https://github.com/LibreSign/libresign/pull/4027)

### Chore
* chore: bump dependencies [4046](https://github.com/LibreSign/libresign/pull/4046)
* chore: show loading before finish load file list [4042](https://github.com/LibreSign/libresign/pull/4042)
* chore: disable Actions menu when click in an action [4040](https://github.com/LibreSign/libresign/pull/4040)
* chore: unify code into a new component [4036](https://github.com/LibreSign/libresign/pull/4036)

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

## 9.6.1 - 2024-11-23
### Fixes
* fix: assure that all signers will have an unique id [4017](https://github.com/LibreSign/libresign/pull/4017)
* fix: show actions at signer list [4014](https://github.com/LibreSign/libresign/pull/4014)

### Changes
* feat: add footer to file list [4020](https://github.com/LibreSign/libresign/pull/4020)
* feat: only show name and allow save signer when have signer [4009](https://github.com/LibreSign/libresign/pull/4009)
* Update translations

### Chore
* chore(deps): Bump @nextcloud/vue from 8.20.0 to 8.21.0 [4013](https://github.com/LibreSign/libresign/pull/4013)

## 10.3.0 - 2024-11-20
### Fixes
* fix: retrieve file when request to sign from file list [3998](https://github.com/LibreSign/libresign/pull/3998)

### Changes
* feat: make possible choose the page [3984](https://github.com/LibreSign/libresign/pull/3984)
* Update translations

### Chore
* chore: bump dependencies at PHP and JS side
* chore: refresh file list every when load view [4001](https://github.com/LibreSign/libresign/pull/4001)

## 9.6.0 - 2024-11-20
### Fixes
* fix: retrieve file when request to sign from file list [3997](https://github.com/LibreSign/libresign/pull/3997)

### Changes
* feat: make possible choose the page [3988](https://github.com/LibreSign/libresign/pull/3988)
* Update translations

### Chore
* chore: bump dependencies at PHP and JS side
* chore: refresh file list every when load view [4000](https://github.com/LibreSign/libresign/pull/4000)

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

## 9.5.0 - 2024-11-15
### Fixes
* fix: only show return when come from validation button [3969](https://github.com/LibreSign/libresign/pull/3969)
* fix: go ahead if the file is not found [3971](https://github.com/LibreSign/libresign/pull/3971)
* fix: filter files by signer uuid [3962](https://github.com/LibreSign/libresign/pull/3962)
* fix: toggle sidebar [3958](https://github.com/LibreSign/libresign/pull/3958)
* fix: add back the contition to write_qrcode_on_footer [3931](https://github.com/LibreSign/libresign/pull/3931)
* fix: Use unicode signer name [3929](https://github.com/LibreSign/libresign/pull/3929)

### Changes
* feat: request to sign from files [3946](https://github.com/LibreSign/libresign/pull/3946)
* feat: write success after end of configure [3936](https://github.com/LibreSign/libresign/pull/3936)
* Update translations

### Chore
* Bump dependencies
* chore: feedback improvement [3974](https://github.com/LibreSign/libresign/pull/3974)
* chore: disable buttons when is processing the action [3968](https://github.com/LibreSign/libresign/pull/3968)
* chore: replace :value.sync by v-model [3953](https://github.com/LibreSign/libresign/pull/3953)
* chore: improve cfssl validation [3943](https://github.com/LibreSign/libresign/pull/3943)
* chore: Optimize svg image [3938](https://github.com/LibreSign/libresign/pull/3938)

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

## 9.4.0 - 2024-11-07
### Fixes
* fix: open notification as internal url https://github.com/LibreSign/libresign/pull/3712
* fix: close button https://github.com/LibreSign/libresign/pull/3723
* fix: typo https://github.com/LibreSign/libresign/pull/3734
* fix: i18n; Fixed grammar https://github.com/LibreSign/libresign/pull/3784
* fix: prevent don't match extension when the file have uppercase name https://github.com/LibreSign/libresign/pull/3820
* fix: open settings together with cms_pico https://github.com/LibreSign/libresign/pull/3825

### Changes
* feat: use Viewer to open pdf https://github.com/LibreSign/libresign/pull/3726
* feat: add documentation url https://github.com/LibreSign/libresign/pull/3816
* feat: rewrite file list https://github.com/LibreSign/libresign/pull/3897
* feat: add OWASP dependency check https://github.com/LibreSign/libresign/pull/3913

### Chore
* chore: remove unused packages and code
* chore: convert indent size and apply updated linter rules
* chore: update api documentation https://github.com/LibreSign/libresign/pull/3902

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

## 9.3.2 - 2024-09-14
### Fixes
* fix: ajust condition to filter file list [3701](https://github.com/LibreSign/libresign/pull/3701)
* fix: prevent warning when check if array has key [3691](https://github.com/LibreSign/libresign/pull/3691)
* fix: prevent duplicate text [3689](https://github.com/LibreSign/libresign/pull/3689)
* fix: notification parameters need to be string [3682](https://github.com/LibreSign/libresign/pull/3682)

### Changes
* chore: adjust filter condition [3704](https://github.com/LibreSign/libresign/pull/3704)
* chore: validation setup improvement [3696](https://github.com/LibreSign/libresign/pull/3696)
* bump dependencies
* Update translations

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

## 10.0.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3655](https://github.com/LibreSign/libresign/pull/3655)

## 9.3.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3654](https://github.com/LibreSign/libresign/pull/3654)

## 8.3.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3653](https://github.com/LibreSign/libresign/pull/3653)

## 10.0.0 - 2024-09-10
* Say hello to Nextcloud 30 🎉

## 9.3.0 - 2024-09-10
### New feature
* Support to GitHub codespace and devcontainers
* Add filter by status to listing at API side [3603](https://github.com/LibreSign/libresign/pull/3603)

### Changes
* chore: Test signature proccess [3581](https://github.com/LibreSign/libresign/pull/3581)
* chore: add unit tests [3504](https://github.com/LibreSign/libresign/pull/3504)

### Fixes
* fix: prevent error when resync sequence of other apps [3606](https://github.com/LibreSign/libresign/pull/3606)
* fix: internal route [3626](https://github.com/LibreSign/libresign/pull/3626)
* fix: js linter warning [3577](https://github.com/LibreSign/libresign/pull/3577)
* fix: draw width [3546](https://github.com/LibreSign/libresign/pull/3546)
* fix: handle error when is invalid password [3484](https://github.com/LibreSign/libresign/pull/3484)
* fix: prevent js error when disabled for user [3487](https://github.com/LibreSign/libresign/pull/3487)
* fix: git safe directory [3451](https://github.com/LibreSign/libresign/pull/3451)

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

## 9.2.3 - 2024-07-24
### New feature
feat: implement support to devcontainer [3398](https://github.com/LibreSign/libresign/pull/3398)
feat: implement endpoint to disable hate limit [3394](https://github.com/LibreSign/libresign/pull/3394)

### Changes
chore: add tsconfig by @Any97Cris [3445](https://github.com/LibreSign/libresign/pull/3445)
chore: remove unecessary string [3418](https://github.com/LibreSign/libresign/pull/3418)
chore: remove unecessary var [3414](https://github.com/LibreSign/libresign/pull/3414)
chore: replace way to identify Alpine Linux [3390](https://github.com/LibreSign/libresign/pull/3390)
chore: prevent error when try to delete user that haven't uid [3392](https://github.com/LibreSign/libresign/pull/3392)
chore: changelog [3369](https://github.com/LibreSign/libresign/pull/3369)
chore: changelog [3363](https://github.com/LibreSign/libresign/pull/3363)
chore: changelog [3356](https://github.com/LibreSign/libresign/pull/3356)

### Fixes
fix: imporve validation [3438](https://github.com/LibreSign/libresign/pull/3438)
fix: cfsslUri is optional value [3440](https://github.com/LibreSign/libresign/pull/3440)
fix: run test in separated proccess [3442](https://github.com/LibreSign/libresign/pull/3442)
fix: route verb [3428](https://github.com/LibreSign/libresign/pull/3428)
fix: name of button after generate OpenSSL certificate [3430](https://github.com/LibreSign/libresign/pull/3430)
fix: prevent error when use relative path [3419](https://github.com/LibreSign/libresign/pull/3419)
fix: set linux distro before validate downloaded files [3416](https://github.com/LibreSign/libresign/pull/3416)
fix: check if certificate was generated [3408](https://github.com/LibreSign/libresign/pull/3408)
fix: resynchronize database sequences [3402](https://github.com/LibreSign/libresign/pull/3402)
fix: use linux distro when build [3367](https://github.com/LibreSign/libresign/pull/3367)
fix: Java setup [3360](https://github.com/LibreSign/libresign/pull/3360)
fix: setup at alpine [3354](https://github.com/LibreSign/libresign/pull/3354)

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

## 9.2.2 - 2024-07-12
### Fixes
* fix: use linux distro when build [3367]https://github.com/LibreSign/libresign/pull/3367

## 8.2.3 - 2024-07-12
### Fixes
* fix: use linux distro when build [3366]https://github.com/LibreSign/libresign/pull/3366

## 9.2.1 - 2024-07-12
### Fixes
* fix: Java setup [3360]https://github.com/LibreSign/libresign/pull/3360
* fix: setup at alpine [#3354](https://github.com/LibreSign/libresign/pull/3354)

## 8.2.2 - 2024-07-12
### Fixes
* fix: Java setup [3361]https://github.com/LibreSign/libresign/pull/3361

## 8.2.1 - 2024-07-11
### Fixes
* fix: setup at alpine [#3354](https://github.com/LibreSign/libresign/pull/3354)

## 9.2.0 - 2024-07-11
### Changed
* bump cs fixer [#3328](https://github.com/LibreSign/libresign/pull/3328)
* ui improvements [#3331](https://github.com/LibreSign/libresign/pull/3331)
* js optimizations [#3323](https://github.com/LibreSign/libresign/pull/3323)
* reduce a query when delete file [#3321](https://github.com/LibreSign/libresign/pull/3321)
* bump dependencies [#3309](https://github.com/LibreSign/libresign/pull/3309)
* use engine name at tip [#3306](https://github.com/LibreSign/libresign/pull/3306)

### Fixes
* install and check process [#3342](https://github.com/LibreSign/libresign/pull/3342)
* prevent error when try to create folder and alreay exists [#3338](https://github.com/LibreSign/libresign/pull/3338)
* Prevent error when haven't ps command [#3316](https://github.com/LibreSign/libresign/pull/3316)

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

## 9.1.3 - 2024-07-08
### Changed
* chore: update workflows [3254](https://github.com/LibreSign/libresign/pull/3254)
* chore: bump dependencies of integration tests [3268](https://github.com/LibreSign/libresign/pull/3268)
* chore: move account routes definition to attributes [3269](https://github.com/LibreSign/libresign/pull/3269)

### Fixes
* fix: pack openapi json file [3248](https://github.com/LibreSign/libresign/pull/3248)
* fix: use equal to option [3258](https://github.com/LibreSign/libresign/pull/3258)
* fix: sign setup when build [3263](https://github.com/LibreSign/libresign/pull/3263)
* fix: pagination [3271](https://github.com/LibreSign/libresign/pull/3271)

## 8.1.3 - 2024-07-08
### Changed
* chore: update workflows [3255](https://github.com/LibreSign/libresign/pull/3255)
* chore: bump dependencies of integration tests [3267](https://github.com/LibreSign/libresign/pull/3267)

### Fixes
* fix: pack openapi json file [3247](https://github.com/LibreSign/libresign/pull/3247)
* fix: use equal to option [3257](https://github.com/LibreSign/libresign/pull/3257)
* fix: sign setup when build [3262](https://github.com/LibreSign/libresign/pull/3262)
* fix: pagination [3270](https://github.com/LibreSign/libresign/pull/3270)

## 9.1.2 - 2024-06-28
### Fixes
* fix: Internal error when signing [#3238](github.com/libresign/libresign/pull/3238)


## 8.1.2 - 2024-06-28
### Fixes
* fix: Internal error when signing [#3238](github.com/libresign/libresign/pull/3238)


## 9.1.1 - 2024-06-26
### Changed
* Disable sign button when is loading [#3225](https://github.com/libresign/libresign/pull/3225)
* Bump dependencies

### Fixes
* signing dependencies at deploy to Nextcloud app store [#3234](https://github.com/libresign/libresign/pull/3234)
* Make possible use multiple signatures of same signer [#3229](https://github.com/libresign/libresign/pull/3229)
* neutralize deleted users [#3222](https://github.com/libresign/libresign/pull/3222)

## 8.1.1 - 2024-06-26
### Changed
* Disable sign button when is loading [#3224](https://github.com/libresign/libresign/pull/3224)
* Bump dependencies

### Fixes
* signing dependencies at deploy to Nextcloud app store [#3233](https://github.com/libresign/libresign/pull/3233)
* Make possible use multiple signatures of same signer [#3228](https://github.com/libresign/libresign/pull/3228)
* neutralize deleted users [#3221](https://github.com/libresign/libresign/pull/3221)

## 9.1.0 - 2024-06-24
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

## 9.0.2 - 2024-05-10
### New feature
* feat: finish setup in https://github.com/LibreSign/libresign/pull/3039

### Changed
* Updated translations

### Fixed
* fix: check if is alpine by @backportbot-libresign in https://github.com/LibreSign/libresign/pull/3049

## 8.0.2 - 2024-05-10
### New feature
* feat: finish setup in https://github.com/LibreSign/libresign/pull/3039

### Changed
* Updated translations

### Fixed
* fix: check if is alpine by @backportbot-libresign in https://github.com/LibreSign/libresign/pull/3049

## 9.0.1 - 2024-05-10
### Changed
* Update translations
* Make possible customize the document footer using HTML [#2970](https://github.com/LibreSign/libresign/pull/2970)
* Update dependencies at front and backend

### Fixed
* Fix position of components when preview document before sign

## 8.0.1 - 2024-05-10
### Changed
* Update translations
* Make possible customize the document footer using HTML [#2970](https://github.com/LibreSign/libresign/pull/2970)
* Update dependencies at front and backend

### Fixed
* Fix position of components when preview document before sign

## 9.0.0 - 2024-04-24
### ✨Big changes to a new moment
* 📝 Allow you to sign documents without creating an account
* 🔒 Create root certificate with OpenSSL
* 📜 Possibility to send and sign with your own certificate
* 🛠️ Simplified setup

## 8.0.0 - 2024-04-24
### ✨Big changes to a new moment
* 📝 Allow you to sign documents without creating an account
* 🔒 Create root certificate with OpenSSL
* 📜 Possibility to send and sign with your own certificate
* 🛠️ Simplified setup

## 7.1.1 - 2023-04-12
### Changed
* Update translations
* Drop libresign cli
* Add identify method
* Add more tests
* Bump packages
### Fixed
* fix: style preview signatur modal like canva style

## 7.1.0 - 2023-04-01
### Changed
* Make possible change the default user folder
* Hide initial and fix save signature modal
* Hide generate passowrd when cert handler isn't ok
* Remove sidebar marging
* Change validate page image
* Make the text more clear
* Increase PDF validation

### Fixed
* Fix save signature as image
* Fix generate root certificate at the first time

## 7.0.0 - 2023-03-21
### Changed
* Compatibility with Nextcloud Hub 4 (26)

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

## 5.2.0 - 2022-10-23
### Changed
* Easy setup without necessity to run commands in server
* Updated translations
* Bump JSignPdf
* Prevent delete signed file when original file was deleted

## 4.2.0 - 2022-10-23
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

## 2.4.3 - 2021-07-14

### Changed
- Update translations
- API message changes, thanks to, thanks to @rakekniven and @Valdnet

## 2.4.2 - 2021-07-08

### Added
- List of documents
- User profile
- Filter files
- Add qrcode to footer
- Validate by LibreSign App
- Request sign by LibreSign App
- Resend sign invite email
- App config to configure JSignPDF
- Added integration with Approval app on README.md. Thanks to @eneiluj
- Endpoint to list LibreSign files
- Endpoint to attach files to LibreSign profile
- Endpoints to delete signer and file sign request
- One more step to turn possible replace CFSSL
- Test for validation of Swagger documentation
- GitHub action to add a changelog reminder
- View document on mobile before sign
- Markdown formatting for description
- Libresign button in file options in theh Files app
- Button to redirect to files to view the document
- Legal information on Validation screen, configure in Admin settings
- Validation page, validating by UUID and ID
- Button that takes you to the validation page on all `.signed` and `.signed` files
- Button to validate document in Sidebar into App on menu files.

### Changed
- Bump max Nextcloud version to 23
- Increment of coverage on backend code
- Bug fixes and refactorings resulting from increased coverage
- Use name of user on error message when email is empty
- Logo replaced by new logo
- It will only verify the password if nextcloud requests confirmation of the password by the OC.
- Check if has pfx
- After signing the document, it will update the app files
- Changed wizard to split user creation and pfx creation

### Removed
- Removed dsv folder
- Removed docs folder

### Fixed
- Rendering files tab in Nextcloud 20 and 21
- Invalid method name when validating if a file signature has already been requested
- Tests autoload
- Correction of loading class after clicking sign in application
- Add ellipsis to pdf file title
- Now it is possible to choose a file even if it is inside x folders
- Clear uuid field before returns
- Button to redirect to document validation page

## 2.3.0 - 2021-05-22

### Added and changed

- Allow devtools in the development [#250](https://github.com/LibreSign/libresign/pull/250) @vinicios-gomes
- Add has signature file check [#248](https://github.com/LibreSign/libresign/pull/248) @vitormattos
- Setup changelog in github actions [#246](https://github.com/LibreSign/libresign/pull/246) @vinicios-gomes
- l10n: Correct spelling [#244](https://github.com/LibreSign/libresign/pull/244) @Valdnet
- Validation by route using UUID [#243](https://github.com/LibreSign/libresign/pull/243) @vinicios-gomes
- Add Home [#241](https://github.com/LibreSign/libresign/pull/241) @vinicios-gomes
- Remove unused file [#238](https://github.com/LibreSign/libresign/pull/238) @vitormattos
- Signature password [#236](https://github.com/LibreSign/libresign/pull/236) @vitormattos
- l10n: Correct text strings [#235](https://github.com/LibreSign/libresign/pull/235) @Valdnet
- Validation date format [#234](https://github.com/LibreSign/libresign/pull/234) @raw-vitor
- Update swagger [#233](https://github.com/LibreSign/libresign/pull/233) @vitormattos
- Add validation to yarn.lock [#232](https://github.com/LibreSign/libresign/pull/232) @raw-vitor
- Coverage improvement [#240](https://github.com/LibreSign/libresign/pull/240) @vitormattos
- Coverage improvement [#239](https://github.com/LibreSign/libresign/pull/239) @vitormattos
- Coverage improvement [#237](https://github.com/LibreSign/libresign/pull/237) @vitormattos
- Coverage improvement [#231](https://github.com/LibreSign/libresign/pull/231) @vitormattos
- Coverage improvement [#228](https://github.com/LibreSign/libresign/pull/228) @vitormattos
- Coverage improvement [#226](https://github.com/LibreSign/libresign/pull/226) @vitormattos
- Signature password [#225](https://github.com/LibreSign/libresign/pull/225) @vitormattos
- Create CONTRIBUTING.md [#224](https://github.com/LibreSign/libresign/pull/224) @vitormattos
- Create CODE_OF_CONDUCT.md [#223](https://github.com/LibreSign/libresign/pull/223) @vitormattos
- Signature validate [#221](https://github.com/LibreSign/libresign/pull/221) @raw-vitor
- File tab [#216](https://github.com/LibreSign/libresign/pull/216) @vinicios-gomes
- Improvements in validate file_id [#215](https://github.com/LibreSign/libresign/pull/215) @vitormattos
- translate text [#214](https://github.com/LibreSign/libresign/pull/214) @vinicios-gomes
- Change property name [#213](https://github.com/LibreSign/libresign/pull/213) @vitormattos
- Sign using nodeid [#212](https://github.com/LibreSign/libresign/pull/212) @vitormattos
- LibreSign signature validation [#206](https://github.com/LibreSign/libresign/pull/206) @vitormattos
- default placeholder url  [#204](https://github.com/LibreSign/libresign/pull/204) @raw-vitor
- merge main in signature validate [#203](https://github.com/LibreSign/libresign/pull/203) @vinicios-gomes
- l10n: Add a dot and an ellipsis [#202](https://github.com/LibreSign/libresign/pull/202) @Valdnet
- Return status signed [#200](https://github.com/LibreSign/libresign/pull/200) @vitormattos
- Disable create account button when submitting form [#198](https://github.com/LibreSign/libresign/pull/198) @raw-vitor
- Only use validate page if is defined [#197](https://github.com/LibreSign/libresign/pull/197) @vitormattos
- Force email to lowercase [#196](https://github.com/LibreSign/libresign/pull/196) @vitormattos
- disable-btn [#194](https://github.com/LibreSign/libresign/pull/194) @raw-vitor
- translations [#193](https://github.com/LibreSign/libresign/pull/193) @vitormattos
- Validates a PDF. Triggers error if invalid. [#186](https://github.com/LibreSign/libresign/pull/186) @vitormattos
- url validation field [#183](https://github.com/LibreSign/libresign/pull/183) @raw-vitor
- Remove simplify changelog [#181](https://github.com/LibreSign/libresign/pull/181) @vitormattos
- Test matrix [#177](https://github.com/LibreSign/libresign/pull/177) @vitormattos
- Add app:check-code [#176](https://github.com/LibreSign/libresign/pull/176) @vitormattos
- Make info.xml compatible with xml schema [#175](https://github.com/LibreSign/libresign/pull/175) @vitormattos

### Bugfix

- Fix mistake in WebhookService::getFileUser() [#252](https://github.com/LibreSign/libresign/pull/252) @eneiluj
- Fix message in the home page app [#247](https://github.com/LibreSign/libresign/pull/247) @vinicios-gomes
- Fix extraneous-import error [#229](https://github.com/LibreSign/libresign/pull/229) @vinicios-gomes
- Bugfix generate password [#227](https://github.com/LibreSign/libresign/pull/227) @vitormattos
- Improvements and bugfix [#199](https://github.com/LibreSign/libresign/pull/199) @vitormattos
- Fix transifex setting [#190](https://github.com/LibreSign/libresign/pull/190) @vitormattos
- Fix get config [#187](https://github.com/LibreSign/libresign/pull/187) @vitormattos
- Fix property name [#185](https://github.com/LibreSign/libresign/pull/185) @vitormattos
- Bugfix: concatenate [#184](https://github.com/LibreSign/libresign/pull/184) @vitormattos
- Help to cs:fix when fail [#179](https://github.com/LibreSign/libresign/pull/179) @vitormattos
- Fix fatal error on run in cron [#174](https://github.com/LibreSign/libresign/pull/174) @vitormattos
- Prevent warning [#182](https://github.com/LibreSign/libresign/pull/182) @vitormattos

## 2.2.0 - 2021-04-12

### Changed

- l10n: Add an apostrophe [#134](https://github.com/LibreSign/libresign/pull/134) @Valdnet
- Move settings to specific menu [#164](https://github.com/LibreSign/libresign/pull/164) @vitormattos
- Add callback url in examble of documentation [#160](https://github.com/LibreSign/libresign/pull/160) @vitormattos
- l10n: Change case of letter [#145](https://github.com/LibreSign/libresign/pull/145) @Valdnet
- Update info.xml [#126](https://github.com/LibreSign/libresign/pull/126) @vitormattos
- Update info xml [#128](https://github.com/LibreSign/libresign/pull/128) @vitormattos
- Custom validation site [#129](https://github.com/LibreSign/libresign/pull/129) @vitormattos
- l10n: Change to singular [#132](https://github.com/LibreSign/libresign/pull/132) @Valdnet
- l10n: Correct text string for login [#133](https://github.com/LibreSign/libresign/pull/133) @Valdnet
- l10n: Change to uppercase [#135](https://github.com/LibreSign/libresign/pull/135) @Valdnet
- Change text [#136](https://github.com/LibreSign/libresign/pull/136) @vitormattos
- l10n: Change order [#139](https://github.com/LibreSign/libresign/pull/139) @Valdnet
- l10n: Shorten message [#141](https://github.com/LibreSign/libresign/pull/141) @Valdnet
- l10n: Replace with adjective [#142](https://github.com/LibreSign/libresign/pull/142) @Valdnet
- l10n: Change to uppercase URI [#143](https://github.com/LibreSign/libresign/pull/143) @Valdnet
- Replace collection by list [#147](https://github.com/LibreSign/libresign/pull/147) @vitormattos
- Make validate endpoint public [#163](https://github.com/LibreSign/libresign/pull/163) @vitormattos
- l10n: Change message [#144] [#148](https://github.com/LibreSign/libresign/pull/148) @Valdnet
- l10n: Change error message of file [#150](https://github.com/LibreSign/libresign/pull/150) @Valdnet
- Api documentation [#152](https://github.com/LibreSign/libresign/pull/152) @vitormattos
- Improvement in text [#157](https://github.com/LibreSign/libresign/pull/157) @vitormattos
- Validate by UUID [#161](https://github.com/LibreSign/libresign/pull/161) @vitormattos
- bump setup php [#162](https://github.com/LibreSign/libresign/pull/162) @vitormattos
- Create user design color [#98](https://github.com/LibreSign/libresign/pull/98) @raw-vitor

### Fixed

- Change var name and fix translation [#149](https://github.com/LibreSign/libresign/pull/149) @vitormattos
- Fix definition [#151](https://github.com/LibreSign/libresign/pull/151) @vitormattos
- Fix text [#153](https://github.com/LibreSign/libresign/pull/153) @vitormattos
- Fix text [#154](https://github.com/LibreSign/libresign/pull/154) @vitormattos
- Fix text [#155](https://github.com/LibreSign/libresign/pull/155) @vitormattos
- Fix text [#156](https://github.com/LibreSign/libresign/pull/156) @vitormattos

## 2.1.2 - 2021-03-21

### Changed

- Changelog [#125](https://github.com/LibreSign/libresign/pull/125) @vitormattos
- Bump release [#117](https://github.com/LibreSign/libresign/pull/117) @vitormattos
- Backend translations [#116](https://github.com/LibreSign/libresign/pull/116) @vitormattos
- Review frontend translations [#115](https://github.com/LibreSign/libresign/pull/115) @vitormattos
- Only include line if necessary [#124](https://github.com/LibreSign/libresign/pull/124) @vitormattos
- Instructions to create cfssl folder [#123](https://github.com/LibreSign/libresign/pull/123) @vitormattos
- Update README.md [#122](https://github.com/LibreSign/libresign/pull/122) @vitormattos

### Fixed

- Fix transifex config [#119](https://github.com/LibreSign/libresign/pull/119) @vitormattos
- Fix langmap config [#118](https://github.com/LibreSign/libresign/pull/118) @MorrisJobke

## 2.0.5 - 2021-03-11

- Changelog [#114](https://github.com/LibreSign/libresign/pull/114) @vitormattos
- Add health check [#113](https://github.com/LibreSign/libresign/pull/113) @vitormattos
- Remove unused var [#111](https://github.com/LibreSign/libresign/pull/111) @vitormattos
- Catch error [#112](https://github.com/LibreSign/libresign/pull/112) @vitormattos
- Rename property [#110](https://github.com/LibreSign/libresign/pull/110) @vitormattos
- Add route me [#109](https://github.com/LibreSign/libresign/pull/109) @vitormattos
- Bump version [#108](https://github.com/LibreSign/libresign/pull/108) @vitormattos
- Bump packages [#107](https://github.com/LibreSign/libresign/pull/107) @vitormattos
- Update changelog [#103](https://github.com/LibreSign/libresign/pull/103) @vitormattos

## 2.0.4 - 2021-03-09

### Changed

- Add category [#106](https://github.com/LibreSign/libresign/pull/106) @vitormattos
- Fix app name, description and summary [#105](https://github.com/LibreSign/libresign/pull/105) @vitormattos

## 2.0.1 - 2021-03-08
- Makefile and change dependency repository [#102](https://github.com/LibreSign/libresign/pull/102) @vitormattos
- Clean package.json [#101](https://github.com/LibreSign/libresign/pull/101) @vitormattos

### Changed

- Feature publish app [#100](https://github.com/LibreSign/libresign/pull/100) @vitormattos
- Fix package size [#99](https://github.com/LibreSign/libresign/pull/99) @vitormattos
- Add automate generate changelog [#97](https://github.com/LibreSign/libresign/pull/97) @vitormattos
- Changelog workflow [#96](https://github.com/LibreSign/libresign/pull/96) @vitormattos
- Changelog workflow [#95](https://github.com/LibreSign/libresign/pull/95) @vitormattos
- External route [#22](https://github.com/LibreSign/libresign/pull/22) @vinicios-gomes

## 2.0.0 - 2021-02-25

### Added

- Add CSP [#91](https://github.com/LibreSign/libresign/pull/91) @vitormattos
- Inserting data in the store [#90](https://github.com/LibreSign/libresign/pull/90) @vinicios-gomes
- Send mail when change sign request [#89](https://github.com/LibreSign/libresign/pull/89) @vitormattos
- Feature update improvements [#86](https://github.com/LibreSign/libresign/pull/86) @vitormattos
- Route to get PDF [#85](https://github.com/LibreSign/libresign/pull/85) @vitormattos
- Redux Struture for persistence of data [#83](https://github.com/LibreSign/libresign/pull/83) @vinicios-gomes
- Validate user [#82](https://github.com/LibreSign/libresign/pull/82) @vitormattos
- Cancel sign notification [#81](https://github.com/LibreSign/libresign/pull/81) @vitormattos
- Delete sign request [#80](https://github.com/LibreSign/libresign/pull/80) @vitormattos
- Success default page [#79](https://github.com/LibreSign/libresign/pull/79) @vinicios-gomes
- Show error when user already signed the file [#77](https://github.com/LibreSign/libresign/pull/77) @vitormattos
- error handler [#76](https://github.com/LibreSign/libresign/pull/76) @vitormattos
- patch sign request and move description to relation beetwen user and file [#74](https://github.com/LibreSign/libresign/pull/74) @vitormattos
- Now it is possible to view the pdf [#69](https://github.com/LibreSign/libresign/pull/69) @vinicios-gomes
- Disable button if sign sucess, show toast error if has error message [#68](https://github.com/LibreSign/libresign/pull/68) @vinicios-gomes
- Translated texts and change in the error toast message. [#67](https://github.com/LibreSign/libresign/pull/67) @vinicios-gomes
- Improvement on error handling [#66](https://github.com/LibreSign/libresign/pull/66) @vitormattos
- Pass error message through route props [#62](https://github.com/LibreSign/libresign/pull/62) @vinicios-gomes
- Bump max nextloud version [#59](https://github.com/LibreSign/libresign/pull/59) @vitormattos
- Documentation [#57](https://github.com/LibreSign/libresign/pull/57) @vitormattos
- Feature notify callback [#56](https://github.com/LibreSign/libresign/pull/56) @vitormattos
- Feature add footer [#55](https://github.com/LibreSign/libresign/pull/55) @vitormattos
- Test libraries [#54](https://github.com/LibreSign/libresign/pull/54) @vinicios-gomes
- Route sign document [#51](https://github.com/LibreSign/libresign/pull/51) @vinicios-gomes
- Interaction with the api [#50](https://github.com/LibreSign/libresign/pull/50) @vinicios-gomes
- Rename field [#48](https://github.com/LibreSign/libresign/pull/48) @vitormattos
- Ident [#47](https://github.com/LibreSign/libresign/pull/47) @vitormattos
- Refactor rename field [#45](https://github.com/LibreSign/libresign/pull/45) @vitormattos
- Feature sign using uuuid [#41](https://github.com/LibreSign/libresign/pull/41) @vitormattos
- Return data to sign after create user [#40](https://github.com/LibreSign/libresign/pull/40) @vitormattos
- Feature account create [#39](https://github.com/LibreSign/libresign/pull/39) @vitormattos
- Feature send email [#35](https://github.com/LibreSign/libresign/pull/35) @vitormattos
- External route create user [#33](https://github.com/LibreSign/libresign/pull/33) @raw-vitor
- Webhook config front [#32](https://github.com/LibreSign/libresign/pull/32) @vinicios-gomes
- Feature add webhook [#30](https://github.com/LibreSign/libresign/pull/30) @vitormattos
- Translate [#28](https://github.com/LibreSign/libresign/pull/28) @vinicios-gomes
- External route [#27](https://github.com/LibreSign/libresign/pull/27) @vitormattos
- Mock config [#23](https://github.com/LibreSign/libresign/pull/23) @vitormattos
- Secutiry Policy for acessing data on an external route. [#21](https://github.com/LibreSign/libresign/pull/21) @vinicios-gomes
- Add badge and ajust position of itens in composer.json [#20](https://github.com/LibreSign/libresign/pull/20) @vitormattos
- Refactor bump package [#19](https://github.com/LibreSign/libresign/pull/19) @vitormattos
- Add php-cs check [#18](https://github.com/LibreSign/libresign/pull/18) @vitormattos
- GitHub actions [#17](https://github.com/LibreSign/libresign/pull/17) @vitormattos
- Change to jsignpdf without java [#16](https://github.com/LibreSign/libresign/pull/16) @vitormattos
- Add blank page [#15](https://github.com/LibreSign/libresign/pull/15) @vitormattos
- php_cs [#13](https://github.com/LibreSign/libresign/pull/13) @vitormattos
- Refactor [#11](https://github.com/LibreSign/libresign/pull/11) @vitormattos

### Fixed

- Fix Did not access the subscription page after creating the user and … [#92](https://github.com/LibreSign/libresign/pull/92) @vinicios-gomes
- Fix widthh description [#88](https://github.com/LibreSign/libresign/pull/88) @vinicios-gomes
- fix validate user [#84](https://github.com/LibreSign/libresign/pull/84) @vitormattos
- Fix invalid route [#63](https://github.com/LibreSign/libresign/pull/63) @vitormattos
- Fix much bugs [#49](https://github.com/LibreSign/libresign/pull/49) @vitormattos
- Fix admin permissions in routes. [#14](https://github.com/LibreSign/libresign/pull/14) @vitormattos
