<!-- eslint-disable vue/no-v-html -->

<template>
	<NcSettingsSection :name="name" :description="description">
		<table class="grid">
			<tbody>
				<tr class="group-header">
					<th>{{ t('libresign', 'Status') }}</th>
					<th>{{ t('libresign', 'Message') }}</th>
					<th>{{ t('libresign', 'Resource') }}</th>
					<th>{{ t('libresign', 'Tip') }}</th>
				</tr>
				<tr v-for="(row, index) in items" :key="index">
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
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

export default {
	name: 'ConfigureCheck',
	components: {
		NcSettingsSection,
	},
	data: () => ({
		name: t('libresign', 'Configuration check'),
		description: t('libresign', 'Status of setup'),
		items: [],
	}),
	mounted() {
		this.checkSetup()
		this.$root.$on('config-check', data => {
			this.checkSetup()
		})
		subscribe('libresign:certificate-engine:changed', this.checkSetup)
		subscribe('libresign:signature-engine:changed', this.checkSetup)
	},
	beforeUnmount() {
		unsubscribe('libresign:certificate-engine:changed')
		unsubscribe('libresign:signature-engine:changed')
	},
	methods: {
		async checkSetup() {
			const response = await axios.get(
				generateOcsUrl('/apps/libresign/api/v1/admin/configure-check'),
			)
			this.items = response.data
			this.$root.$emit('after-config-check', response.data)
		},
	},
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
</style>
