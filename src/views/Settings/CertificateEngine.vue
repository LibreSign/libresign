<template>
	<NcSettingsSection :title="title" :description="description">
		<div class="certificate-engine-content">
			<label for="certificateEngine" class="form-heading--required">{{ t('libresign', 'Certificate engine') }}</label>
			<NcSelect v-bind="getCertificateEngines"
				@input="saveEngine" />
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { emit } from '@nextcloud/event-bus'
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
				value: [
					{
						id: 0,
						label: '',
					}
				],
				options: [
					{ id: 'cfssl', label: 'CFSSL' },
					{ id: 'openssl', label: 'OpenSSL' },
					{ id: 'none', label: t('libresign', 'I will not use root certificate') },
				],
			},
		}
	},
	computed: {
		getCertificateEngines() {
			let currentOption = {}
			currentOption.id = loadState('libresign', 'certificate_engine')
			if (currentOption.id === 'openssl') {
				currentOption.label = 'OpenSSL'
			} else if (currentOption.id === 'cfssl') {
				currentOption.label = 'CFSSL'
			} else {
				currentOption.label = t('libresign', 'I will not use root certificate')
			}
			this.certificateEngines.value = [currentOption]
			return this.certificateEngines
		},
	},
	methods: {
		saveEngine(selected) {
			this.certificateEngines.value = selected
			OCP.AppConfig.setValue('libresign', 'certificate_engine', selected.id, {
				success() {
					emit('libresign:certificate-engine:changed', selected.id)
				},
			})
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
