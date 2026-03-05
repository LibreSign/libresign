<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Active Signings')"
		:description="t('libresign', 'Monitor documents currently being signed')">
		<div class="active-signings-content">
			<!-- Auto-refresh toggle -->
			<div class="active-signings__controls">
				<NcCheckboxRadioSwitch type="switch"
					v-model="autoRefresh"
					:disabled="loading">
					{{ t('libresign', 'Auto-refresh') }}
				</NcCheckboxRadioSwitch>
				<NcButton type="tertiary"
					:disabled="loading"
					@click="refresh">
					<template #icon>
						<NcIconSvgWrapper :path="mdiRefresh" :size="20" />
					</template>
					{{ t('libresign', 'Refresh') }}
				</NcButton>
			</div>

			<!-- Loading indicator -->
			<NcLoadingIcon v-if="loading && signings.length === 0"
				class="active-signings__loading-icon"
				:size="32" />

			<!-- Empty state -->
			<div v-if="!loading && signings.length === 0" class="active-signings__empty">
				<p>{{ t('libresign', 'No documents are currently being signed') }}</p>
			</div>

			<!-- Signings list -->
			<div v-else class="active-signings__list">
				<div class="active-signings__list-header">
					<span class="active-signings__col-file">{{ t('libresign', 'File') }}</span>
					<span class="active-signings__col-signer">{{ t('libresign', 'Signer') }}</span>
					<span class="active-signings__col-time">{{ t('libresign', 'Last Updated') }}</span>
				</div>

				<div v-for="signing in signings"
					:key="signing.id"
					class="active-signings__item">
					<span class="active-signings__col-file">
						<a :href="getFileUrl(signing.id)" :title="signing.name">
							{{ signing.name }}
						</a>
					</span>
					<span class="active-signings__col-signer">
						{{ signing.signerDisplayName || signing.signerEmail }}
					</span>
					<span class="active-signings__col-time">
						{{ formatTime(signing.updatedAt) }}
					</span>
				</div>
			</div>

			<!-- Last update info -->
			<div v-if="signings.length > 0" class="active-signings__footer">
				<small>{{ t('libresign', 'Last updated: {time}', { time: lastUpdateTime }) }}</small>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import {
	mdiRefresh,
} from '@mdi/js'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'ActiveSignings',
	components: {
		NcButton,
		NcSettingsSection,
		NcIconSvgWrapper,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
	},
	setup() {
		return {
			mdiRefresh,
		}
	},
	data() {
		return {
			signings: [],
			loading: false,
			autoRefresh: true,
			lastUpdateTime: '',
			refreshInterval: null,
		}
	},
	computed: {
		// Auto-refresh every 10 seconds when enabled
		shouldRefresh() {
			return this.autoRefresh
		},
	},
	watch: {
		autoRefresh(newValue) {
			if (newValue) {
				this.startAutoRefresh()
			} else {
				this.stopAutoRefresh()
			}
		},
	},
	mounted() {
		this.refresh()
		if (this.autoRefresh) {
			this.startAutoRefresh()
		}
	},
	beforeUnmount() {
		this.stopAutoRefresh()
	},
	methods: {
		t,

		async refresh() {
			this.loading = true
			try {
				const response = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/admin/active-signings')
				)
				this.signings = response.data.ocs.data || []
				this.updateLastRefreshTime()
			} catch (error) {
				console.error('Failed to fetch active signings:', error)
				this.signings = []
			} finally {
				this.loading = false
			}
		},

		startAutoRefresh() {
			if (this.refreshInterval) {
				clearInterval(this.refreshInterval)
			}
			this.refreshInterval = setInterval(() => {
				this.refresh()
			}, 10000) // Refresh every 10 seconds
		},

		stopAutoRefresh() {
			if (this.refreshInterval) {
				clearInterval(this.refreshInterval)
				this.refreshInterval = null
			}
		},

		updateLastRefreshTime() {
			this.lastUpdateTime = Moment().format('HH:mm:ss')
		},

		formatTime(timestamp) {
			return Moment(timestamp * 1000).fromNow()
		},

		getFileUrl(fileId) {
			// Build URL to view the file
			return `/index.php/apps/files/?fileid=${fileId}`
		},
	},
}
</script>

<style lang="scss" scoped>
.active-signings-content {
	padding: 12px 0;
}

.active-signings__controls {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 16px;
}

.active-signings__loading-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24px;
}

.active-signings__empty {
	padding: 24px;
	text-align: center;
	color: var(--color-text-lighter);
}

.active-signings__list {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.active-signings__list-header {
	display: grid;
	grid-template-columns: 1fr 1fr 150px;
	gap: 12px;
	padding: 12px;
	background-color: var(--color-background-hover);
	border-bottom: 1px solid var(--color-border);
	font-weight: 600;
}

.active-signings__item {
	display: grid;
	grid-template-columns: 1fr 1fr 150px;
	gap: 12px;
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	align-items: center;

	&:last-child {
		border-bottom: none;
	}

	&:hover {
		background-color: var(--color-background-hover);
	}
}

.active-signings__col-file {
	a {
		color: var(--color-primary);
		text-decoration: none;

		&:hover {
			text-decoration: underline;
		}
	}
}

.active-signings__col-signer {
	color: var(--color-text);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.active-signings__col-time {
	text-align: right;
	color: var(--color-text-lighter);
	font-size: calc(var(--default-font-size) * 0.9);
}

.active-signings__footer {
	padding: 12px;
	text-align: right;
	color: var(--color-text-lighter);
}
</style>
