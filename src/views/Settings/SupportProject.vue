<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="support-project-content">
			<p class="support-message">
				{{ t('libresign', 'LibreSign is developed with ❤️ by LibreCode coop. Your support helps us maintain and improve this free and open-source project.') }}
			</p>
			<div class="support-buttons">
				<NcButton type="primary"
					href="https://github.com/sponsors/libresign"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<HeartIcon :size="20" />
					</template>
					{{ t('libresign', 'Sponsor on GitHub') }}
				</NcButton>
				<NcButton href="https://buy.stripe.com/eVqfZibhx8QO3LseWc2kw00"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<CurrencyUsdIcon :size="20" />
					</template>
					{{ t('libresign', 'Donate via Stripe') }}
				</NcButton>
				<NcButton href="https://libresign.coop"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<InformationIcon :size="20" />
					</template>
					{{ t('libresign', 'Learn more') }}
				</NcButton>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script>
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'
import HeartIcon from 'vue-material-design-icons/Heart.vue'
import InformationIcon from 'vue-material-design-icons/Information.vue'

import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'SupportProject',
	components: {
		CurrencyUsdIcon,
		HeartIcon,
		InformationIcon,
		NcButton,
		NcSettingsSection,
	},
	data() {
		return {
			name: t('libresign', 'Support LibreSign'),
			description: t('libresign', 'Help us continue developing and maintaining LibreSign'),
		}
	},
}
</script>

<style scoped>
.support-project-content {
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-width: 600px;
}

.support-message {
	margin: 0;
	color: var(--color-text-lighter);
	line-height: 1.5;
}

.support-buttons {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
}
</style>
