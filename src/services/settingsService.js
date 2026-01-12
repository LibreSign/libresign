/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const saveUserNumber = async (phoneNumber) => {
	const { data } = await axios.patch(
		generateUrl('/apps/libresign/api/v1/account/settings'),
		{ phone: phoneNumber },
	)
	return data
}

export const settingsService = {
	saveUserNumber,
}
