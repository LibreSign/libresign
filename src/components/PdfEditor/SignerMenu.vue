<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcActions v-if="show"
		class="signer-menu"
		:force-menu="true"
		:menu-name="''"
		variant="tertiary-no-background">
		<template #icon>
			<span class="signer-trigger">
				<NcAvatar
					class="signer-avatar"
					:size="28"
					:is-no-user="true"
					:display-name="label(currentSigner)" />
				<NcIconSvgWrapper :path="mdiChevronDown" :size="18" />
			</span>
		</template>
		<NcActionButton
			v-for="signer in signers"
			:key="signerKey(signer)"
			:close-after-click="true"
			@click="selectSigner(signer)">
			<template #icon>
				<span class="signer-option-icon">
					<NcAvatar
						class="signer-avatar"
						:size="30"
						:is-no-user="true"
						:display-name="label(signer)" />
				</span>
			</template>
			{{ label(signer) }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { mdiChevronDown } from '@mdi/js'

export default {
	name: 'SignerMenu',
	components: {
		NcActionButton,
		NcActions,
		NcAvatar,
		NcIconSvgWrapper,
	},
	props: {
		signers: {
			type: Array,
			default: () => [],
	},
		currentSigner: {
			type: Object,
			default: null,
	},
		getSignerLabel: {
			type: Function,
			default: null,
	},
		show: {
			type: Boolean,
			default: true,
	},
	},
	data() {
		return {
			mdiChevronDown,
		}
	},
	methods: {
		t,
		label(signer) {
			if (this.getSignerLabel) {
				return this.getSignerLabel(signer)
			}
			if (!signer) {
				return ''
			}
			return signer.displayName || signer.name || signer.email || signer.id || ''
		},
		signerKey(signer) {
			return signer?.signRequestId || signer?.uuid || signer?.id || signer?.email || ''
		},
		selectSigner(signer) {
			this.$emit('change', signer)
		},
	},
}
</script>

<style lang="scss">
.signer-menu {
	display: inline-flex;

	.action-item__menutoggle {
		padding: 0 !important;
		min-width: 80px !important;
		width: auto !important;
		height: 40px !important;

		.button-vue__wrapper {
			padding: 0 !important;
			width: 100% !important;
		}

		.button-vue__icon {
			width: 100% !important;
			height: 100% !important;
			min-width: 80px !important;
		}
	}
}

.signer-trigger {
	display: inline-flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 6px 8px;
	border-radius: 6px;
	background: rgba(255, 255, 255, 0.08);
	border: 1px solid rgba(255, 255, 255, 0.16);
	color: #fff;
	font-size: 13px;
	transition: background-color 0.1s ease, border-color 0.1s ease;

	&:hover {
		background: rgba(255, 255, 255, 0.15);
		border-color: rgba(255, 255, 255, 0.24);
	}

	.nc-icon-svg-wrapper {
		flex-shrink: 0;
		opacity: 0.7;
	}
}

.signer-avatar {
	flex-shrink: 0;
}

.signer-option-icon {
	display: inline-flex;
	margin-right: 6px;
}
</style>
