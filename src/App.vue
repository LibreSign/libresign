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
	<NcContent app-name="libresign" :class="{'sign-external-page': isSignExternalPage}">
		<LeftSidebar />
		<NcAppContent :class="{'icon-loading' : loading }">
			<DefaultPageError v-if="isDoNothingError" />
			<router-view v-else-if="!loading" :key="$route.name " :loading.sync="loading" />
			<NcEmptyContent v-if="isRoot" :description="t('libresign', 'LibreSign, digital signature app for Nextcloud.')">
				<template #icon>
					<img :src="LogoLibreSign">
				</template>
			</NcEmptyContent>
		</NcAppContent>
		<RightSidebar />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import LeftSidebar from './Components/LeftSidebar/LeftSidebar.vue'
import RightSidebar from './Components/RightSidebar/RightSidebar.vue'
import DefaultPageError from './views/DefaultPageError.vue'

import LogoLibreSign from './../img/logo-gray.svg'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		NcEmptyContent,
		LeftSidebar,
		RightSidebar,
		DefaultPageError,
	},
	data() {
		return {
			loading: false,
			LogoLibreSign,
		}
	},
	computed: {
		isRoot() {
			return this.$route.path === '/'
		},
		isSignExternalPage() {
			return this.$route.path.startsWith('/p/')
		},
		isDoNothingError() {
			return this.$route.params?.action === 2000
		},
	},
}
</script>

<style lang="scss" scoped>
.sign-external-page {
	width: 100%;
	height: 100%;
	margin: unset;
	box-sizing: unset;
	border-radius: unset;
}
.app-libresign {
	.app-navigation {
		.app-navigation-entry.active {
			background-color: var(--color-primary-element) !important;
			.app-navigation-entry-link{
				color: var(--color-primary-element-text) !important;
			}
		}
	}
}

.app-content {
	.empty-content {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 70%;
		margin-top: unset !important;

		margin-top: 10vh;
		p {
			opacity: .6;
		}

		&__icon {
			width: 400px !important;
			height: unset !important;
		}
	}
}
</style>
