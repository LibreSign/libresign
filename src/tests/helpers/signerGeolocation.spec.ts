/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import {
	collectDeviceGeolocation,
	formatDeviceReportedLocation,
	GEOLOCATION_POSITION_OPTIONS,
	isGeolocationRequired,
	mapGeolocationError,
} from '../../helpers/signerGeolocation'

describe('signerGeolocation helper', () => {
	it('treats only frozen required as a collection gate', () => {
		expect(isGeolocationRequired('required')).toBe(true)
		expect(isGeolocationRequired('disabled')).toBe(false)
		expect(isGeolocationRequired(undefined)).toBe(false)
	})

	it('maps browser position error codes', () => {
		expect(mapGeolocationError({ code: 1 })).toBe('permission_denied')
		expect(mapGeolocationError({ code: 2 })).toBe('position_unavailable')
		expect(mapGeolocationError({ code: 3 })).toBe('timeout')
		expect(mapGeolocationError({})).toBe('unknown')
	})

	it('uses fresh high-accuracy position options', () => {
		expect(GEOLOCATION_POSITION_OPTIONS).toEqual({
			enableHighAccuracy: true,
			timeout: 15_000,
			maximumAge: 0,
		})
	})

	it('returns unsupported when the Geolocation API is missing', async () => {
		await expect(collectDeviceGeolocation(undefined)).resolves.toEqual({
			ok: false,
			reason: 'unsupported',
		})
	})

	it('collects a normalized collected payload on success', async () => {
		const api = {
			getCurrentPosition: vi.fn((success: PositionCallback) => {
				success({
					coords: {
						latitude: -23.5505,
						longitude: -46.6333,
						accuracy: 25,
						altitude: null,
						altitudeAccuracy: null,
						heading: null,
						speed: null,
						toJSON() {
							return this
						},
					},
					timestamp: 1_700_000_000_000,
					toJSON() {
						return this
					},
				} as GeolocationPosition)
			}),
		} as unknown as Geolocation

		await expect(collectDeviceGeolocation(api)).resolves.toEqual({
			ok: true,
			geolocation: {
				status: 'collected',
				latitude: -23.5505,
				longitude: -46.6333,
				accuracy: 25,
				timestamp: 1_700_000_000_000,
			},
		})
		expect(api.getCurrentPosition).toHaveBeenCalledWith(
			expect.any(Function),
			expect.any(Function),
			GEOLOCATION_POSITION_OPTIONS,
		)
	})

	it('maps permission denial from getCurrentPosition', async () => {
		const api = {
			getCurrentPosition: vi.fn((_success: PositionCallback, error: PositionErrorCallback) => {
				error({ code: 1, message: 'denied', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 } as GeolocationPositionError)
			}),
		} as unknown as Geolocation

		await expect(collectDeviceGeolocation(api)).resolves.toEqual({
			ok: false,
			reason: 'permission_denied',
		})
	})

	it('maps position unavailable and timeout from getCurrentPosition', async () => {
		const unavailableApi = {
			getCurrentPosition: vi.fn((_success: PositionCallback, error: PositionErrorCallback) => {
				error({ code: 2, message: 'unavailable', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 } as GeolocationPositionError)
			}),
		} as unknown as Geolocation
		const timeoutApi = {
			getCurrentPosition: vi.fn((_success: PositionCallback, error: PositionErrorCallback) => {
				error({ code: 3, message: 'timeout', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 } as GeolocationPositionError)
			}),
		} as unknown as Geolocation

		await expect(collectDeviceGeolocation(unavailableApi)).resolves.toEqual({
			ok: false,
			reason: 'position_unavailable',
		})
		await expect(collectDeviceGeolocation(timeoutApi)).resolves.toEqual({
			ok: false,
			reason: 'timeout',
		})
	})

	it('formats device-reported location for audit display', () => {
		expect(formatDeviceReportedLocation(null)).toBeNull()
		expect(formatDeviceReportedLocation({
			status: 'denied',
		})).toBeNull()
		expect(formatDeviceReportedLocation({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
			accuracy: 12,
			timestamp: 0,
		})).toContain('-23.55, -46.63')
		expect(formatDeviceReportedLocation({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
			accuracy: 12,
			timestamp: 0,
		})).toContain('±12 m')
	})
})
