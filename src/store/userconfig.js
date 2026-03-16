/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { reactive, toRefs } from 'vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * @typedef {Record<string, unknown> & {
 * 	locale?: string
 * 	files_list_grid_view?: boolean
 * 	files_list_signer_identify_tab?: string
 * 	crl_filters?: { serialNumber?: string, status?: string | null, owner?: string }
 * 	crl_sort?: { sortBy?: string | null, sortOrder?: 'ASC' | 'DESC' | null }
 * 	id_docs_filters?: { owner?: string, status?: string | null }
 * 	id_docs_sort?: { sortBy?: string | null, sortOrder?: string | null }
 * }} UserConfigState
 */

export const useUserConfigStore = defineStore('userconfig', () => {
	const config = reactive(/** @type {UserConfigState} */ (loadState('libresign', 'config', {})))

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
