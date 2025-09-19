/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export const useUserConfigStore = defineStore('userconfig', {
    state: () => ({
        grid_view: loadState('libresign', 'config', { grid_view: false }).grid_view,
    }),
    actions: {
        onUpdate(key, value) {
            this[key] = value
        },

        async update(key, value) {
            this.onUpdate(key, value)

            await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key }), {
                value,
            })
        },
    },
})
