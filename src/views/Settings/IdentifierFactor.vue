<template>
	<NcSettingsSection :name="name" :description="description">
		<div v-for="(option, index) in options"
			:key="index">
			<hr v-if="index != 0">
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="option.enabled"
				@update:checked="save()">
				{{ option.friendly_name }}
			</NcCheckboxRadioSwitch>
			<div v-if="option.enabled">
				<fieldset v-if="option.name === 'email'" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch :checked.sync="option.can_create_account"
						@update:checked="save()">
						{{ t('libresign', 'Request to create account when the user does not have an account') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<fieldset v-if="false" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch :checked.sync="option.mandatory"
						@update:checked="save()">
						{{ t('libresign', 'Make this method required') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<fieldset class="settings-section__sub-section">
					{{ t('libresign', 'Signature methods') }}
					<NcCheckboxRadioSwitch v-for="(method, id) in option.signatureMethods"
						:key="id"
						type="switch"
						:checked.sync="method.enabled"
						@update:checked="save()">
						{{ method.label }}
					</NcCheckboxRadioSwitch>
				</fieldset>
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'IdentifierFactor',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		const identifyMethod = loadState('libresign', 'identify_methods')

		return {
			name: t('libresign', 'Identify factors'),
			description: t('libresign', 'Ways to identify a person who will sign a document.'),
			isEmpty: identifyMethod.length === 0,
			options: identifyMethod,
		}
	},
	mounted() {
		this.flagAccountIfAllDisabled()
		this.flagFirstSignatureMethodIfAllDisabled()
	},
	methods: {
		flagAccountIfAllDisabled() {
			const allDisabled = this.options
				.filter(item => item.enabled)
				.length === 0
			if (allDisabled) {
				this.options
					.filter(item => item.name === 'account')
					.reduce(o => o)
					.enabled = true
			}
		},
		flagFirstSignatureMethodIfAllDisabled() {
			this.options.forEach(item => {
				const allDisabled = Object.values(item.signatureMethods)
					.filter(item => item.enabled)
					.length === 0
				if (allDisabled) {
					// Enable the first signature method
					Object.keys(item.signatureMethods).every(methodId => {
						item.signatureMethods[methodId].enabled = true
						return false
					})
				}
			})
		},
		save() {
			this.flagAccountIfAllDisabled()
			this.flagFirstSignatureMethodIfAllDisabled()
			// Get only enabled
			let props = this.options.filter(item => item.enabled)
			// Remove label from signature method, we don't need to save this
			props = JSON.parse(JSON.stringify(props))
				.map(item => {
					Object.keys(item.signatureMethods).forEach(id => {
						Object.keys(item.signatureMethods[id]).forEach(signatureMethdoPropName => {
							if (signatureMethdoPropName === 'label') {
								delete item.signatureMethods[id][signatureMethdoPropName]
							}
						})
					})
					return item
				})
			OCP.AppConfig.setValue('libresign', 'identify_methods',
				JSON.stringify(props),
			)
		},
	},
}
</script>
<style lang="scss" scoped>
.settings-section{
	&__sub-section {
		display: flex;
		flex-direction: column;
		gap: 4px;

		margin-inline-start: 44px;
		margin-block-end: 12px
	}
}
@media only screen and (max-width: 350px) {
	// ensure no overflow happens on small devices (required for WCAG)
	.sharing {
		&__sub-section {
			margin-inline-start: 14px;
		}
	}
}
</style>
