/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { withCollectedDeviceGeolocation } from '../../helpers/signGeolocationPayload'

describe('withCollectedDeviceGeolocation', () => {
	it('adds only deviceGeolocation and never IP fields', () => {
		const payload = withCollectedDeviceGeolocation(
			{ method: 'clickToSign' },
			{
				status: 'collected',
				latitude: -23.55,
				longitude: -46.63,
			},
		)

		expect(payload).toEqual({
			method: 'clickToSign',
			deviceGeolocation: {
				status: 'collected',
				latitude: -23.55,
				longitude: -46.63,
			},
		})
		expect(payload).not.toHaveProperty('geolocation')
		expect(JSON.stringify(payload)).not.toContain('geolocation.ip')
		expect(JSON.stringify(payload)).not.toContain('sourceIp')
	})

	it('leaves the payload without location keys when nothing was collected', () => {
		const payload = withCollectedDeviceGeolocation({ method: 'clickToSign' }, null)

		expect(payload).toEqual({ method: 'clickToSign' })
		expect(payload).not.toHaveProperty('deviceGeolocation')
	})
})
