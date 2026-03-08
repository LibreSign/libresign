/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useActionsMenuStore = defineStore('actionsmenu', () => {
	const opened = ref(/** @type {number | null} */ (null))

	return {
		opened,
	}
})
