<template>
	<NcSettingsSection :title="title">
		<p>
			<NcSelect v-model="signatureMethod"
				:options="allowedSignatureMethods"
				input-id="selectIdentificationDefault"
				@input="save()" />
		</p>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'SignatureMethod',
	components: {
		NcSettingsSection,
		NcSelect,
	},
	data() {
		return {
			title: t('libresign', 'Default signature method'),
			signatureMethod: loadState('libresign', 'signature_method', {}),
			allowedSignatureMethods: loadState('libresign', 'allowed_signature_methods', []),
		}
	},
	methods: {
		save() {
			OCP.AppConfig.setValue('libresign', 'signature_method',
				JSON.stringify(this.signatureMethod),
			)
		},
	},
}
</script>
