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

## 11.6.0 - 2025-11-28
### Features
- feat: implement TSA [#5583](https://github.com/LibreSign/libresign/pull/5583)
- feat: display more informatin about certificate [#5589](https://github.com/LibreSign/libresign/pull/5589)
- feat: implement serial number with random number [#5594](https://github.com/LibreSign/libresign/pull/5594)
- feat: implement crl [#5629](https://github.com/LibreSign/libresign/pull/5629)
- Add support message and button [#5639](https://github.com/LibreSign/libresign/pull/5639)
- feat: implement aki and ski [#5611](https://github.com/LibreSign/libresign/pull/5611)

### Changes
- Update translations
- Bump dependencies
- chore: valdiate display name at API side [#5563](https://github.com/LibreSign/libresign/pull/5563)
- chore: Refactor certificate chain processing with ordering [#5585](https://github.com/LibreSign/libresign/pull/5585)
- refactor: separate CA and leaf certificate configuration in OpenSSL e… [#5603](https://github.com/LibreSign/libresign/pull/5603)
- chore: remov eunecessary comment [#5610](https://github.com/LibreSign/libresign/pull/5610)
- chore: improve UX at sign screen [#5631](https://github.com/LibreSign/libresign/pull/5631)
- chore: improve error handler about Imagick [#5635](https://github.com/LibreSign/libresign/pull/5635)
- chore: remove to-do [#5646](https://github.com/LibreSign/libresign/pull/5646)

### Fixes
- fix: update to newest version of eslint [#5571](https://github.com/LibreSign/libresign/pull/5571)
- fix: disable ocp at behat tests [#5580](https://github.com/LibreSign/libresign/pull/5580)
- fix(i18n): Fixed grammar [#5592](https://github.com/LibreSign/libresign/pull/5592)
- fix: replace keyCertSign with nonRepudiation in leaf certificate keyUsage [#5599](https://github.com/LibreSign/libresign/pull/5599)
- fix: use sha256 insteadof sha1 to leaf cert [#5607](https://github.com/LibreSign/libresign/pull/5607)
- fix: prevent warning when send notifications [#5618](https://github.com/LibreSign/libresign/pull/5618)
- fix: use only classes compatible with old Nextcloud server versions [#5621](https://github.com/LibreSign/libresign/pull/5621)
- fix: prevent error when send reminders [#5638](https://github.com/LibreSign/libresign/pull/5638)
- fix: Only accept pfx files. [#5643](https://github.com/LibreSign/libresign/pull/5643)

## 11.5.1 - 2025-11-13
### Fixes
- fix: prevent error when the response dont have data [#5553](https://github.com/LibreSign/libresign/pull/5553)
- fix: workaround to make compatible with different structures [#5555](https://github.com/LibreSign/libresign/pull/5555)

## 11.5.0 - 2025-11-10
### Features
- feat(dependabot): add missing composer paths to config [#5466](https://github.com/LibreSign/libresign/pull/5466)
- feat: sign usign twofactor_gateway [#5498](https://github.com/LibreSign/libresign/pull/5498)
- feat: return next scheduled date [#5526](https://github.com/LibreSign/libresign/pull/5526)

### Changes
- Update translations
- Bump dependencies
- chore: add link to logs [#5456](https://github.com/LibreSign/libresign/pull/5456)
- chore: gridViewButtonLabel [#5462](https://github.com/LibreSign/libresign/pull/5462)
- chore: update workflows [#5485](https://github.com/LibreSign/libresign/pull/5485)
- chore: replace vendor by 3rdparty [#5514](https://github.com/LibreSign/libresign/pull/5514)
- chore: cover with more scenarios [#5525](https://github.com/LibreSign/libresign/pull/5525)
- chore: handle error and cover with tests [#5536](https://github.com/LibreSign/libresign/pull/5536)

### Fixes
- fix: error at CI with PHP 8.3 [#5481](https://github.com/LibreSign/libresign/pull/5481)
- fix: rollback previous commit [#5484](https://github.com/LibreSign/libresign/pull/5484)
- fix: isolate all dependencies [#5490](https://github.com/LibreSign/libresign/pull/5490)
- fix: patcher for mpdf [#5495](https://github.com/LibreSign/libresign/pull/5495)
- fix: apply rector [#5501](https://github.com/LibreSign/libresign/pull/5501)
- fix: unit test after translation update [#5516](https://github.com/LibreSign/libresign/pull/5516)
- fix: unit test after implement submodule [#5521](https://github.com/LibreSign/libresign/pull/5521)
- fix: error handler to prevent JS error when receive 4xx from API [#5531](https://github.com/LibreSign/libresign/pull/5531)
- fix: make possible to test with dates [#5538](https://github.com/LibreSign/libresign/pull/5538)
- fix: add maxlength [#5542](https://github.com/LibreSign/libresign/pull/5542)
- fix: make the error message more specific [#5543](https://github.com/LibreSign/libresign/pull/5543)

## 11.4.1 - 2025-09-13
### Changes
- Update translations
- Bump dependencies

### Fixes
- fix: typo [#5443](https://github.com/LibreSign/libresign/pull/5443)

## 11.4.0 - 2025-09-12
### Features
- feat: implement reminders to signers [#5434](https://github.com/LibreSign/libresign/pull/5434)

### Changes
- Update translations
- chore: add log to make possible debug issues at certificate chain [#5411](https://github.com/LibreSign/libresign/pull/5411)

### Fixes
- fix: isolate PHP-pdftk dependency [#5413](https://github.com/LibreSign/libresign/pull/5413)
- fix: prevent error when try to create a folder two times [#5421](https://github.com/LibreSign/libresign/pull/5421)
- fix: set TZ=UTC for pdfsig [#5427](https://github.com/LibreSign/libresign/pull/5427)
- fix: use utc as timezone when read data from signed document [#5430](https://github.com/LibreSign/libresign/pull/5430)

## 11.3.2 - 2025-09-02
### Changes
- Update translations
- Bump dependencies
- chore: update contributing [#5395](https://github.com/LibreSign/libresign/pull/5395)

### Fixes
- fix: timezone of preview signature stamp [#5387](https://github.com/LibreSign/libresign/pull/5387)
- fix: propagate timezone [#5393](https://github.com/LibreSign/libresign/pull/5393)
- fix: use UTC into all dates [#5398](https://github.com/LibreSign/libresign/pull/5398)
- fix: set default value to initial state [#5401](https://github.com/LibreSign/libresign/pull/5401)

## 11.3.1 - 2025-09-01
### Changes
- Update translations
- Bump dependencies
- chore: add log to track McFly [#5355](https://github.com/LibreSign/libresign/pull/5355)
- Docs/add GitHub codespaces steps to pr template [#5361](https://github.com/LibreSign/libresign/pull/5361)
- chore: convert the date object to json [#5367](https://github.com/LibreSign/libresign/pull/5367)
- chore: Improvements at response from API when generate root certificate [#5375](https://github.com/LibreSign/libresign/pull/5375)

### Fixes
- fix: remove condition that restricts LibreSign tab to LibreSign files [#5374](https://github.com/LibreSign/libresign/pull/5374)
- fix: open file at app files [#5372](https://github.com/LibreSign/libresign/pull/5372)

## 11.3.0 - 2025-08-21
### Features
- feat:button open file by [#5240](https://github.com/LibreSign/libresign/pull/5240)

### Changes
- Update translations
- Bump dependencies
- chore: cover the sign method using pkcs7 engine [#5235](https://github.com/LibreSign/libresign/pull/5235)
- chore: cover the sign method using pkcs7 engine [#5235](https://github.com/LibreSign/libresign/pull/5235)
- chore: Replace getById getFirstNodeById [#5244](https://github.com/LibreSign/libresign/pull/5244)
- chore: update behat [#5255](https://github.com/LibreSign/libresign/pull/5255)
- chore: update workflows [#5282](https://github.com/LibreSign/libresign/pull/5282)
- chore: remove unecessary check if node exists [#5339](https://github.com/LibreSign/libresign/pull/5339)
- refactor: replace deprecated PHPUnit returnValue() with willReturn() [#5247](https://github.com/LibreSign/libresign/pull/5247)
- Replace deprecated PHPUnit methods [#5301](https://github.com/LibreSign/libresign/pull/5301)

### Fixes
- fix: indent using taps [#5228](https://github.com/LibreSign/libresign/pull/5228)
- fix: remove duplicated step [#5233](https://github.com/LibreSign/libresign/pull/5233)
- fix: store the date that the file was signed [#5279](https://github.com/LibreSign/libresign/pull/5279)
- fix: email token with camel case email [#5310](https://github.com/LibreSign/libresign/pull/5310)
- fix: configure check with poppler [#5318](https://github.com/LibreSign/libresign/pull/5318)
- fix: always return the owner of the file to be signed [#5325](https://github.com/LibreSign/libresign/pull/5325)
- fix: prevent show add signers early [#5334](https://github.com/LibreSign/libresign/pull/5334)

## 11.2.5 - 2025-07-21
### Changes
- Update translations
- Bump dependencies
- chore: cover scenario of two accoutns with same email [#5209](https://github.com/LibreSign/libresign/pull/5209)

### Fixes
- fix: display draw signature in full mode [#5203](https://github.com/LibreSign/libresign/pull/5203)
- fix: validate file answer [#5181](https://github.com/LibreSign/libresign/pull/5181)
- fix: replace heredoc by string concat [#5176](https://github.com/LibreSign/libresign/pull/5176)
- fix: ignore vendor bin at transifex sync [#5175](https://github.com/LibreSign/libresign/pull/5175)

## 11.2.4 - 2025-06-16
### Changes
- Update translations
- Bump dependencies
- docs: add donation link to appear on Nextcloud appstore [#5153](https://github.com/LibreSign/libresign/pull/5153)
- chore: edit visible signatures [#5151](https://github.com/LibreSign/libresign/pull/5151)
- chore: start to move methods to Helper class [#5142](https://github.com/LibreSign/libresign/pull/5142)
- docs: add Star History image at README.md file [#5131](https://github.com/LibreSign/libresign/pull/5131)
- chore: use method that get app config [#5109](https://github.com/LibreSign/libresign/pull/5109)
- chore: remove unused var reported by Rector [#5108](https://github.com/LibreSign/libresign/pull/5108)
- chore: test set visible elements [#5103](https://github.com/LibreSign/libresign/pull/5103)
- chore: implement unit tests at visible elements class [#5093](https://github.com/LibreSign/libresign/pull/5093)
- chore: improve Release Drafter config with categories, changelog temp… [#5082](https://github.com/LibreSign/libresign/pull/5082)
- chore: Convert bug_report.md and feature_request.md to yaml [#5071](https://github.com/LibreSign/libresign/pull/5071)

### Fixes
- fix: link in README.md to feature_request.yml form [#5078](https://github.com/LibreSign/libresign/pull/5078)
- fix: change display name [#5155](https://github.com/LibreSign/libresign/pull/5155)
- fix: change lang environment when is possible [#5149](https://github.com/LibreSign/libresign/pull/5149)
- fix: implemented debounce on signer search field [#5132](https://github.com/LibreSign/libresign/pull/5132)

## 11.2.3 - 2025-05-28
### Changes
- chore: improve tip to sysadmin [#5055](https://github.com/LibreSign/libresign/pull/5055)

### Fixes
- fix: prevent error when check binaries of JSignPdf [#5066](https://github.com/LibreSign/libresign/pull/5066)
- fix: prevent warning when install first time [#5064](https://github.com/LibreSign/libresign/pull/5064)

## 11.2.2 - 2025-05-26
### Changes
- chore: add more ways to get signer email [#5048](https://github.com/LibreSign/libresign/pull/5048)

### Fixes
- fix: fallback when system haven't a TTF font [#5045](https://github.com/LibreSign/libresign/pull/5045)
- fix: show 'Dismiss notification' button on signed file notification [#5042](https://github.com/LibreSign/libresign/pull/5042)

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

## 11.1.2 - 2025-04-14
### Changes
- Update translations

### Fixes
- fix: user userId when validate file [#4857](https://github.com/LibreSign/libresign/pull/4857)
- fix: only notify when is not draft [#4855](https://github.com/LibreSign/libresign/pull/4855)
- fix: prevent json decode null [#4852](https://github.com/LibreSign/libresign/pull/4852)

## 11.1.1 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4841](https://github.com/LibreSign/libresign/pull/4841)
- fix typo: descriptin -> description [#4837](https://github.com/LibreSign/libresign/pull/4837)

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

## 11.0.4 - 2025-03-20
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

## 11.0.0 - 2025-01-27
### Feature
- Say hello to Nexcloud 31
