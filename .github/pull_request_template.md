Resolves: # <!-- related GitHub issue -->

## 📝 Summary

<!--
A concise description of what this PR does and why.
Keep the scope focused on the linked issue.
-->

## 🧪 How to test

<!--
Testing instructions should be specific to this PR and describe the shortest
reproducible path to validate the proposed changes.

Example:
1. Access the affected LibreSign screen or API.
2. Reproduce the behavior before the change when applicable.
3. Apply the steps required by this PR.
4. Verify the expected result.

Feel free to paste terminal commands, fixtures or URLs that help reviewers
follow along.

Generic development-environment setup belongs in the Developer Manual and
.devcontainer/README.md rather than being copied into every PR.
-->

## Validation evidence

<!--
List only checks that actually ran. Do not mark a check as completed when it
was not executed.
-->

- [ ] Focused regression/acceptance test
- [ ] Relevant lint/type/static-analysis checks
- [ ] Broader suite required by the changed surface
- [ ] CI reviewed

## 🎨 UI / Front-end changes

<!--
Feel free to remove this section when your PR only affects backend/API code.

Describe the visible changes below. For user-visible changes, screenshots are
review evidence: include before/after images or links whenever a meaningful
visual comparison is possible.
-->

- [ ] ... <!-- Describe the UI tasks performed here, e.g. layout adjustment or new feature -->
- [ ] Screenshots before/after added for user-visible changes

| 🏚️ Before | 🏡 After |
| --- | --- |
| Screenshot before | Screenshot after |

<!-- ☀️ Light theme | 🌑 Dark theme: test and document both when applicable. -->

- [ ] Tested in relevant browsers (Chrome, Firefox, Safari) when applicable
- [ ] Component/unit (Vitest) and/or E2E (Playwright) tests added or updated as appropriate
- [ ] Accessibility verified (contrast, keyboard navigation, screen reader) when applicable
- [ ] Design review approved when applicable <!-- Link to feedback/review -->
- [ ] Documentation updated when applicable <!-- https://github.com/LibreSign/documentation/ -->

### 🚧 Tasks

<!--
Add prerequisites that must be completed before this PR can be merged, such as
updating a dependency or merging another PR. Remove this block when there are
no prerequisites.
-->

- [ ] ...

## ⚙️ API / Back-end changes

<!--
Feel free to remove this section when your PR only affects frontend/UI code.
-->

- [ ] ... <!-- Describe the API/service/architecture changes here -->
- [ ] Unit and/or integration tests added or updated for backend behavior
- [ ] Authorization and negative paths checked when applicable
- [ ] Capabilities updated when applicable <!-- When adding/modifying Nextcloud capabilities -->
- [ ] Documentation updated when applicable <!-- https://github.com/LibreSign/documentation/ -->
- [ ] API documentation regenerated with `composer openapi` when necessary <!-- Generates openapi*.json -->

### 🚧 Tasks

<!--
Add prerequisites that must be completed before this PR can be merged. Remove
this block when there are no prerequisites.
-->

- [ ] ...

## ✅ Checklist

- [ ] I have read and followed the [contribution guide](../CONTRIBUTING.md).
- [ ] The diff does not contain unrelated refactors or generated changes.
- [ ] Commits follow Conventional Commits where applicable.
- [ ] All commits include the required DCO sign-off.

## 🤖 AI (if applicable)

<!--
AI-assisted contributions have the same review, testing and responsibility
requirements as any other contribution.
-->

- [ ] The content of this PR was partially or fully generated using AI
