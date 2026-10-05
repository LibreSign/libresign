Resolves: # <!-- related GitHub issue -->

## Summary

A concise description of what this PR does and why.

## How to test

Describe the shortest reproducible path to validate the changed behavior.

Example:
1. Access the affected LibreSign screen or API.
2. Reproduce the behavior before the change when applicable.
3. Apply the steps required by this PR.
4. Verify the expected result.

Include commands, fixtures, URLs, or setup details that a reviewer actually needs.
Generic development-environment setup belongs in the Developer Manual and
`.devcontainer/README.md`, not in each PR.

## Validation evidence

List the checks that actually ran. Do not mark a check as completed when it was
not executed.

- [ ] Focused regression/acceptance test
- [ ] Relevant lint/type/static-analysis checks
- [ ] Broader suite required by the changed surface
- [ ] CI reviewed

## UI / front-end changes

<!-- Remove this section when the PR does not change user-visible UI. -->

- [ ] Describe the visible change
- [ ] Screenshots before/after added when the UI changed

| Before | After |
| --- | --- |
| Screenshot before | Screenshot after |

<!-- Test and document both light and dark themes when the changed UI supports them. -->

- [ ] Light and dark themes checked when applicable
- [ ] Tested in relevant browsers when applicable
- [ ] Component/unit and/or Playwright tests added or updated as appropriate
- [ ] Accessibility checked when applicable
- [ ] Design review linked when applicable
- [ ] Documentation updated when the user-facing contract changed

## API / back-end changes

<!-- Remove this section when the PR does not affect backend/API behavior. -->

- [ ] Describe the API/service/architecture change
- [ ] Unit and/or integration regression coverage added or updated
- [ ] Authorization and negative paths checked when applicable
- [ ] Capabilities updated when applicable
- [ ] `composer openapi` run when the API contract changed
- [ ] Documentation updated when the public or durable engineering contract changed

## Tasks

<!-- List real prerequisites or follow-up tasks required before merge. Remove when empty. -->

- [ ] ...

## Checklist

- [ ] I have read and followed [CONTRIBUTING.md](../CONTRIBUTING.md).
- [ ] The diff does not contain unrelated refactors or generated changes.
- [ ] Commits follow Conventional Commits where applicable.
- [ ] All commits include the required DCO sign-off.

## AI assistance

- [ ] This PR was partially or fully produced with AI assistance.

AI-assisted contributions follow the same review, testing, and responsibility
requirements as any other contribution.
