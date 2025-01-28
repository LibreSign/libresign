/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

import { isExternal } from '../helpers/isExternal.js'

const redirectURL = loadState('libresign', 'redirect', 'Home')

export const selectAction = (action, to, from) => {
	const isExternalRoute = isExternal(to, from)
	const external = isExternalRoute ? 'External' : ''
	switch (action) {
	case 1000: // ACTION_REDIRECT
		window.location.replace(redirectURL.toString())
		break
	case 1500: // ACTION_CREATE_ACCOUNT
		return 'CreateAccount' + external
	case 2000: // ACTION_DO_NOTHING
		return to.name
	case 2500: // ACTION_SIGN
		return 'SignPDF' + external
	case 2625: // ACTION_SIGN_INTERNAL
		return 'SignPDF' + external
	case 2750: // ACTION_SIGN_ACCOUNT_FILE
		return 'AccountFileApprove' + external
	case 3000: // ACTION_SHOW_ERROR
		return 'DefaultPageError' + external
	case 3500: // ACTION_SIGNED
		return 'ValidationFile' + external
	case 4000: // ACTION_CREATE_SIGNATURE_PASSWORD
		return 'CreatePassword' + external
	case 4500: // ACTION_RENEW_EMAIL
		return 'RenewEmail' + external
	case 5000: // ACTION_INCOMPLETE_SETUP
		return 'Incomplete' + external
	default:
		if (loadState('libresign', 'error', false)) {
			return 'DefaultPageError' + external
		}
		break
	}
}
