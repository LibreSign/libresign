<template>
	<NcSettingsSection :title="title" :description="description">
		<div class="certificate-engine-content">
			<label for="certificateEngine" class="form-heading--required">{{ t('libresign', 'Certificate engine') }}</label>
			<NcSelect v-bind="certificateEngines"
				@input="saveEngine" />
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'CertificateEngine',
	components: {
		NcSettingsSection,
		NcSelect,
	},
	data() {
		return {
			title: t('libresign', 'Certificate engine'),
			description: t('libresign', 'Certificate engine to generate the root certificate'),
			certificateEngines: {
				inputId: 'certificateEngine',
				placeholder: t('libresign', 'Select the certificate engine to generate the root certificate'),
				clearable: false,
				// @todo fix this removing the ternary operator
				value: {
					id: loadState('libresign', 'certificate_engine'),
					label: loadState('libresign', 'certificate_engine') === 'openssl' ? 'OpenSSL' : 'CFSSL',
				},
				options: [
					{ id: 'cfssl', label: 'CFSSL' },
					{ id: 'openssl', label: 'OpenSSL' },
				],
			},
		}
	},
	methods: {
		saveEngine(selected) {
			this.certificateEngines.value = selected
			OCP.AppConfig.setValue('libresign', 'certificate_engine', selected.id)
		},
	},
}
</script>
<style scoped>
.certificate-engine-content{
	display: flex;
	flex-direction: column;
}
</style>
