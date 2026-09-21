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

## 9.9.6 - 2025-04-12
### Fixes
- fix: only load backup if exists [#4842](https://github.com/LibreSign/libresign/pull/4842)

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

## 9.9.4 - 2025-03-20
### Changes
- Update translations
- Bump dependencies

## 9.9.3 - 2025-02-28
### Changes
- Update translations
- Bump dependencies

### Fixes
- fix: restrict access to validation endpoints [#4702](https://github.com/LibreSign/libresign/pull/4702)
- fix: add information note about visible signature [#4686](https://github.com/LibreSign/libresign/pull/4686)

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

## 9.8.3 - 2025-01-19
### Changes
- chore: prevent generate unecessary temp file [#4392](https://github.com/LibreSign/libresign/pull/4392)
- chore: bump dependencies [#4381](https://github.com/LibreSign/libresign/pull/4381)

### fixes
- fix: view pdf at validation page [#4390](https://github.com/LibreSign/libresign/pull/4390)
- fix: list files with deleted signer [#4384](https://github.com/LibreSign/libresign/pull/4384)

## 9.8.2 - 2025-01-16
### fixes
- fix: handle settings after backend upgrade [#4371](https://github.com/LibreSign/libresign/pull/4371)
- fix: prevent error when is empty files [#4370](https://github.com/LibreSign/libresign/pull/4370)
- fix: validation url [#4363](https://github.com/LibreSign/libresign/pull/4363)
- fix: prevent error when get timeout from api [#4358](https://github.com/LibreSign/libresign/pull/4358)
- fix: close dialog after submit [#4355](https://github.com/LibreSign/libresign/pull/4355)

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

## 9.7.3 - 2024-11-30
### Fixes
* fix: prevent error when using PostgreSQL [4081](https://github.com/LibreSign/libresign/pull/4081)

### Changes
* Update translations

## 9.7.2 - 2024-11-29
### Fixes
* fix: list files from PostgreSQL [4075](https://github.com/LibreSign/libresign/pull/4075)

### Changes
* Update translations

## 9.7.1 - 2024-11-26
### Fixes
* fix: Icon color att app files [4057](https://github.com/LibreSign/libresign/pull/4057)
* fix: prevent error when click at signer to add to document [4055](https://github.com/LibreSign/libresign/pull/4055)
* fix: toggle loading [4053](https://github.com/LibreSign/libresign/pull/4053)
* fix: prevent don't delete file when folder is deleted [4060](https://github.com/LibreSign/libresign/pull/4060)

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

## 9.6.0 - 2024-11-20
### Fixes
* fix: retrieve file when request to sign from file list [3997](https://github.com/LibreSign/libresign/pull/3997)

### Changes
* feat: make possible choose the page [3988](https://github.com/LibreSign/libresign/pull/3988)
* Update translations

### Chore
* chore: bump dependencies at PHP and JS side
* chore: refresh file list every when load view [4000](https://github.com/LibreSign/libresign/pull/4000)

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

## 9.3.1 - 2024-09-10
### Fixes
* fix: check linux distro when get java path [3654](https://github.com/LibreSign/libresign/pull/3654)

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

## 9.2.2 - 2024-07-12
### Fixes
* fix: use linux distro when build [3367]https://github.com/LibreSign/libresign/pull/3367

## 9.2.1 - 2024-07-12
### Fixes
* fix: Java setup [3360]https://github.com/LibreSign/libresign/pull/3360
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

## 9.1.2 - 2024-06-28
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

## 9.0.2 - 2024-05-10
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

## 9.0.0 - 2024-04-24
### ✨Big changes to a new moment
* 📝 Allow you to sign documents without creating an account
* 🔒 Create root certificate with OpenSSL
* 📜 Possibility to send and sign with your own certificate
* 🛠️ Simplified setup
