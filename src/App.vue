<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="libresign" :class="{'sign-external-page': isSignExternalPage}">
		<LeftSidebar v-if="showLeftSidebar" />
		<NcAppContent :class="{'icon-loading' : loading }">
			<DefaultPageError v-if="isDoNothingError" />
			<router-view
				v-else-if="!loading"
				:key="$route.name"
				v-model:loading="loading" />
			<NcEmptyContent v-if="isRoot" :description="t('libresign', 'LibreSign, digital signature app for Nextcloud.')">
				<template #icon>
					<img :src="LogoLibreSign">
				</template>
			</NcEmptyContent>
		</NcAppContent>
		<RightSidebar />
	</NcContent>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import LeftSidebar from './components/LeftSidebar/LeftSidebar.vue'
import RightSidebar from './components/RightSidebar/RightSidebar.vue'
import DefaultPageError from './views/DefaultPageError.vue'

import LogoLibreSign from '../img/logo-gray.svg'

const route = useRoute()
const loading = ref(false)

const isRoot = computed(() => route.path === '/')
const isSignExternalPage = computed(() => route.path.startsWith('/p/'))
const isDoNothingError = computed(() => (route.params?.action as number | undefined) === 2000)
const showLeftSidebar = computed(() => !route.matched.some(record => record.meta?.hideLeftSidebar === true))
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
