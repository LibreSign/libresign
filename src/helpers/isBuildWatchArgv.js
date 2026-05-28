/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const isBuildWatchArgv = (argv = process.argv) => argv.includes('--watch') || argv.includes('-w')
