/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

import Settings from './views/Settings/Settings.vue'

const app = createApp(Settings)

app.config.globalProperties.t = t
app.config.globalProperties.n = n

app.use(createPinia())
app.mount('#libresign-admin-settings')
