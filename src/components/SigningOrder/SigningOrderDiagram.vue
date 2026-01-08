<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signing-order-diagram">
		<div class="diagram-content">
		<div class="stage">
			<div class="stage-number-placeholder"></div>
			<div class="stage-label">{{ t('libresign', 'SENDER') }}</div>
				<div class="stage-items">
					<div class="signer-node sender">
						<div class="signer-content">
							<NcAvatar :user="senderName" :size="40" />
							<div class="signer-info">
								<div class="signer-name">{{ senderName }}</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div v-for="order in uniqueOrders" :key="order" class="stage">
			<div class="stage-number">{{ order }}</div>
			<div class="stage-label-placeholder"></div>
			<div class="stage-items">
					<div v-for="(signer, index) in getSignersByOrder(order)"
						:key="`${order}-${index}`"
						class="signer-node"
						:class="{ signed: signer.signed }">
						<NcPopover>
							<template #trigger="{ attrs }">
								<button class="signer-content"
									v-bind="attrs"
									type="button">
									<div class="avatar-container">
										<NcAvatar :display-name="getSignerDisplayName(signer)" :size="40" />
										<div class="status-indicator" :class="getStatusClass(signer)" />
									</div>
									<div class="signer-info">
										<div class="signer-name">{{ getSignerDisplayName(signer) }}</div>
										<div class="signer-identify">{{ getSignerIdentify(signer) }}</div>
									</div>
								</button>
							</template>
							<div class="popover-content" tabindex="0">
								<div class="popover-row">
									<strong>{{ t('libresign', 'Name') }}:</strong>
									<span>{{ getSignerDisplayName(signer) }}</span>
								</div>
								<div class="popover-row">
									<strong>{{ t('libresign', 'Method') }}:</strong>
									<div class="method-chips">
										<NcChip v-for="method in getIdentifyMethods(signer)"
											:key="method"
											:text="method"
											:no-close="true" />
									</div>
								</div>
								<div class="popover-row">
									<strong>{{ t('libresign', 'Contact') }}:</strong>
									<span>{{ getSignerIdentify(signer) }}</span>
								</div>
								<div class="popover-row">
									<strong>{{ t('libresign', 'Status') }}:</strong>
									<NcChip :text="getStatusLabel(signer)"
										:type="getChipType(signer)"
										:icon-path="getStatusIconPath(signer)"
										:no-close="true" />
								</div>
								<div class="popover-row">
									<strong>{{ t('libresign', 'Order') }}:</strong>
									<span>{{ signer.signingOrder || 1 }}</span>
								</div>
								<div v-if="signer.signed && signer.signDate" class="popover-row">
									<strong>{{ t('libresign', 'Signed at') }}:</strong>
									<span>{{ formatDate(signer.signDate) }}</span>
								</div>
							</div>
						</NcPopover>
					</div>
				</div>
			</div>

		<div class="stage">
			<div class="stage-number-placeholder"></div>
			<div class="stage-label">{{ t('libresign', 'COMPLETED') }}</div>
				<div class="stage-items">
					<div class="signer-node completed">
						<div class="signer-icon">
							<Check :size="24" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { mdiCheckCircle, mdiClockOutline, mdiCircleOutline } from '@mdi/js'
import Check from 'vue-material-design-icons/Check.vue'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

export default {
	name: 'SigningOrderDiagram',
	components: {
		NcAvatar,
		NcChip,
		NcPopover,
		Check,
	},
	setup() {
		return {
			mdiCheckCircle,
			mdiClockOutline,
			mdiCircleOutline,
		}
	},
	props: {
		signers: {
			type: Array,
			required: true,
		},
		senderName: {
			type: String,
			default: '',
		},
	},
	computed: {
		uniqueOrders() {
			const orders = this.signers.map(s => s.signingOrder || 1)
			return [...new Set(orders)].sort((a, b) => a - b)
		},
	},
	methods: {
		getSignersByOrder(order) {
			return this.signers.filter(s => (s.signingOrder || 1) === order)
		},
		getSignerDisplayName(signer) {
			return signer.displayName || signer.identifyMethods?.[0]?.value || this.t('libresign', 'Signer')
		},
		getSignerIdentify(signer) {
			const method = signer.identifyMethods?.[0]?.method
			const value = signer.identifyMethods?.[0]?.value
			if (method === 'email') {
				return value
			}
			if (method === 'account') {
				return `v.${method}.${value}@colab.rio`
			}
			return value
		},
		getIdentifyMethods(signer) {
			return signer.identifyMethods?.map(method => method.method) || []
		},
		getStatusLabel(signer) {
			if (signer.signed) return this.t('libresign', 'Signed')
			if (signer.me?.status === 0) return this.t('libresign', 'Draft')
			return this.t('libresign', 'Pending')
		},
		getStatusIconPath(signer) {
			if (signer.signed) return this.mdiCheckCircle
			if (signer.me?.status === 0) return this.mdiCircleOutline
			return this.mdiClockOutline
		},
		getChipType(signer) {
			if (signer.signed) return 'success'
			if (signer.me?.status === 0) return 'secondary'
			return 'warning'
		},
		getStatusClass(signer) {
			if (signer.signed) return 'signed'
			if (signer.me?.status === 0) return 'draft'
			return 'pending'
		},
		formatDate(timestamp) {
			if (!timestamp) return ''
			const date = new Date(timestamp * 1000)
			return date.toLocaleString()
		},
	},
}
</script>

<style lang="scss" scoped>
.signing-order-diagram {
	padding: 20px 16px;
	max-height: 70vh;
	overflow-y: auto;

	@media (max-width: 512px) {
		padding: 16px 12px;
	}
}

.diagram-content {
	display: flex;
	flex-direction: column;
	position: relative;
	max-width: 700px;
	margin: 0 auto;
	padding-bottom: 16px;

	@media (max-width: 512px) {
		max-width: 100%;
		padding-bottom: 12px;
	}
}

.stage {
	display: flex;
	flex-direction: row;
	align-items: center;
	width: 100%;
	position: relative;
	padding: 16px 0;
	border-bottom: 1px solid var(--color-border);

	&:first-child {
		padding-top: 0;
	}

	&:last-child {
		border-bottom: none;
		padding-bottom: 0;
	}

	.stage-label,
	.stage-label-placeholder {
		width: 100px;
		flex-shrink: 0;

		@media (max-width: 512px) {
			width: 80px;
		}
	}

	.stage-label {
		font-size: 12px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;

		@media (max-width: 512px) {
			font-size: 11px;
		}
	}

	.stage-number,
	.stage-number-placeholder {
		font-size: 24px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		width: 50px;
		flex-shrink: 0;
		text-align: center;

		@media (max-width: 512px) {
			font-size: 20px;
			width: 40px;
		}
	}

	.stage-items {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		justify-content: center;
		flex: 1;

		@media (max-width: 512px) {
			gap: 10px;
		}
	}
}

.signer-node {
	background: var(--color-main-background);
	border: 2px solid var(--color-border);
	border-radius: 50px;
	min-width: 220px;
	max-width: 100%;
	transition: all 0.2s ease;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	cursor: pointer;

	@media (max-width: 512px) {
		min-width: 180px;
	}

	&:hover {
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
		transform: translateY(-1px);
	}

	.signer-content {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 20px;
		width: 100%;
		background: transparent;
		border: none;
		cursor: pointer;
		text-align: left;

		@media (max-width: 512px) {
			padding: 10px 16px;
			gap: 10px;
		}
	}

	&.sender {
		background: var(--color-primary-element-light);
		border-color: var(--color-primary-element);

		.signer-name {
			font-weight: 600;
		}
	}

	&.completed {
		background: var(--color-success);
		border-color: var(--color-success);
		justify-content: center;
		min-width: 64px;
		padding: 16px;

		@media (max-width: 512px) {
			min-width: 56px;
			padding: 14px;
		}

		.signer-icon {
			background: transparent;
			color: white;
			width: 32px;
			height: 32px;

			@media (max-width: 512px) {
				width: 28px;
				height: 28px;
			}
		}
	}

	.avatar-container {
		position: relative;
		flex-shrink: 0;

		.status-indicator {
			position: absolute;
			bottom: 0;
			right: 0;
			width: 12px;
			height: 12px;
			border: 2px solid var(--color-main-background);
			border-radius: 50%;

			@media (max-width: 512px) {
				width: 10px;
				height: 10px;
			}

			&.signed {
				background: var(--color-success);
			}

			&.pending {
				background: var(--color-warning);
			}

			&.draft {
				background: var(--color-text-maxcontrast);
			}
		}
	}

	.signer-icon {
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;

		@media (max-width: 512px) {
			width: 28px;
			height: 28px;
		}
	}

	.signer-info {
		flex: 1;
		min-width: 0;

		.signer-name {
			font-size: 14px;
			font-weight: 500;
			color: var(--color-main-text);
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;

			@media (max-width: 512px) {
				font-size: 13px;
			}
		}

		.signer-identify {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			margin-top: 2px;

			@media (max-width: 512px) {
				font-size: 11px;
			}
		}
	}
}

.popover-content {
	padding: 12px;
	min-width: 250px;
	outline: none;

	.popover-row {
		display: grid;
		grid-template-columns: 80px 1fr;
		gap: 8px;
		padding: 8px 0;
		border-bottom: 1px solid var(--color-border);
		align-items: start;

		&:last-child {
			border-bottom: none;
		}

		strong {
			font-size: 13px;
			color: var(--color-text-maxcontrast);
			padding-top: 2px;
		}

		span {
			font-size: 14px;
			color: var(--color-main-text);
			word-break: break-word;
		}

		.method-chips {
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}
	}
}
</style>
