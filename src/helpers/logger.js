/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getLoggerBuilder } from '@nextcloud/logger'

export default getLoggerBuilder()
	.setApp('libresign')
	.detectUser()
	.build()
