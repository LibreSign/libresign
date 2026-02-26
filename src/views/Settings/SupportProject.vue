<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Support LibreSign')"
		:description="t('libresign', 'Help us continue developing and maintaining LibreSign')">
		<div class="support-project-content">
			<p class="support-message">
				{{ t('libresign', 'LibreSign is developed with ❤️ by LibreCode coop. Your support helps us maintain and improve this free and open-source project.') }}
			</p>
			<div class="support-buttons">
				<NcButton variant="primary"
					href="https://github.com/sponsors/libresign"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<NcIconSvgWrapper :path="mdiHeart" :size="20" />
					</template>
					{{ t('libresign', 'Sponsor on GitHub') }}
				</NcButton>
				<NcButton href="https://buy.stripe.com/eVqfZibhx8QO3LseWc2kw00"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCurrencyUsd" :size="20" />
					</template>
					{{ t('libresign', 'Donate via Stripe') }}
				</NcButton>
				<NcButton href="https://libresign.coop"
					target="_blank"
					rel="noopener noreferrer">
					<template #icon>
						<NcIconSvgWrapper :path="mdiInformation" :size="20" />
					</template>
					{{ t('libresign', 'Learn more') }}
				</NcButton>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script>

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '@nextcloud/l10n'
import {
	mdiHeart,
	mdiCurrencyUsd,
	mdiInformation,
} from '@mdi/js'

export default {
	name: 'SupportProject',
	components: {
		NcButton,
		NcSettingsSection,
		NcIconSvgWrapper,
	},
	setup() {
		return {
			mdiHeart,
			mdiCurrencyUsd,
			mdiInformation,
		}
	},
	methods: {
		t,
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
