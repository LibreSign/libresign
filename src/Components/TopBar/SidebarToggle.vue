<!--
- @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
-
- @author Vitor Mattos <vitor@php.rio>
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
-->

<template>
	<NcButton v-if="sidebarStore.canShow()"
		:aria-label="t('libresign', 'Open sidebar')"
		:title="t('libresign', 'Open sidebar')"
		close-after-click="true"
		type="tertiary"
		@click="openSidebar">
		<template #icon>
			<MenuIcon :size="20" />
		</template>
	</NcButton>
</template>

<script>
import MenuIcon from 'vue-material-design-icons/Menu.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import { useSidebarStore } from '../../store/sidebar.js'

export default {
	name: 'SidebarToggle',
	components: {
		NcButton,
		MenuIcon,
	},
	setup() {
		const sidebarStore = useSidebarStore()
		return { sidebarStore }
	},
	methods: {
		openSidebar() {
			this.sidebarStore.showSidebar()
		},
	},
}
</script>
