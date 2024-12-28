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
		<NcAppNavigationItem icon="icon-user"
			:name="t('libresign', 'Account')"
			:to=" {name: 'Account'} " />
		<NcAppNavigationItem :name="t('libresign', 'Rate LibreSign  ❤️')"
			href="https://apps.nextcloud.com/apps/libresign#comments">
			<template #icon>
				<StarIcon :size="20" />
			</template>
		</NcAppNavigationItem>
	</ul>
</template>

<script>
import StarIcon from 'vue-material-design-icons/Star.vue'

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

export default {
	name: 'Settings',
	components: {
		NcAppNavigationItem,
		StarIcon,
	},
}
</script>
