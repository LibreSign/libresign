<template>
	<SettingsSection
		:title="title"
		:description="description">
		<AdminFormLibresign />
	</SettingsSection>
</template>

<script>
import AdminFormLibresign from './AdminFormLibresign'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Settings',
	components: {
		AdminFormLibresign,
		SettingsSection,
	},
	data() {
		return {
			loading: true,
			title: t('libresign', 'Assinador Digital: Dados Certificado Raiz'),
			description: t('libresign', 'Para gerar novas assinaturas, é preciso primeiro gerar o ceritificado raiz'),
		}
	},
	computed: {},
	async mounted() {},

	methods: {},
}

</script>
<style scoped>
#libresign-admin-settings {
	width: 100vw;
	padding: 20px;
	padding-top: 70px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	align-items: center;
}
</style>
