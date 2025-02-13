<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div class="container-image">
			<img :src="image" draggable="false">
		</div>
		<h1 class="title">
			{{ t('libresign', 'Welcome to LibreSign') }}
		</h1>
		<NcButton v-if="isAdmin"
			@click="finishSetup">
			<template #icon>
				<CogsIcon :size="20" />
			</template>
			{{ t('libresign', 'Finish the setup') }}
		</NcButton>
		<p v-else>
			{{ t('libresign', 'The admin hasn\'t set up LibreSign yet, please wait.') }}
		</p>
	</div>
</template>

<script>

import CogsIcon from 'vue-material-design-icons/Cogs.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'

import BackgroundImage from '../../img/logo-gray.svg'

export default {
	name: 'IncompleteCertification',
	components: {
		NcButton,
		CogsIcon,
	},
	data() {
		return {
			image: BackgroundImage,
			isAdmin: getCurrentUser().isAdmin,
		}
	},
	methods: {
		finishSetup() {
			window.location.href = generateUrl('settings/admin/libresign')
		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
	h1 {
		font-weight: bold;
		font-size: 24px;
		color: var(--color-main-text);
	}
}

.container-image {
	display: flex;
	justify-content: center;
	margin: 20px;

	img {
		width: 30%;
	}
}

</style>
