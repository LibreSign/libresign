<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signing-progress">
		<div class="signing-progress__header">
			<div class="signing-progress__status">
				<div v-if="isLoading" class="icon-loading-small" />
				<NcIconSvgWrapper v-else class="signing-progress__icon" :path="statusIconPath" />
				<span class="signing-progress__status-text">{{ statusText }}</span>
			</div>
		</div>

		<div v-if="progress && isInProgress" class="signing-progress__details">
			<div v-if="progress.total > 0" class="signing-progress__bar-container">
				<div class="signing-progress__bar-label">
					{{ t('libresign', 'Progress: {signed} of {total} signed', { signed: progress.signed, total: progress.total }) }}
				</div>
				<div class="signing-progress__bar">
					<div
						class="signing-progress__bar-fill"
						:style="{ width: progressPercentage + '%' }" />
				</div>
				<div class="signing-progress__bar-percentage">
					{{ progressPercentage }}%
				</div>
			</div>

			<div v-if="progress.files && progress.files.length > 0" class="signing-progress__files">
				<div class="signing-progress__files-title">
					{{ t('libresign', 'Files') }}
				</div>
				<ul class="signing-progress__files-list">
					<li
						v-for="file in progress.files"
						:key="file.uuid"
						class="signing-progress__file-item">
						<div class="signing-progress__file-status">
							<div v-if="file.isSigned" class="icon-checkmark" />
							<div v-else class="icon-loading-small" />
						</div>
						<span class="signing-progress__file-name">{{ file.name }}</span>
						<span class="signing-progress__file-progress">
							{{ file.signedCount }}/{{ file.totalSigners }}
						</span>
					</li>
				</ul>
			</div>

			<div v-else-if="progress.signers && progress.signers.length > 0" class="signing-progress__signers">
				<div class="signing-progress__signers-title">
					{{ t('libresign', 'Signers') }}
				</div>
				<ul class="signing-progress__signers-list">
					<li
						v-for="signer in progress.signers"
						:key="signer.id"
						class="signing-progress__signer-item">
						<div class="signing-progress__signer-status">
							<div v-if="signer.signed" class="icon-checkmark" />
							<div v-else class="icon-close" />
						</div>
						<span class="signing-progress__signer-name">{{ signer.displayName }}</span>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { FILE_STATUS } from '../constants.js'
import { getStatusIcon } from '../utils/fileStatus.js'

export default {
	name: 'RequestSigningProgress',
	components: {
		NcIconSvgWrapper,
	},
	props: {
		status: {
			type: Number,
			required: true,
		},
		statusText: {
			type: String,
			required: false,
			default: '',
		},
		progress: {
			type: Object,
			required: false,
			default: null,
		},
		isLoading: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	setup() {
		return { t }
	},
	computed: {
		isInProgress() {
			return this.status === FILE_STATUS.SIGNING_IN_PROGRESS
		},

		statusIconPath() {
			return getStatusIcon(this.status) || ''
		},

		progressPercentage() {
			if (!this.progress || this.progress.total === 0) {
				return 0
			}
			return Math.round((this.progress.signed / this.progress.total) * 100)
		},
	},
}
</script>

<style lang="scss" scoped>
.signing-progress {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 12px;
	}

	&__status {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__icon {
		width: 24px;
		height: 24px;
		display: flex;
		align-items: center;
		justify-content: center;

		:deep(svg) {
			width: 100%;
			height: 100%;
		}
	}

	&__status-text {
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__details {
		margin-top: 12px;
	}

	&__bar-container {
		margin-bottom: 16px;
	}

	&__bar-label {
		font-size: var(--default-font-size);
		color: var(--color-text-lighter);
		margin-bottom: 4px;
	}

	&__bar {
		height: 8px;
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius);
		overflow: hidden;
		margin-bottom: 4px;
	}

	&__bar-fill {
		height: 100%;
		background-color: var(--color-primary);
		transition: width 0.3s ease;
	}

	&__bar-percentage {
		text-align: end;
		font-size: calc(var(--default-font-size) * 0.9);
		color: var(--color-text-lighter);
	}

	&__files,
	&__signers {
		margin-top: 16px;
	}

	&__files-title,
	&__signers-title {
		font-weight: 600;
		margin-bottom: 8px;
		color: var(--color-main-text);
	}

	&__files-list,
	&__signers-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	&__file-item,
	&__signer-item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 6px 0;
		border-bottom: 1px solid var(--color-border);

		&:last-child {
			border-bottom: none;
		}
	}

	&__file-status,
	&__signer-status {
		width: 20px;
		height: 20px;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	&__file-name,
	&__signer-name {
		flex-grow: 1;
		color: var(--color-main-text);
	}

	&__file-progress {
		font-size: calc(var(--default-font-size) * 0.9);
		color: var(--color-text-lighter);
	}

	.icon-checkmark {
		color: var(--color-success);
	}

	.icon-close {
		color: var(--color-text-lighter);
	}

	.icon-loading-small {
		animation: rotate 1s linear infinite;
	}
}

@keyframes rotate {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}
</style>
