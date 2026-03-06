/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { reactive, toRefs } from 'vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

export const useUserConfigStore = defineStore('userconfig', () => {
	const config = reactive(loadState('libresign', 'config', {}))

	const onUpdate = (key, value) => {
		config[key] = value
	}

	const update = async (key, value) => {
		onUpdate(key, value)

		await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key }), {
			value,
		})
	}

	return {
		...toRefs(config),
		onUpdate,
		update,
	}
})
