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
- Disable sign button when is loading [#3225](https://github.com/libresign/libresign/pull/3225)
- Bump dependencies

### Fixes
- signing dependencies at deploy to Nextcloud app store [#3234](https://github.com/libresign/libresign/pull/3234)
- Make possible use multiple signatures of same signer [#3229](https://github.com/libresign/libresign/pull/3229)
- neutralize deleted users [#3222](https://github.com/libresign/libresign/pull/3222)
- Bump dependencies

## 8.1.1 - 2024-06-26
### Changed
- Disable sign button when is loading [#3224](https://github.com/libresign/libresign/pull/3224)
- Bump dependencies

### Fixes
- signing dependencies at deploy to Nextcloud app store [#3233](https://github.com/libresign/libresign/pull/3233)
- Make possible use multiple signatures of same signer [#3228](https://github.com/libresign/libresign/pull/3228)
- neutralize deleted users [#3221](https://github.com/libresign/libresign/pull/3221)

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
* Prevent error when synchronize with windows
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
* Prevent error when synchronize with windows
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
