<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

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
