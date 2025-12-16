<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="error-page">
		<div class="logo-header">
			<img :src="logoLibreSign"
				:alt="t('libresign', 'LibreSign')"
				class="logo-icon">
		</div>

		<div class="error-container">
			<NcEmptyContent :name="t('libresign', 'Page not found')"
				:description="paragrath">
				<template #icon>
					<AlertCircleOutline :size="80" class="alert-icon" />
				</template>
				<template #action>
					<div v-if="errors.length" class="error-messages">
						<NcNoteCard v-for="(error, index) in errors"
							:key="index"
							type="error">
							{{ error.message }}
						</NcNoteCard>
					</div>
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

import logoLibreSign from '../../img/logo-gray.svg'

export default {
	name: 'DefaultPageError',
	components: {
		NcEmptyContent,
		NcNoteCard,
		AlertCircleOutline,
	},

	data() {
		return {
			logoLibreSign,
			paragrath: t('libresign', 'Sorry but the page you are looking for does not exist, has been removed, moved or is temporarily unavailable.'),
		}
	},
	computed: {
		errors() {
			const errors = loadState('libresign', 'errors', [])
			if (errors.length) {
				return errors
			}
			const errorMessage = loadState('libresign', 'error', {})?.message
			if (errorMessage) {
				return [{ message: errorMessage }]
			}
			return []
		},
	},

}
</script>

<style lang="scss" scoped>
.error-page {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 100vh;
	padding: 40px 20px;
	gap: 24px;
	background: var(--color-background-dark);

	.logo-header {
		.logo-icon {
			height: 80px;

			@media (max-width: 768px) {
				height: 60px;
			}
		}
	}

	.error-container {
		max-width: 800px;
		width: 100%;
		padding: 48px 32px;
		background: var(--color-main-background);
		border-radius: var(--border-radius-large);
		box-shadow: 0 2px 16px rgba(0, 0, 0, 0.1);

		:deep(.empty-content__action) {
			min-width: 500px;

			@media (max-width: 768px) {
				min-width: 100%;
			}
		}

		.error-messages {
			display: flex;
			flex-direction: column;
			gap: 12px;
			width: 100%;
		}

		@media (max-width: 768px) {
			padding: 32px 24px;
		}
	}

	.alert-icon {
		color: #e53c3c;
	}
}
</style>
