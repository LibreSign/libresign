/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	mdiFileDocument,
	mdiClockOutline,
	mdiAlert,
	mdiCheckCircle,
	mdiFileCancel,
	mdiSync,
	mdiHelpCircle,
} from '@mdi/js'
import { translate as t } from '@nextcloud/l10n'

import { FILE_STATUS } from '../constants.js'

const STATUS_CONFIG = {
	[FILE_STATUS.NOT_LIBRESIGN_FILE]: {
		label: () => t('libresign', 'Not LibreSign file'),
		icon: mdiFileDocument,
	},
	[FILE_STATUS.DRAFT]: {
		label: () => t('libresign', 'Draft'),
		icon: mdiFileDocument,
	},
	[FILE_STATUS.ABLE_TO_SIGN]: {
		label: () => t('libresign', 'Ready to sign'),
		icon: mdiClockOutline,
	},
	[FILE_STATUS.PARTIAL_SIGNED]: {
		label: () => t('libresign', 'Partially signed'),
		icon: mdiAlert,
	},
	[FILE_STATUS.SIGNED]: {
		label: () => t('libresign', 'Signed'),
		icon: mdiCheckCircle,
	},
	[FILE_STATUS.DELETED]: {
		label: () => t('libresign', 'Deleted'),
		icon: mdiFileCancel,
	},
	[FILE_STATUS.SIGNING_IN_PROGRESS]: {
		label: () => t('libresign', 'Signing'),
		icon: mdiSync,
	},
}

export function getStatusConfig(status) {
	return STATUS_CONFIG[status] || {
		label: () => t('libresign', 'Unknown'),
		icon: mdiHelpCircle,
	}
}

export function getStatusLabel(status) {
	return getStatusConfig(status).label()
}

export function getStatusIcon(status) {
	return getStatusConfig(status).icon
}

export function getStatusSvgInline(status) {
	try {
		const svgs = {
			[FILE_STATUS.DRAFT]: require('@mdi/svg/svg/file.svg?raw').default,
			[FILE_STATUS.ABLE_TO_SIGN]: require('@mdi/svg/svg/signature.svg?raw').default,
			[FILE_STATUS.PARTIAL_SIGNED]: require('@mdi/svg/svg/fraction-one-half.svg?raw').default,
			[FILE_STATUS.SIGNED]: require('@mdi/svg/svg/signature-freehand.svg?raw').default,
			[FILE_STATUS.SIGNING_IN_PROGRESS]: require('@mdi/svg/svg/loading.svg?raw').default,
		}

		const colors = {
			[FILE_STATUS.DRAFT]: '#9E9E9E',
			[FILE_STATUS.ABLE_TO_SIGN]: '#D4A843',
			[FILE_STATUS.PARTIAL_SIGNED]: '#D4A843',
			[FILE_STATUS.SIGNED]: '#4CAF50',
			[FILE_STATUS.SIGNING_IN_PROGRESS]: '#2196F3',
		}

		const svg = svgs[status]
		const color = colors[status]
		if (!svg) return ''
		if (!color) return svg
		return svg.replace('<path ', `<path fill="${color}" `)
	} catch (e) {
		return ''
	}
}

export function buildStatusMap() {
	const classMap = {
		[FILE_STATUS.NOT_LIBRESIGN_FILE]: 'unknown',
		[FILE_STATUS.DRAFT]: 'draft',
		[FILE_STATUS.ABLE_TO_SIGN]: 'ready',
		[FILE_STATUS.PARTIAL_SIGNED]: 'partial',
		[FILE_STATUS.SIGNED]: 'signed',
		[FILE_STATUS.DELETED]: 'deleted',
		[FILE_STATUS.SIGNING_IN_PROGRESS]: 'signing',
	}

	const map = {}

	Object.entries(STATUS_CONFIG).forEach(([statusNum, config]) => {
		const entry = {
			label: config.label(),
			icon: config.icon,
			class: classMap[statusNum] || 'unknown',
		}

		map[statusNum] = entry

		for (const [key, value] of Object.entries(FILE_STATUS)) {
			if (value === parseInt(statusNum, 10)) {
				map[key] = entry
			}
		}
	})

	map.PENDING = map[FILE_STATUS.ABLE_TO_SIGN]
	map.PARTIAL = map[FILE_STATUS.PARTIAL_SIGNED]

	return map
}
