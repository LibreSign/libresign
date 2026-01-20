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
					:size="30"
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
	margin: 0 10px 0 12px;

	.action-item__menutoggle {
		padding: 0;
		min-height: 0;
		min-width: 0;
		overflow: visible;

		.button-vue__wrapper {
			padding: 0;
		}

		.button-vue__icon {
			width: auto;
			height: auto;
		}
	}
}

.signer-trigger {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	min-width: 56px;
	min-height: 34px;
	border-radius: 6px;
	background: rgba(255, 255, 255, 0.08);
	border: 1px solid rgba(255, 255, 255, 0.16);
	color: #ffffff;
	font-size: 12px;
	line-height: 1;
	white-space: nowrap;

	.nc-icon-svg-wrapper {
		flex: 0 0 auto;
		color: #ffffff;
	}
}

.signer-avatar {
	--avatar-size: 30px;
	flex: 0 0 var(--avatar-size);
}

.signer-option-icon {
	display: inline-flex;
	margin-right: 6px;
}
</style>
