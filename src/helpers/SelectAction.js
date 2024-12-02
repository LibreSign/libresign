/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
 * @author Vinicios Gomes <viniciosgomesviana@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
		return 'DefaultPageError' + external
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
