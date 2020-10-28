<template>
	<SettingsSection
		:title="title"
		:description="description">
		<AdminFormSigner />
	</SettingsSection>
</template>

<script>
import AdminFormSigner from './AdminFormSigner'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Settings',
	components: {
		AdminFormSigner,
		SettingsSection,
	},
	data() {
		return {
			loading: true,
			title: t('signer', 'Assinador Digital: Dados Certificado Raiz'),
			description: t('signer', 'Para gerar novas assinaturas, é preciso primeiro gerar o ceritificado raiz'),
		}
	},
	computed: {},
	async mounted() {},

	methods: {},
}

</script>
<style scoped>
#signer-admin-settings {
	width: 100vw;
	padding: 20px;
	padding-top: 70px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	align-items: center;
}
</style>
