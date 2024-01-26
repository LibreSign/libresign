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

const redirectURL = loadState('libresign', 'redirect', 'Home')

export const selectAction = (action) => {
	switch (action) {
	case 100: // ACTION_REDIRECT
		window.location.replace(redirectURL.toString())
		break
	case 150: // ACTION_CREATE_USER
		return 'CreateUser'
	case 200: // ACTION_DO_NOTHING
		return 'DefaultPageError'
	case 250: // ACTION_SIGN
		return 'SignPDF'
	case 275: // ACTION_SIGN_ACCOUNT_FILE
		return 'AccountFileApprove'
	case 300: // ACTION_SHOW_ERROR
		return 'DefaultPageSuccess'
	case 350: // ACTION_SIGNED
		return 'DefaultPageSuccess'
	case 400: // ACTION_CREATE_SIGNATURE_PASSWORD
		return 'CreatePassword'
	case 450: // ACTION_RENEW_EMAIL
		return 'RenewEmail'
	case 500: // ACTION_INCOMPLETE_SETUP
		return 'incomplete'
	default:
		break
	}
}
