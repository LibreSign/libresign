/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios, { type AxiosResponse } from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

interface SettingsData {
	[key: string]: unknown
}

const saveUserNumber = async (phoneNumber: string): Promise<SettingsData> => {
	const { data } = await axios.patch<SettingsData>(
		generateUrl('/apps/libresign/api/v1/account/settings'),
		{ phone: phoneNumber },
	)
	return data
}

export const settingsService = {
	saveUserNumber,
}
