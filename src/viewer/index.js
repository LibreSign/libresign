/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// TODO(viewer-compat): Remove the temporary src/viewer/ bridge (PR #9301).
// When Viewer and Image Editor support dialogs 8, remove the root
// package.json scripts.postinstall and restore direct imports and mocks.
export { canView, getHandlers, getViewer } from '@nextcloud/viewer'
