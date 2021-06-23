<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<ul>
		<AppNavigationItem v-if="!hasSignature"
			icon="icon-add"
			:title="t('libresigng', 'Create Password Key')"
			:to="{name: 'CreatePassword'}" />
		<AppNavigationItem v-else
			icon="icon-password"
			:title="t('libresigng', 'Reset Password')"
			:to="{name: 'ResetPassword'}" />
		<AppNavigationItem
			icon="icon-user"
			:title="t('libresign', 'User Profile')"
			:to=" {name: 'Profile'} " />
	</ul>
</template>

<script>
import { mapGetters } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

export default {
	name: 'CroppedLayoutSettings',
	components: {
		AppNavigationItem,
	},
	computed: {
		...mapGetters({
			hasSignature: 'getHasPfx',
		}),
	},
	created() {
		this.checkHasSignature()
	},
	methods: {
		checkHasSignature() {
			const libresignSettings = JSON.parse(loadState('libresign', 'config'))
			this.$store.commit('setSettings', libresignSettings.settings)
		},
	},
}
</script>
