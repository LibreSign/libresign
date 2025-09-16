/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

const libresignState = loadState('libresign', 'config', {})

export default {
	namespaced: true,

	state: {
		...{
			hasSignatureFile: false,
			identificationDocumentsFlow: false,
			isApprover: false,
			phoneNumber: '',
		},
		...(libresignState?.settings || {}),
	},
}
