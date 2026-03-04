<!--
  - 🚨 SECURITY INFO
  -
  - Before sending a pull request that fixes a security issue please report it via our HackerOne page (https://hackerone.com/nextcloud) following our security policy (https://nextcloud.com/security/). This allows us to coordinate the fix and release without potentially exposing all Nextcloud servers and users in the meantime.
-->

* Resolves: # <!-- related github issue -->

## Summary

A concise description of what this PR does and why.

## How to test

<details>
<summary>Instructions for running the app in Codespaces or local development</summary>

1. Open a Codespace using the `LibreSign` repository or start the container with `make dev-setup`.
2. Build assets (`npm run watch` for frontend, `composer test:unit -- --filter ...` for backend).
3. Execute the steps required to reproduce the changes (e.g. upload a PDF, open validation screen, call API endpoint).
4. Verify that the behaviour matches the description above.

Feel free to paste terminal commands or URLs that help reviewers follow along.

</details>

## UI / Front‑end changes

<!--
 █████  █████ █████
▒▒███  ▒▒███ ▒▒███
 ▒███   ▒███  ▒███
 ▒███   ▒███  ▒███
 ▒███   ▒███  ▒███
 ▒███   ▒███  ▒███
 ▒▒████████   █████
  ▒▒▒▒▒▒▒▒   ▒▒▒▒▒

Feel free to remove this section when your PR only affects the backend/API code.
-->

- [ ] ... <!-- Describe the tasks performed here (e.g., layout adjustment, new feature X) -->
- [ ] Screenshots before/after (add images or links)

| Before | After |
| --- | --- |
| <!-- Add screenshot → | ← Add screenshot → |

<!-- ☀️ Light theme | 🌑 Dark theme → Please test and document both themes (important for Nextcloud apps) -->

- [ ] Tested in multiple browsers (Chrome, Firefox, Safari) – *optional but appreciated*
- [ ] Accessibility verified (contrast, keyboard navigation, screen reader friendly) – *if applicable*
- [ ] Design review approved – *optional, link to feedback if available*
- [ ] User documentation updated (if applicable) – [user manual](https://docs.libresign.coop/user_manual/) / [docs repository](https://github.com/LibreSign/documentation/)

## API / Back‑end changes

<!--
   █████████   ███████████  █████
  ███▒▒▒▒▒███ ▒▒███▒▒▒▒▒███▒▒███
 ▒███    ▒███  ▒███    ▒███ ▒███
 ▒███████████  ▒██████████  ▒███
 ▒███▒▒▒▒▒███  ▒███▒▒▒▒▒▒   ▒███
 ▒███    ▒███  ▒███         ▒███
 █████   █████ █████        █████
▒▒▒▒▒   ▒▒▒▒▒ ▒▒▒▒▒        ▒▒▒▒▒

Feel free to remove this section when your PR only affects the frontend/UI code.
-->

- [ ] ... <!-- Describe the API/service/architecture changes here -->
- [ ] Unit and/or integration tests added – *required for backend changes*
- [ ] Capabilities updated (if applicable) – if adding/modifying Nextcloud capabilities
- [ ] API documentation updated with the command `composer openapi` if necessary <!-- This generates the openapi.json file -->

## ✅ Checklist

- [ ] I have read and followed the [contribution guide](CONTRIBUTING.md).
- [ ] ... (list your own tasks here)

## AI (if applicable)

- [ ] The content of this PR was partially or fully generated using AI
