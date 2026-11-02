/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// The native viewer and its dialogs 7 peer stay isolated from LibreSign's dialogs 8.
// Remove this bridge when the weekly Viewer/dialogs compatibility check signals cleanup.
export { canView, getHandlers, getViewer } from '@nextcloud/viewer'
