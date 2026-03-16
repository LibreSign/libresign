/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock, interpolateL10n } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'

import axios from '@nextcloud/axios'
import Reminders from '../../../views/Settings/Reminders.vue'

vi.mock('debounce', () => ({
	default: <T extends (...args: any[]) => any>(fn: T) => fn,
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((value: number) => ({
		format: vi.fn((pattern: string) => `formatted:${value}:${pattern}`),
	})),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock({
	t: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	n: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
	translate: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	translatePlural: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
}))

describe('Reminders.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(axios.get).mockResolvedValue({
			data: {
				ocs: {
					data: {
						days_before: '2',
						days_between: '5',
						max: '3',
						send_timer: '14:30:00',
						next_run: '2026-03-06 12:00:00',
					},
				},
			},
		})
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						days_before: 0,
						days_between: 0,
						max: 0,
						send_timer: '',
						next_run: null,
					},
				},
			},
		})
	})

	function createWrapper() {
		return mount(Reminders, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcTextField: true,
					NcCheckboxRadioSwitch: true,
					NcLoadingIcon: true,
					NcDateTimePickerNative: true,
				},
			},
		})
	}

	it('loads reminder settings on mount and computes the formatted next run', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/reminder')
		expect(wrapper.vm.reminderDaysBefore).toBe(2)
		expect(wrapper.vm.reminderDaysBetween).toBe(5)
		expect(wrapper.vm.reminderMax).toBe(3)
		expect(wrapper.vm.reminderState).toBe(true)
		expect(wrapper.vm.nextRunFormatted).toContain('formatted:')
	})

	it('resets reminder fields and persists zeros when reminders are turned off', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.reminderState = false
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/reminder', {
			daysBefore: 0,
			daysBetween: 0,
			max: 0,
			sendTimer: '',
		})
		expect(wrapper.vm.reminderDaysBefore).toBe(0)
		expect(wrapper.vm.reminderDaysBetween).toBe(0)
		expect(wrapper.vm.reminderMax).toBe(0)
		expect(wrapper.vm.formatHourMinute(wrapper.vm.reminderSendTimer)).toBe('10:00')
	})

	it('formats a reminder send time as HH:mm', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.formatHourMinute(new Date('2022-10-10 09:05:00'))).toBe('09:05')
	})
})
