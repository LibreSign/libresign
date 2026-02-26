/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { t, n } from '@nextcloud/l10n'

import Validation from './views/Validation.vue'
import router from './router/router'

const app = createApp(Validation)

app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.config.globalProperties.OC = OC
app.config.globalProperties.OCA = OCA

app.use(router)

app.mount('#content')
