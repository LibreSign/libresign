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

## Unreleased

### Changed

- [#102] Makefile and change dependency repository, Thanks to [@vitormattos]
- [#101] Clean package.json, Thanks to [@vitormattos]
- [#100] Feature publish app, Thanks to [@vitormattos]
- [#99] Fix package size, Thanks to [@vitormattos]
- [#97] Add automate generate changelog, Thanks to [@vitormattos]
- [#96] Changelog workflow, Thanks to [@vitormattos]
- [#95] Changelog workflow, Thanks to [@vitormattos]
- [#22] External route, Thanks to [@vinicios-gomes]

## [v2.0.0] - 2021-02-25

### Added

- [#91] Add CSP, Thanks to [@vitormattos]
- [#90] Inserting data in the store, Thanks to [@vinicios-gomes]
- [#89] Send mail when change sign request, Thanks to [@vitormattos]
- [#86] Feature update improvements, Thanks to [@vitormattos]
- [#85] Route to get PDF, Thanks to [@vitormattos]
- [#83] Redux Struture for persistence of data, Thanks to [@vinicios-gomes]
- [#82] Validate user, Thanks to [@vitormattos]
- [#81] Cancel sign notification, Thanks to [@vitormattos]
- [#80] Delete sign request, Thanks to [@vitormattos]
- [#79] Success default page, Thanks to [@vinicios-gomes]
- [#77] Show error when user already signed the file, Thanks to [@vitormattos]
- [#76] error handler, Thanks to [@vitormattos]
- [#74] patch sign request and move description to relation beetwen user and file, Thanks to [@vitormattos]
- [#69] Now it is possible to view the pdf, Thanks to [@vinicios-gomes]
- [#68] Disable button if sign sucess, show toast error if has error message, Thanks to [@vinicios-gomes]
- [#67] Translated texts and change in the error toast message., Thanks to [@vinicios-gomes]
- [#66] Improvement on error handling, Thanks to [@vitormattos]
- [#62] Pass error message through route props, Thanks to [@vinicios-gomes]
- [#59] Bump max nextloud version, Thanks to [@vitormattos]
- [#57] Documentation, Thanks to [@vitormattos]
- [#56] Feature notify callback, Thanks to [@vitormattos]
- [#55] Feature add footer, Thanks to [@vitormattos]
- [#54] Test libraries, Thanks to [@vinicios-gomes]
- [#51] Route sign document, Thanks to [@vinicios-gomes]
- [#50] Interaction with the api, Thanks to [@vinicios-gomes]
- [#48] Rename field, Thanks to [@vitormattos]
- [#47] Ident, Thanks to [@vitormattos]
- [#45] Refactor rename field, Thanks to [@vitormattos]
- [#41] Feature sign using uuuid, Thanks to [@vitormattos]
- [#40] Return data to sign after create user, Thanks to [@vitormattos]
- [#39] Feature account create, Thanks to [@vitormattos]
- [#35] Feature send email, Thanks to [@vitormattos]
- [#33] External route create user, Thanks to [@raw-vitor]
- [#32] Webhook config front, Thanks to [@vinicios-gomes]
- [#30] Feature add webhook, Thanks to [@vitormattos]
- [#28] Translate, Thanks to [@vinicios-gomes]
- [#27] External route, Thanks to [@vitormattos]
- [#23] Mock config, Thanks to [@vitormattos]
- [#21] Secutiry Policy for acessing data on an external route., Thanks to [@vinicios-gomes]
- [#20] Add badge and ajust position of itens in composer.json, Thanks to [@vitormattos]
- [#19] Refactor bump package, Thanks to [@vitormattos]
- [#18] Add php-cs check, Thanks to [@vitormattos]
- [#17] GitHub actions, Thanks to [@vitormattos]
- [#16] Change to jsignpdf without java, Thanks to [@vitormattos]
- [#15] Add blank page, Thanks to [@vitormattos]
- [#13] php_cs, Thanks to [@vitormattos]
- [#11] Refactor, Thanks to [@vitormattos]

### Fixed

- [#92] Fix Did not access the subscription page after creating the user and …, Thanks to [@vinicios-gomes]
- [#88] Fix widthh description, Thanks to [@vinicios-gomes]
- [#84] fix validate user, Thanks to [@vitormattos]
- [#63] Fix invalid route, Thanks to [@vitormattos]
- [#49] Fix much bugs, Thanks to [@vitormattos]
- [#14] Fix admin permissions in routes., Thanks to [@vitormattos]

[@vitormattos]: https://github.com/vitormattos
[@vinicios-gomes]: https://github.com/vinicios-gomes
[@raw-vitor]: https://github.com/raw-vitor
[#96]: https://github.com/LibreSign/libresign/pull/96
[#95]: https://github.com/LibreSign/libresign/pull/95
[#92]: https://github.com/LibreSign/libresign/pull/92
[#91]: https://github.com/LibreSign/libresign/pull/91
[#90]: https://github.com/LibreSign/libresign/pull/90
[#89]: https://github.com/LibreSign/libresign/pull/89
[#88]: https://github.com/LibreSign/libresign/pull/88
[#86]: https://github.com/LibreSign/libresign/pull/86
[#85]: https://github.com/LibreSign/libresign/pull/85
[#84]: https://github.com/LibreSign/libresign/pull/84
[#83]: https://github.com/LibreSign/libresign/pull/83
[#82]: https://github.com/LibreSign/libresign/pull/82
[#81]: https://github.com/LibreSign/libresign/pull/81
[#80]: https://github.com/LibreSign/libresign/pull/80
[#79]: https://github.com/LibreSign/libresign/pull/79
[#77]: https://github.com/LibreSign/libresign/pull/77
[#76]: https://github.com/LibreSign/libresign/pull/76
[#74]: https://github.com/LibreSign/libresign/pull/74
[#69]: https://github.com/LibreSign/libresign/pull/69
[#68]: https://github.com/LibreSign/libresign/pull/68
[#67]: https://github.com/LibreSign/libresign/pull/67
[#66]: https://github.com/LibreSign/libresign/pull/66
[#63]: https://github.com/LibreSign/libresign/pull/63
[#62]: https://github.com/LibreSign/libresign/pull/62
[#59]: https://github.com/LibreSign/libresign/pull/59
[#57]: https://github.com/LibreSign/libresign/pull/57
[#56]: https://github.com/LibreSign/libresign/pull/56
[#55]: https://github.com/LibreSign/libresign/pull/55
[#54]: https://github.com/LibreSign/libresign/pull/54
[#51]: https://github.com/LibreSign/libresign/pull/51
[#50]: https://github.com/LibreSign/libresign/pull/50
[#49]: https://github.com/LibreSign/libresign/pull/49
[#48]: https://github.com/LibreSign/libresign/pull/48
[#47]: https://github.com/LibreSign/libresign/pull/47
[#45]: https://github.com/LibreSign/libresign/pull/45
[#41]: https://github.com/LibreSign/libresign/pull/41
[#40]: https://github.com/LibreSign/libresign/pull/40
[#39]: https://github.com/LibreSign/libresign/pull/39
[#35]: https://github.com/LibreSign/libresign/pull/35
[#33]: https://github.com/LibreSign/libresign/pull/33
[#32]: https://github.com/LibreSign/libresign/pull/32
[#30]: https://github.com/LibreSign/libresign/pull/30
[#28]: https://github.com/LibreSign/libresign/pull/28
[#27]: https://github.com/LibreSign/libresign/pull/27
[#23]: https://github.com/LibreSign/libresign/pull/23
[#22]: https://github.com/LibreSign/libresign/pull/22
[#21]: https://github.com/LibreSign/libresign/pull/21
[#20]: https://github.com/LibreSign/libresign/pull/20
[#19]: https://github.com/LibreSign/libresign/pull/19
[#18]: https://github.com/LibreSign/libresign/pull/18
[#17]: https://github.com/LibreSign/libresign/pull/17
[#16]: https://github.com/LibreSign/libresign/pull/16
[#15]: https://github.com/LibreSign/libresign/pull/15
[#14]: https://github.com/LibreSign/libresign/pull/14
[#13]: https://github.com/LibreSign/libresign/pull/13
[#11]: https://github.com/LibreSign/libresign/pull/11
[#100]: https://github.com/LibreSign/libresign/pull/100
[#99]: https://github.com/LibreSign/libresign/pull/99
[#97]: https://github.com/LibreSign/libresign/pull/97
[#102]: https://github.com/LibreSign/libresign/pull/102
[#101]: https://github.com/LibreSign/libresign/pull/101
