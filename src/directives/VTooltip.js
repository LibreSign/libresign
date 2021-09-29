import Vue from 'vue'
import VTooltip from '@nextcloud/vue/dist/Directives/Tooltip'

import '@nextcloud/dialogs/styles/toast.scss'

Vue.directive('Tooltip', VTooltip)
VTooltip.options.autohide = true
