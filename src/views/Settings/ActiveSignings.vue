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
				<NcButton variant="tertiary"
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

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { mdiRefresh } from '@mdi/js'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import type { operations } from '../../types/openapi/openapi-administration'

type ActiveSigningsResponse = operations['admin-get-active-signings']['responses'][200]['content']['application/json']
type SigningItem = ActiveSigningsResponse['ocs']['data']['data'][number]

defineOptions({
	name: 'ActiveSignings',
})

const signings = ref<SigningItem[]>([])
const loading = ref(false)
const autoRefresh = ref(true)
const lastUpdateTime = ref('')
const refreshInterval = ref<ReturnType<typeof setInterval> | null>(null)

const shouldRefresh = computed(() => autoRefresh.value)

async function refresh() {
	loading.value = true
	try {
		const response = await axios.get<ActiveSigningsResponse>(
			generateOcsUrl('/apps/libresign/api/v1/admin/active-signings'),
		)
		signings.value = response.data.ocs.data.data
		updateLastRefreshTime()
	} catch (error: unknown) {
		console.error('Failed to fetch active signings:', error)
		signings.value = []
	} finally {
		loading.value = false
	}
}

function startAutoRefresh() {
	if (refreshInterval.value) {
		clearInterval(refreshInterval.value)
	}
	refreshInterval.value = setInterval(() => {
		void refresh()
	}, 10000)
}

function stopAutoRefresh() {
	if (refreshInterval.value) {
		clearInterval(refreshInterval.value)
		refreshInterval.value = null
	}
}

function updateLastRefreshTime() {
	lastUpdateTime.value = Moment().format('HH:mm:ss')
}

function formatTime(timestamp: number) {
	return Moment(timestamp * 1000).fromNow()
}

function getFileUrl(fileId: number) {
	return `/index.php/apps/files/?fileid=${fileId}`
}

watch(autoRefresh, (newValue) => {
	if (newValue) {
		startAutoRefresh()
	} else {
		stopAutoRefresh()
	}
})

onMounted(() => {
	void refresh()
	if (autoRefresh.value) {
		startAutoRefresh()
	}
})

onBeforeUnmount(() => {
	stopAutoRefresh()
})

defineExpose({
	t,
	mdiRefresh,
	signings,
	loading,
	autoRefresh,
	lastUpdateTime,
	refreshInterval,
	shouldRefresh,
	refresh,
	startAutoRefresh,
	stopAutoRefresh,
	updateLastRefreshTime,
	formatTime,
	getFileUrl,
})
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
