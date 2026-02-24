/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DefaultPageError from '../../views/DefaultPageError.vue'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	isRTL: vi.fn(() => false),
}))

describe('DefaultPageError.vue - Error Aggregation Rules', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
	})

	it('uses errors array when present', () => {
		loadStateMock.mockImplementation((app, key, fallback) => {
			if (key === 'errors') {
				return [{ message: 'Error A' }, { message: 'Error B' }]
			}
			return fallback
		})

		const wrapper = mount(DefaultPageError, {
			stubs: {
				NcEmptyContent: true,
				NcNoteCard: true,
				AlertCircleOutline: true,
			},
		})

		expect(wrapper.vm.errors).toEqual([
			{ message: 'Error A' },
			{ message: 'Error B' },
		])
	})

	it('falls back to single error message when errors array is empty', () => {
		loadStateMock.mockImplementation((app, key, fallback) => {
			if (key === 'errors') {
				return []
			}
			if (key === 'error') {
				return { message: 'Single error' }
			}
			return fallback
		})

		const wrapper = mount(DefaultPageError, {
			stubs: {
				NcEmptyContent: true,
				NcNoteCard: true,
				AlertCircleOutline: true,
			},
		})

		expect(wrapper.vm.errors).toEqual([{ message: 'Single error' }])
	})

	it('returns empty array when no error state is provided', () => {
		loadStateMock.mockImplementation((app, key, fallback) => fallback)

		const wrapper = mount(DefaultPageError, {
			stubs: {
				NcEmptyContent: true,
				NcNoteCard: true,
				AlertCircleOutline: true,
			},
		})

		expect(wrapper.vm.errors).toEqual([])
	})
})
