/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

export const useActionsMenuStore = defineStore('actionsmenu', {
	state: () => ({
		opened: null,
	}),
})
