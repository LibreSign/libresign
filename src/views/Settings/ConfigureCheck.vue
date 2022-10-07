<template>
	<SettingsSection :title="title" :description="description">
		<div class="settings-section">
			<ul>
				<li v-if="items.success">
					<ul class="items">
						<li v-for="(item, index) in items.success" :key="index" class="success">
							{{ item }}
						</li>
					</ul>
				</li>
				<li v-if="items.errors">
					<ul class="items">
						<li v-for="(item, index) in items.errors" :key="index" class="error">
							{{ item }}
						</li>
					</ul>
				</li>
			</ul>
		</div>
	</SettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ConfigureCheck',
	components: {
		SettingsSection,
	},
	data: () => ({
		title: t('libresign', 'Check configure'),
		description: t('libresign', 'Status of setup'),
		items: [],
	}),
	mounted() {
		this.checkSetup()
	},
	methods: {
		async checkSetup() {
			const response = await axios.get(
				generateUrl('/apps/libresign/api/0.1/admin/configure-check')
			)
			this.items = response.data
		},
	},
}
</script>
<style lang="scss" scoped>
.items {
	li {
		list-style: initial;
		list-style-type: initial;
		list-style-position: inside;
	}
	.success {
		color: green;
	}
	.error {
		color: red;
	}
}
</style>
