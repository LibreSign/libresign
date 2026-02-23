<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signers-list-wrapper">
		<NcListItem v-for="(signer, index) in signers"
			:key="index"
			class="signer-item"
			:compact="compact">
			<template #icon>
				<NcAvatar disable-menu
					:is-no-user="!signer.userId"
					:size="compact ? 32 : 44"
					:user="signer.userId || signer.displayName || signer.email"
					:display-name="signer.displayName || signer.email" />
			</template>
			<template #name>
				<div class="signer-info">
					<strong>{{ signer.displayName || signer.email }}</strong>
					<span v-if="signer.signed" class="signer-status signed">
						<NcIconSvgWrapper :path="mdiCheckCircle" :size="16" class="status-icon" />
						{{ t('libresign', 'Signed on') }} {{ dateFromSqlAnsi(signer.signed) }}
					</span>
					<span v-else class="signer-status pending">
						<NcIconSvgWrapper :path="mdiClockOutline" :size="16" class="status-icon" />
						{{ t('libresign', 'Awaiting signature') }}
					</span>
				</div>
			</template>
		</NcListItem>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import {
	mdiCheckCircle,
	mdiClockOutline,
} from '@mdi/js'
import Moment from '@nextcloud/moment'

export default {
	name: 'SignersList',
	components: {
		NcAvatar,
		NcIconSvgWrapper,
		NcListItem,
	},
	props: {
		signers: {
			type: Array,
			required: true,
	},
		compact: {
			type: Boolean,
			default: false,
	},
	},
	setup() {
		return {
			mdiCheckCircle,
			mdiClockOutline,
			t,
		}
	},
	methods: {
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
	},
}
</script>

<style lang="scss" scoped>
.signers-list-wrapper {
	.signer-item {
		margin-bottom: 8px;

		&:last-child {
			margin-bottom: 0;
		}

		:deep(.list-item-content__wrapper) {
			padding: 4px 0;
		}
	}
}

.signer-info {
	display: flex;
	flex-direction: column;
	gap: 4px;

	strong {
		color: var(--color-main-text);
	}
}

.signer-status {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);

	.status-icon {
		flex-shrink: 0;
	}

	&.signed {
		color: var(--color-success-text);

		.status-icon {
			color: var(--color-success);
		}
	}

	&.pending {
		color: var(--color-text-maxcontrast);
	}
}
</style>
