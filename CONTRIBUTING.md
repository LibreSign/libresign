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

If you would prefer to write code, explore the
[Good First Issues](https://github.com/search?q=org%3ALibreSign+is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22&type=issues)
across the LibreSign organization.
For larger product initiatives and current development direction, see the [LibreSign Roadmap](https://github.com/orgs/LibreSign/projects/2).

### Working on an issue

A `good first issue` may still be blocked or have ongoing work. Check its
dependencies, comments and related pull requests before starting. We recommend
leaving a comment to coordinate with maintainers, but this is not required.
See the [development workflow](https://docs.libresign.coop/developer_manual/development-workflow.html)
for details on readiness, blockers and alternative tasks.

For larger features or refactors, please align the proposed approach with a maintainer before investing significant work.

LibreSign contributions are voluntary by default. Issues and pull requests do not imply financial compensation unless the project explicitly states otherwise. If you or your organization would like to fund the development of a feature, contact us at `contact@libresign.coop`.

### Pull requests

Draft pull requests are considered work in progress and normally do not receive a full code review. Mark the pull request as **Ready for review** when you want the review process to begin.

For contributions from new contributors, maintainers may inspect the changes before authorizing CI workflows to run. Once CI has been authorized, please review failures caused by your changes before requesting another review.

AI-assisted contributions are welcome. You may use AI for any part of the contribution process, but you remain responsible for everything you submit. The same quality and review standards apply regardless of how the contribution was produced.

When updating your branch with changes from `main`, rebasing is preferred when practical because it avoids unnecessary merge commits and keeps the pull request history easier to review.

### Front and backend development environment

More information [here](https://docs.libresign.coop/developer_manual/getting-started/development-environment/setup), at the [official documentation](https://docs.libresign.coop/developer_manual/getting-started/development-environment/setup)

### Classifying OpenAPI endpoints

Documented HTTP operations use a functional OpenAPI tag, not a controller-name tag. Add or update a method-level `#[OpenAPI(tags: ['...'])]` attribute on the controller action. If the method already has an explicit `scope` (for example `SCOPE_ADMINISTRATION` or `SCOPE_IGNORE`), keep that scope and add the tag on the same attribute.

Approved tags: `appearance`, `certificates`, `documents`, `identity`, `notifications`, `policies`, `requests`, `signing`, `system`, `verification`.

Classify by what the operation does:

- `requests`: create, update, cancel, or inspect signature requests
- `signing`: sign, reject, renew, or request a signing code
- `documents`: store, list, attach, delete, or thumbnail files
- `verification`: validate a document or certificate status
- `identity`: account, signer identity, or identification documents
- `appearance`: visible elements, stamps, footer, or signature backgrounds
- `certificates`: certificate engines, policies, CRL publication, or revocation
- `policies`: policy reads and writes
- `notifications`: signer or in-app notification delivery
- `system`: instance setup or infrastructure configuration

Do not invent a new tag from the controller class name. After changing attributes, regenerate with `composer run openapi` and `npm run typescript:generate`, then run `composer openapi:check-tags`. Do not hand-edit generated OpenAPI JSON or TypeScript.

After your first contribution
-----------------------------

When your first pull request is merged, a bot leaves a short note on it to say
thank you and to point you at what to look at next. It is triggered by the
first pull request of yours that is actually **merged**, so an earlier one that
was closed without being accepted does not use it up.

That note may include a link to a short contributor survey. Answering it is
entirely optional and nothing about your contribution depends on it — we ask
because the people best placed to tell us where the first contribution is
harder than it should be are the ones who have just been through it.

You are, of course, welcome to contribute again without any of that.

Contributor feedback
--------------------

Have you contributed, or tried to contribute, to LibreSign? We would like to
hear about your experience, whether or not your work was merged — the setup
step or the issue description that stopped you is the one we most need to hear
about, and the post-merge note never reaches you.

[Contributor experience survey][contributor-survey]

<!-- The post-merge workflow reads its complete message from the
     FIRST_MERGED_PR_MESSAGE repository Actions variable. Markdown cannot read
     repository variables, so the survey URL is written out once here. Keep the
     source and repository query parameters aligned with the post-merge survey
     context. -->

[contributor-survey]: https://ls.librecode.coop/index.php/645932?lang=en&source=contributing-guide&repository=libresign
