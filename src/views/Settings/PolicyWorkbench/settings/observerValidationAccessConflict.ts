/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

export const OBSERVER_PROFILE_POLICY_KEY = 'enable_observer_profile'
export const VALIDATION_ACCESS_POLICY_KEY = 'make_validation_url_private'

export function getObserverPrivateValidationWarningMessage(): string {
	// TRANSLATORS Warning shown when Observer and authenticated-only validation are both enabled: email observers get a public validation link they cannot open without a Nextcloud account.
	return t(
		'libresign',
		'Email observers receive a link to the public validation page. With authenticated-only validation, observers without a Nextcloud account cannot open the document.',
	)
}
