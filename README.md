![Test Status](https://github.com/libresign/libresign/workflows/PHPUnit/badge.svg?branch=main)
[![Coverage Status](https://coveralls.io/repos/github/LibreSign/libresign/badge.svg?branch=main)](https://coveralls.io/github/LibreSign/libresign?branch=main)
[![Start contributing](https://img.shields.io/github/issues/LibreSign/libresign/good%20first%20issue?color=7057ff&label=Contribute)](https://github.com/LibreSign/libresign/issues?q=is%3Aissue+is%3Aopen+sort%3Aupdated-desc+label%3A%22good+first+issue%22)

Nextcloud app to sign PDF documents.

<img src="img/LibreSign.png" />

**Table of contents**
- [Setup](#setup)
  - [Check setup](#check-setup)
- [Integrations](#integrations)
- [Full documentation](#full-documentation)
- [Contributing](#contributing)

## Setup

After installing LibreSign, is necessary go to `Administration Settings > LibreSign` and:
* Download binareis
* Configure root certificate

Go to `Administration Settings > Basic Settings` and configure email settings. Is mandatory.

### Check setup

Check install:
```bash
occ libresign:configure:check
```

## Integrations

* [GLPI](https://github.com/LibreSign/libresign-glpi): Plugin to sign GLPI tickets
* [Approval](https://github.com/nextcloud/approval): Approve/reject files based on workflows defined by admins

## Full documentation

[here](https://libresign.github.io/)

## Contributing

[here](/CONTRIBUTING.md)
