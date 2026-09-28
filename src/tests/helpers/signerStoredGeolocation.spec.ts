/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { getStoredSignerGeolocation } from '../../helpers/signerStoredGeolocation'

describe('getStoredSignerGeolocation', () => {
	it('keeps device and IP sources independent across signers', () => {
		const deviceOnly = {
			metadata: {
				geolocation: {
					device: { status: 'collected', latitude: -23.55, longitude: -46.63 },
				},
			},
		}
		const ipOnly = {
			metadata: {
				geolocation: {
					ip: { status: 'resolved', country: 'Brazil' },
				},
			},
		}

		expect(getStoredSignerGeolocation(deviceOnly)).toEqual({
			device: { status: 'collected', latitude: -23.55, longitude: -46.63 },
			ip: null,
		})
		expect(getStoredSignerGeolocation(ipOnly)).toEqual({
			device: null,
			ip: { status: 'resolved', country: 'Brazil' },
		})
	})

	it('returns stored evidence without reading the current policy', () => {
		const historicalSigner = {
			metadata: {
				geolocation: {
					device: { status: 'collected', latitude: 1, longitude: 2 },
					ip: { status: 'not_found' },
				},
			},
		}

		expect(getStoredSignerGeolocation(historicalSigner)).toEqual({
			device: { status: 'collected', latitude: 1, longitude: 2 },
			ip: { status: 'not_found' },
		})
	})
})
