<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Frontend Tests

## Running

```bash
npm test              # Run once
npm run test:watch    # Watch mode
npm run test:coverage # With coverage
```

## Writing

Create `.spec.js` files next to your source. See [src/helpers/isExternal.spec.js](../src/helpers/isExternal.spec.js) for example.

**What to test:**
- ✅ Helpers, utilities, business logic
- ✅ Pinia stores  
- ✅ Data transformations
- ❌ Vue components (use Cypress E2E later)

Tests run automatically on PRs.
