/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { GeoIpConfig } from '../helpers/geoipConfig'

type OcsGeoIpResponse = {
	ocs?: {
		data?: GeoIpConfig
	}
}

function unwrap(response: { data?: OcsGeoIpResponse }): GeoIpConfig {
	const data = response.data?.ocs?.data
	if (!data || typeof data !== 'object') {
		throw new Error('Invalid GeoIP configuration response')
	}

	return data
}

export async function getGeoIpConfig(): Promise<GeoIpConfig> {
	const response = await axios.get<OcsGeoIpResponse>(
		generateOcsUrl('/apps/libresign/api/v1/admin/geoip'),
	)
	return unwrap(response)
}

export async function saveGeoIpConfig(path: string): Promise<GeoIpConfig> {
	const response = await axios.post<OcsGeoIpResponse>(
		generateOcsUrl('/apps/libresign/api/v1/admin/geoip'),
		{ path },
	)
	return unwrap(response)
}
