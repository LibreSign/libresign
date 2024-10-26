/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'

import Settings from './views/Settings/Settings.vue'

Vue.mixin({ methods: { t, n } })

Vue.use(PiniaVuePlugin)

const pinia = createPinia()

export default new Vue({
	el: '#libresign-admin-settings',
	pinia,
	render: h => h(Settings),
})
