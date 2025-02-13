<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div v-for="(identifyMethod, index) in identifyMethods"
			:key="identifyMethod.name">
			<hr v-if="index != 0">
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="identifyMethod.enabled"
				@update:checked="save()">
				{{ identifyMethod.friendly_name }}
			</NcCheckboxRadioSwitch>
			<div v-if="identifyMethod.enabled">
				<fieldset v-if="identifyMethod.name === 'email'" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch :checked.sync="identifyMethod.can_create_account"
						@update:checked="save()">
						{{ t('libresign', 'Request to create account when the user does not have an account') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<fieldset v-if="false" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch :checked.sync="identifyMethod.mandatory"
						@update:checked="save()">
						{{ t('libresign', 'Make this method required') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<fieldset class="settings-section__sub-section">
					{{ t('libresign', 'Signature methods') }}
					<NcCheckboxRadioSwitch v-for="(signatureMethod, signatureMethodName) in identifyMethod.signatureMethods"
						:key="signatureMethodName"
						type="radio"
						:name="identifyMethod.name"
						:value="signatureMethodName"
						:checked.sync="identifyMethods[index].signatureMethodEnabled"
						@update:checked="save()">
						{{ signatureMethod.label }}
					</NcCheckboxRadioSwitch>
				</fieldset>
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import { set } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'IdentificationFactors',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			// TRANSLATORS Name of a section at "Administration Settings" of LibreSign that an admin can configure the ways that a persol will be identified when access the link to sign a document.
			name: t('libresign', 'Identification factors'),
			description: t('libresign', 'Ways to identify a person who will sign a document.'),
			identifyMethods: loadState('libresign', 'identify_methods', []),
		}
	},
	mounted() {
		this.updateSignatureMethodsEnabled()
	},
	methods: {
		updateSignatureMethodsEnabled() {
			this.identifyMethods.forEach((identifyMethod) => {
				if (!Object.hasOwn(identifyMethod, 'signatureMethodEnabled')) {
					set(identifyMethod, 'signatureMethodEnabled', '')
				}
				if (identifyMethod.signatureMethodEnabled.length === 0) {
					const signatureMethodEnabled = Object.keys(identifyMethod.signatureMethods)
						.reduce((signatureMethodEnabled, signatureMethodName) => {
							if (signatureMethodEnabled.length === 0 && identifyMethod.signatureMethods[signatureMethodName].enabled) {
								signatureMethodEnabled = signatureMethodName
							}
							return signatureMethodEnabled
						}, identifyMethod.signatureMethodEnabled)
					if (signatureMethodEnabled.length > 0) {
						set(identifyMethod, 'signatureMethodEnabled', signatureMethodEnabled)
					} else {
						set(identifyMethod, 'signatureMethodEnabled', Object.keys(identifyMethod.signatureMethods)[0])
					}
				}
				Object.keys(identifyMethod.signatureMethods).forEach(signatureMethodName => {
					identifyMethod.signatureMethods[signatureMethodName].enabled
						= identifyMethod.signatureMethodEnabled === signatureMethodName
				})
			})
		},
		save() {
			this.updateSignatureMethodsEnabled()
			// Get only enabled
			let props = this.identifyMethods.filter(identifyMethod => identifyMethod.enabled)
			// Remove label from signature method, we don't need to save this
			props = JSON.parse(JSON.stringify(props))
				.map(identifyMethod => {
					Object.keys(identifyMethod.signatureMethods).forEach(id => {
						Object.keys(identifyMethod.signatureMethods[id]).forEach(signatureMethdoPropName => {
							if (signatureMethdoPropName === 'label') {
								delete identifyMethod.signatureMethods[id][signatureMethdoPropName]
							}
						})
					})
					return identifyMethod
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
