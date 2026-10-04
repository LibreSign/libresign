/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DeviceReportedLocation } from './signerGeolocation'
import type { SignerIpGeolocationEvidence } from './signerIpGeolocation'

export type SignerWithStoredGeolocation = {
	metadata?: {
		geolocation?: {
			device?: DeviceReportedLocation | null
			ip?: SignerIpGeolocationEvidence | null
		}
	}
}

export type StoredSignerGeolocation = {
	device: DeviceReportedLocation | null
	ip: SignerIpGeolocationEvidence | null
}

export function getStoredSignerGeolocation(
	signer: SignerWithStoredGeolocation | null | undefined,
): StoredSignerGeolocation {
	const geolocation = signer?.metadata?.geolocation
	return {
		device: geolocation?.device ?? null,
		ip: geolocation?.ip ?? null,
	}
}

export function getStoredDeviceGeolocation(
	signer: SignerWithStoredGeolocation | null | undefined,
): DeviceReportedLocation | null {
	return getStoredSignerGeolocation(signer).device
}

export function getStoredIpGeolocation(
	signer: SignerWithStoredGeolocation | null | undefined,
): SignerIpGeolocationEvidence | null {
	return getStoredSignerGeolocation(signer).ip
}
