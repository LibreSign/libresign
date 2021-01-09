import Vue from 'vue'
import Settings from './Settings'

Vue.mixin({ methods: { t, n } })

export default new Vue({
	el: '#libresign-admin-settings',
	render: h => h(Settings),
})
