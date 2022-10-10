<template>
	<NcSettingsSection :title="title" :description="description">
		<table class="grid">
			<tbody>
				<tr class="group-header">
					<th>{{ t('libresign', 'Status') }}</th>
					<th>{{ t('libresign', 'Message') }}</th>
					<th>{{ t('libresign', 'Resource') }}</th>
					<th>{{ t('libresign', 'Tip') }}</th>
				</tr>
				<tr v-for="(row, index) in items" :key="index">
					<td
						:class=row.status
						>
						{{row.status}}
					</td>
					<td>{{row.message}}</td>
					<td>{{row.resource}}</td>
					<td>{{row.tip}}</td>
				</tr>
			</tbody>
		</table>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ConfigureCheck',
	components: {
		NcSettingsSection,
	},
	data: () => ({
		title: t('libresign', 'Check configure'),
		description: t('libresign', 'Status of setup'),
		items: [],
	}),
	mounted() {
		this.checkSetup()
		this.$root.$on('configCheck', data => {
			this.checkSetup()
		})
	},
	methods: {
		async checkSetup() {
			const response = await axios.get(
				generateUrl('/apps/libresign/api/0.1/admin/configure-check')
			)
			this.items = response.data
			this.$root.$emit('afterConfigCheck', response.data);
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
