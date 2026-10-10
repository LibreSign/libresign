/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
export default {
  packageManager: 'npm',
  reporters: ['html', 'clear-text', 'progress'],
  testRunner: 'vitest',
  ignorePatterns: ['/vendor', '/vendor-bin', '/3rdparty', '/lib', '/js', '/css', '/tests', '/playwright', '/build'],
  vitest: {
    configFile: 'vitest.config.js'
  },
  coverageAnalysis: 'perTest',
  // .vue files are not mutated: Stryker's instrumentation breaks <script setup> macros
  // (defineOptions/defineProps), which makes the initial test run crash.
  mutate: [
    'src/**/*.{js,ts}',
    '!src/tests/**',
    '!src/**/*.d.ts',
  ],
};
