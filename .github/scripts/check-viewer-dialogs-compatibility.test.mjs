/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import assert from 'node:assert/strict'
import { test } from 'node:test'
import { usesOnlyDialogs8, viewerCandidates } from './check-viewer-dialogs-compatibility.mjs'

test('checks the v2 prerelease even when the latest tag points to v1', () => {
	assert.deepEqual(viewerCandidates({ latest: '1.0.0', next: '2.0.0-beta.20' }), ['2.0.0-beta.20'])
})

test('deduplicates tags and accepts a stable v2 release', () => {
	assert.deepEqual(viewerCandidates({ latest: '2.0.0', next: '2.0.0' }), ['2.0.0'])
})

test('accepts a tree with Viewer and dialogs 8 only', () => {
	assert.equal(usesOnlyDialogs8({
		packages: {
			'node_modules/@nextcloud/viewer': { version: '2.0.0' },
			'node_modules/@nextcloud/dialogs': { version: '8.0.0' },
			'node_modules/@nextcloud/image-editor': { version: '1.1.0' },
		},
	}), true)
})

test('rejects a nested dialogs 7 package even if dialogs 8 exists at the root', () => {
	assert.equal(usesOnlyDialogs8({
		packages: {
			'node_modules/@nextcloud/viewer': { version: '2.0.0' },
			'node_modules/@nextcloud/dialogs': { version: '8.0.0' },
			'node_modules/@nextcloud/viewer/node_modules/@nextcloud/dialogs': { version: '7.6.0' },
		},
	}), false)
})

test('rejects a dependency tree without the Viewer', () => {
	assert.equal(usesOnlyDialogs8({ packages: {
		'node_modules/@nextcloud/dialogs': { version: '8.0.0' },
	} }), false)
})

test('rejects a root dialogs 7 release', () => {
	assert.equal(usesOnlyDialogs8({ packages: {
		'node_modules/@nextcloud/viewer': { version: '2.0.0' },
		'node_modules/@nextcloud/dialogs': { version: '7.6.0' },
	} }), false)
})
