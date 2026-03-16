/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import { SIGN_REQUEST_STATUS } from '../constants.js'

export function getSignRequestStatusText(status: number | null | undefined): string {
	switch (status) {
	case SIGN_REQUEST_STATUS.SIGNED:
		return t('libresign', 'Signed')
	case SIGN_REQUEST_STATUS.ABLE_TO_SIGN:
		return t('libresign', 'Able to sign')
	case SIGN_REQUEST_STATUS.DRAFT:
	default:
		return t('libresign', 'Draft')
	}
}
