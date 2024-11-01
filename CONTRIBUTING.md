<!--
 - SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
Contributing to LibreSign
=========================

Welcome
-------

We really appreciate everyone who would like to contribute to the LibreSign project!
There are many ways to contribute, including writing code, filing issues on GitHub,
helping people, assisting in triaging, reproducing, or fixing bugs that people have filed,
and enhancing our documentation. Also giving a star to the project is a really good way
to help and donate.

Before you get started, we encourage you to read our code of conduct, which describes our community norms:

1. [Our code of conduct](CODE_OF_CONDUCT.md), which explicitly stipulates that everyone must be gracious,
respectful, and professional. This also documents our conflict resolution policy and encourages people to ask questions.

Quality Assurance
-----------------

One of the most useful tasks, closely related to triage, is finding and filing bug reports.
Testing beta releases, looking for regressions, creating test cases, adding to our test suites,
and other work along these lines can significantly improve the quality of the product.
Creating tests that increase our test coverage and writing tests for issues others have filed are invaluable contributions to open source projects.

If this interests you, feel free to dive in and submit bug reports at any time!

> As a personal side note, this is exactly the kind of work that first got me into open
> source. I was a Quality Assurance volunteer on the Mozilla project, writing test cases for
> browsers, long before I wrote a line of code for any open source project. —Hixie


Developing for LibreSign
------------------------

**NOTE**: If the project does not have an issue for what you want to do, create an issue first.

If you would prefer to write code, you may wish to start with our list of good first issues for [LibreSign](https://github.com/LibreSign/libresign/issues?q=is%3Aopen+is%3Aissue+label%3A%22good+first+issue%22).
See the respective sections below for further instructions.

### Front and backend development environment

This project depends on the NextCloud project, so to start writing code, you will need to set it up.
We recommend using Docker for this, but feel free to use another method if you prefer. We suggest these two setups:

[Libre Code Coop Setup](https://github.com/LibreCodeCoop/nextcloud-docker-development/)<br>
[Julius Härtl Nextcloud Setup](https://github.com/juliushaertl/nextcloud-docker-dev)

**If you have any problems with these setups open an issue at corresponding to the project that you select to use.**

After executing these Docker setups, wait until it's possible to access localhost.
If access is not possible, go to your terminal, run the command docker ps,
and then find the "nextcloud" image or "ghcr.io/juliushaertl/nextcloud-dev-php**".
Access the address reported from the command output.
(If you cannot find the image, you likely encountered a problem running the Docker setup; please return to the previous step.)

To get LibreSign executing go to the folder of the setup that you choose and find the folder called `volumes/nextcloud/apps-extra` and clone the LibreSign repository.

Open bash in Nextcloud container with `docker compose exec -u www-data nextcloud bash`

Inside bash of Nextcloud go to the folder `apps-extra/libresign` and then run the commands:
  ```bash
  # Download composer dependencies
  composer install
  # Download JS dependencies
  npm ci
  # Build and watch JS changes
  npm run watch
  ```

To update API Documentation
---------------------------

After Configure the environment

After installing LibreSign, go to `Administration Settings > LibreSign` and:
* Click in the `Download binaries` button. When it show status `successful` to all items, except `root certificate not configured`, is time to configure root certificate in the next section.

[Repository of site and API documentation](https://github.com/libresign/libresign.github.io)
