/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// Deliberately use the package's real ESM entry point, without browser shims.
// Failure under Node is recorded as a limitation, not converted into a pass.
import nextcloudAxios from '@nextcloud/axios'
console.log('Imported @nextcloud/axios:', typeof nextcloudAxios.request)
