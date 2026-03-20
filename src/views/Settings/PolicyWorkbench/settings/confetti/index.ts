/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import ConfettiRuleEditor from './ConfettiRuleEditor.vue'
import type { PolicySettingDefinition } from '../../types'

export const confettiDefinition: PolicySettingDefinition<'confetti'> = {
	key: 'confetti',
	title: t('libresign', 'Confetti animation'),
	description: t('libresign', 'Control whether a celebratory animation is shown when someone signs a document.'),
	menuHint: t('libresign', 'Useful to validate the policy shell with a compact editor and simple value shape.'),
	editor: ConfettiRuleEditor,
	createEmptyValue: () => ({
		enabled: true,
	}),
	summarizeValue: (value) => value.enabled
		? t('libresign', 'Enabled')
		: t('libresign', 'Disabled'),
	formatAllowOverride: (allowChildOverride) => allowChildOverride
		? t('libresign', 'Lower layers may override this rule')
		: t('libresign', 'Lower layers must inherit this value'),
}
