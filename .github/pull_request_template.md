Resolves: # <!-- related github issue -->

## 📝 Summary

A concise description of what this PR does and why.

## 🧪 How to test
<!--
IMPORTANT: The "How to see this running using GitHub Codespaces" details block SHOULD NOT be included in the final template.
Testing instructions should be written specifically for each PR,
describing the steps needed to validate the proposed changes.

Example:
1. Access the signatures page
2. Upload a test PDF
3. Click "Sign" and verify the modal opens correctly
4. Confirm the signature was applied

Feel free to paste terminal commands or URLs that help reviewers follow along.
-->

<details>
<summary>How to see this running using GitHub Codespaces</summary>

### 1. Open the Codespace
- Authenticate to GitHub
- Go to the branch: [chore/reduce-configure-check-time](https://github.com/LibreSign/libresign/tree/chore/reduce-configure-check-time)
- Click the `Code` button and select the `Codespaces` tab.
- Click **"Create codespace on feat/customize-signature-stamp"**

### 2. Wait for the environment to start
- A progress bar will appear on the left.
- After that, the terminal will show the build process.
- Wait until you see the message:
  ```bash
  ✍️ LibreSign is up!
  ```
  This may take a few minutes.

### 3. Access LibreSign in the browser
- Open the **Ports** tab (next to the **Terminal**).
- Look for the service running on port **80**.
- Hover over the URL and click the **globe icon** 🌐 to open it in your browser.

### 4. (Optional) Make the service public
- If you want to share the app with people **not logged in to GitHub**, you must change the port visibility:
  - Click the three dots `⋮` on the row for port 80.
  - Select `Change visibility` → `Public`.

### 5. Login credentials
- **Username**: `admin`
- **Password**: `admin`

Done! 🎉
You're now ready to test this.
</details>

## 🎨 UI / Front‑end changes

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

🏚️ Before | 🏡 After
--- | ---
Screenshot before | Screenshot after

<!-- ☀️ Light theme | 🌑 Dark theme → Please test and document both themes -->

- [ ] Tested in multiple browsers (Chrome, Firefox, Safari) – *optional but appreciated*
- [ ] Components, Unit (with vitest) and/or e2e (with Playwright) tests added - *Required*
- [ ] Accessibility verified (contrast, keyboard navigation, screen reader friendly) – *if applicable*
- [ ] Design review approved – *optional, link to feedback if available*
- [ ] Documentation updated (if applicable) – [docs repository](https://github.com/LibreSign/documentation/)

### 🚧 Tasks
<!-- Add here the list of tasks that is necessary to do before merge this PR. As example: update the package X, merge the PR y. If isn't necessary, fell free to remove this block -->
- [ ] ...

## ⚙️ API / Back‑end changes

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
- [ ] Documentation updated (if applicable) - [docs repository](https://github.com/LibreSign/documentation)
- [ ] API documentation updated with the command `composer openapi` if necessary <!-- This generates the openapi.json file -->

### 🚧 Tasks
<!-- Add here the list of tasks that is necessary to do before merge this PR. As example: update the package X, merge the PR y. If isn't necessary, fell free to remove this block -->
- [ ] ...

## ✅ Checklist

- [ ] I have read and followed the [contribution guide](CONTRIBUTING.md).
- [ ] ... (list your own tasks here)

## 🤖 AI (if applicable)

- [ ] The content of this PR was partially or fully generated using AI
