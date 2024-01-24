<template>
	<NcSettingsSection :name="name">
		<p>
			<NcCheckboxRadioSwitch v-for="(method, id) in signatureMethods"
				:key="id"
				type="switch"
				:checked.sync="method.enabled"
				@update:checked="save()">
				{{ method.label }}
			</NcCheckboxRadioSwitch>
		</p>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'SignatureMethods',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			name: t('libresign', 'Signature methods'),
			signatureMethods: loadState('libresign', 'signature_methods', []),
		}
	},
	methods: {
		save() {
			const sumEnabled = Object.keys(this.signatureMethods).reduce(
				(accumulator, id) => {
					return (this.signatureMethods[id]?.enabled ? 1 : 0) + accumulator
				},
				0,
			)
			if (sumEnabled === 0) {
				this.signatureMethods.password.enabled = true
			}
			const props = {}
			Object.keys(this.signatureMethods).forEach(id => {
				props[id] = { enabled: this.signatureMethods[id].enabled }
			})
			OCP.AppConfig.setValue('libresign', 'signature_methods',
				JSON.stringify(props),
			)
		},
	},
}
</script>
