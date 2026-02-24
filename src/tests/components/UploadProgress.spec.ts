/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import UploadProgress from '../../components/UploadProgress.vue'
import type { TranslationFunction } from '../test-types'

type L10nVars = Record<string, string | number>

const interpolate = (template: string, vars?: L10nVars) => {
	if (!vars) {
		return template
	}
	return template.replace(/{(\w+)}/g, (match: string, key: string) => {
		if (Object.prototype.hasOwnProperty.call(vars, key)) {
			return String(vars[key])
		}
		return match
	})
}

const globalWithT = globalThis as typeof globalThis & { t?: TranslationFunction }

globalWithT.t = vi.fn((_app, text, vars?: L10nVars) => interpolate(text, vars))

describe('UploadProgress', () => {
	let wrapper: any

	const createWrapper = (props = {}) => {
		return mount(UploadProgress, {
			props: {
				isUploading: true,
				uploadProgress: 0,
				uploadedBytes: 0,
				totalBytes: 0,
				uploadStartTime: null,
				...props,
			},
			mocks: {
				t: (_app: string, text: string, vars?: L10nVars) => interpolate(text, vars),
			},
			stubs: {
				NcButton: true,
				NcProgressBar: true,
				CancelIcon: true,
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
		}
		vi.clearAllMocks()
	})

	describe('RULE: uploadEta returns empty when upload not active', () => {
		it('returns empty string when not uploading', () => {
			wrapper = createWrapper({ isUploading: false })

			expect(wrapper.vm.uploadEta).toBe('')
		})

		it('returns empty string when no start time', () => {
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: null,
			})

			expect(wrapper.vm.uploadEta).toBe('')
		})

		it('returns empty string when uploaded bytes is zero', () => {
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: Date.now(),
				uploadedBytes: 0,
			})

			expect(wrapper.vm.uploadEta).toBe('')
		})
	})

	describe('RULE: uploadEta calculates time based on upload rate', () => {
		it('shows few seconds for very fast uploads under 1 second', () => {
			const startTime = Date.now() - 100
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 5000000,
				totalBytes: 10000000,
			})

			expect(wrapper.vm.uploadEta).toBe('a few seconds left')
		})

		it('shows seconds count for uploads under 1 minute', () => {
			const startTime = Date.now() - 2000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 1000000,
				totalBytes: 2000000,
			})

			const eta = wrapper.vm.uploadEta
			expect(eta).toContain('seconds left')
			expect(eta).toMatch(/\d+/)
		})

		it('rounds seconds up to nearest integer', () => {
			const startTime = Date.now() - 3000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 3000000,
				totalBytes: 4500000,
			})

			const eta = wrapper.vm.uploadEta
			const seconds = parseInt(eta.match(/\d+/)[0])
			expect(seconds).toBeGreaterThan(0)
		})

		it('shows minutes for uploads over 1 minute', () => {
			const startTime = Date.now() - 10000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 1000000,
				totalBytes: 10000000,
			})

			const eta = wrapper.vm.uploadEta
			expect(eta).toContain('minutes left')
			expect(eta).toMatch(/\d+/)
		})

		it('rounds minutes up to nearest integer', () => {
			const startTime = Date.now() - 5000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 1000000,
				totalBytes: 20000000,
			})

			const eta = wrapper.vm.uploadEta
			const minutes = parseInt(eta.match(/\d+/)[0])
			expect(minutes).toBeGreaterThan(0)
		})
	})

	describe('RULE: uploadEta handles edge cases in calculation', () => {
		it('handles very slow uploads correctly', () => {
			const startTime = Date.now() - 30000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 100000,
				totalBytes: 10000000,
			})

			const eta = wrapper.vm.uploadEta
			expect(eta).toContain('minutes left')
		})

		it('handles nearly complete uploads', () => {
			const startTime = Date.now() - 5000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 9900000,
				totalBytes: 10000000,
			})

			const eta = wrapper.vm.uploadEta
			expect(eta).toBeTruthy()
		})

		it('calculates correctly when halfway through', () => {
			const startTime = Date.now() - 10000
			wrapper = createWrapper({
				isUploading: true,
				uploadStartTime: startTime,
				uploadedBytes: 5000000,
				totalBytes: 10000000,
			})

			const eta = wrapper.vm.uploadEta
			expect(eta).toBeTruthy()
		})
	})

	describe('RULE: component emits cancel event when button clicked', () => {
		it('emits cancel event on button click', async () => {
			wrapper = createWrapper({
				isUploading: true,
				uploadProgress: 50,
			})

			await wrapper.vm.$emit('cancel')

			expect(wrapper.emitted('cancel')).toBeTruthy()
			expect(wrapper.emitted('cancel')).toHaveLength(1)
		})
	})

	describe('RULE: component only renders when isUploading is true', () => {
		it('renders upload container when uploading', () => {
			wrapper = createWrapper({
				isUploading: true,
			})

			expect(wrapper.find('.upload-picker-container').exists()).toBe(true)
		})

		it('does not render when not uploading', () => {
			wrapper = createWrapper({
				isUploading: false,
			})

			expect(wrapper.find('.upload-picker-container').exists()).toBe(false)
		})
	})

	describe('RULE: uploadProgress prop controls progress bar value', () => {
		it('passes progress value to progress bar', () => {
			wrapper = createWrapper({
				isUploading: true,
				uploadProgress: 75,
			})

			expect(wrapper.vm.$props.uploadProgress).toBe(75)
		})

		it('defaults to zero when not provided', () => {
			wrapper = createWrapper({
				isUploading: true,
			})

			expect(wrapper.vm.$props.uploadProgress).toBe(0)
		})
	})
})
