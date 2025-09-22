<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="wrapper">
		<div class="header">
			<div class="header__logo" />
		</div>
		<div class="content">
			<h2 v-if="response.length === 0" class="content__headline">
				{{ title }}
			</h2>
			<div v-if="response.length === 0 && error.length === 0"
				class="content__body">
				{{ body }}
			</div>
			<NcNoteCard v-if="response.length > 0"
				type="success">
				{{ response }}
			</NcNoteCard>
			<NcNoteCard v-if="error.length > 0"
				type="error">
				{{ error }}
			</NcNoteCard>
			<NcButton v-if="response.length === 0 && error.length === 0"
				variant="primary"
				:wide="true"
				:disabled="hasLoading"
				@click="renew">
				{{ renewButton }}
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
					<ArrowRight v-else />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

export default {
	name: 'RenewEmail',

	components: {
		NcButton,
		NcNoteCard,
		ArrowRight,
		NcLoadingIcon,
	},
	data() {
		return {
			title: loadState('libresign', 'title'),
			body: loadState('libresign', 'body'),
			renewButton: loadState('libresign', 'renewButton'),
			uuid: loadState('libresign', 'uuid'),
			hasLoading: false,
			response: '',
			error: '',
		}
	},
	methods: {
		async renew() {
			this.hasLoading = true
			this.error = ''
			try {
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/renew/email', {
					uuid: this.uuid,
				}))
				this.response = response.data.ocs.data.message
			} catch (e) {
				this.error = e.response.data.ocs.data.message
			}
			this.hasLoading = false
		},
	},
}
</script>

<style lang="scss">
body {
	font-size: var(--default-font-size);
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
}
</style>

<style lang="scss" scoped>
.wrapper {
	width: 100%;
	margin-block: 10vh auto;
	display: grid;
	justify-content: center;
}

.header {
	display: block;
	&__logo {
		background-image: var(--image-logo, url('../../img/logo-white.svg'));
		background-repeat: no-repeat;
		background-size: contain;
		background-position: center;
		width: 175px;
		height: 130px;
		margin: 0 auto;
		position: relative;
		inset-inline-start: unset;
	}
}

.content {
	--color-text-maxcontrast: var(--color-text-maxcontrast-background-blur, var(--color-main-text));
	color: var(--color-main-text);
	background-color: var(--color-main-background-blur);
	padding: 16px;
	border-radius: var(--border-radius-rounded);
	box-shadow: 0 0 10px var(--color-box-shadow);
	display: block;
	-webkit-backdrop-filter: var(--filter-background-blur);
	backdrop-filter: var(--filter-background-blur);
	box-sizing: border-box;

	&__link {
		display: block;
		padding: 1rem;
		font-size: var(--default-font-size);
		text-align: center;
		font-weight: normal !important;
	}

	&__headline {
		text-align: center;
		overflow-wrap: anywhere;
	}
	&__body {
		white-space: pre-line;
		text-align: start;
		font-size: 1rem;
		padding: 1rem;
	}
}
</style>
