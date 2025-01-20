/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import svgFile from '@mdi/svg/svg/file.svg?raw'
import svgFractionOneHalf from '@mdi/svg/svg/fraction-one-half.svg?raw'
import svgSignatureFreehand from '@mdi/svg/svg/signature-freehand.svg?raw'
import svgSignature from '@mdi/svg/svg/signature.svg?raw'

import { translate as t } from '@nextcloud/l10n'

const colorize = (svg, color) => {
	return svg.replace('<path ', `<path fill="${color}" `)
}

export const fileStatus = [
	{
		id: -1,
		label: t('libresign', 'not a LibreSign file'),
	},
	{
		id: 0,
		icon: colorize(svgFile, '#E0E0E0'),
		label: t('libresign', 'draft'),
	},
	{
		id: 1,
		icon: colorize(svgSignature, '#B2E0B2'),
		label: t('libresign', 'available for signature'),
	},
	{
		id: 2,
		icon: colorize(svgFractionOneHalf, '#F0E68C'),
		label: t('libresign', 'partially signed'),
	},
	{
		id: 3,
		icon: colorize(svgSignatureFreehand, '#A0C4FF'),
		label: t('libresign', 'signed'),
	},
	{
		id: 4,
		label: t('deleted'),
	},
]
