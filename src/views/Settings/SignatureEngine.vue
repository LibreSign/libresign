<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="signature-engine-content">
			<NcSelect input-id="signatureEngine"
				:aria-label-combobox="description"
				:clearable="false"
				:value="value"
				:options="options"
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
	name: 'SignatureEngine',
	components: {
		NcSettingsSection,
		NcSelect,
	},
	data() {
		return {
			name: t('libresign', 'Signature engine'),
			description: t('libresign', 'Select the signature engine to sign the documents'),
			value: [],
			options: [
				{ id: 'JSignPdf', label: 'JSignPdf' },
				{ id: 'PhpNative', label: 'PHP native' },
			],
		}
	},
	beforeMount() {
		const currentOption = {}
		currentOption.id = loadState('libresign', 'signature_engine', 'JSignPdf')
		if (currentOption.id === 'JSignPdf') {
			currentOption.label = 'JSignPdf'
		} else {
			currentOption.label = 'PHP native'
		}
		this.value = [currentOption]
	},
	methods: {
		saveEngine(selected) {
			this.value = selected
			OCP.AppConfig.setValue('libresign', 'signature_engine', selected.id, {
				success() {
					emit('libresign:signature-engine:changed', selected.id)
				},
			})
		},
	},
}
</script>
<style scoped>
.signature-engine-content{
	display: flex;
	flex-direction: column;
}
</style>
