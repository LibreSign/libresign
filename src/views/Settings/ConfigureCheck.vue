<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!-- eslint-disable vue/no-v-html -->

<template>
	<NcSettingsSection v-if="configureCheckStore.items.length > 0" :name="name" :description="description">
		<table class="grid">
			<tbody>
				<tr class="group-header">
					<th>{{ t('libresign', 'Status') }}</th>
					<th>{{ t('libresign', 'Message') }}</th>
					<th>{{ t('libresign', 'Resource') }}</th>
					<th>{{ t('libresign', 'Tip') }}</th>
				</tr>
				<tr v-for="(row, index) in configureCheckStore.items" :key="index">
					<td :class="row.status">
						{{ row.status }}
					</td>
					<td v-html="row.message" />
					<td>{{ row.resource }}</td>
					<td>{{ row.tip }}</td>
				</tr>
			</tbody>
		</table>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { useConfigureCheckStore } from '../../store/configureCheck.js'

export default {
	name: 'ConfigureCheck',
	components: {
		NcSettingsSection,
	},
	setup() {
		const configureCheckStore = useConfigureCheckStore()
		return { configureCheckStore }
	},
	data: () => ({
		name: t('libresign', 'Configuration check'),
		description: t('libresign', 'Status of setup'),
	}),
}
</script>
<style lang="scss" scoped>
table {
	white-space: inherit;
}
.success {
	color: green;
}
.error {
	color: red;
}
.info {
	color: orange;
}
</style>
