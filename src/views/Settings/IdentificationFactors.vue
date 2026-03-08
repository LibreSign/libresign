<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection v-if="!isNoneEngine"
		:name="t('libresign', 'Identification factors')"
		:description="description">
		<div v-for="(identifyMethod, index) in identifyMethods"
			:key="identifyMethod.name">
			<hr v-if="index != 0">
			<NcCheckboxRadioSwitch type="switch"
				v-model="identifyMethod.enabled"
				@update:model-value="save">
				{{ identifyMethod.friendly_name }}
			</NcCheckboxRadioSwitch>
			<div v-if="identifyMethod.enabled">
				<fieldset v-if="identifyMethod.name === 'email'" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch v-model="identifyMethod.can_create_account"
						@update:model-value="save">
						{{ t('libresign', 'Request to create account when the user does not have an account') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<fieldset v-if="false" class="settings-section__sub-section">
					<NcCheckboxRadioSwitch v-model="identifyMethod.mandatory"
						@update:model-value="save">
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
						v-model="identifyMethods[index].signatureMethodEnabled"
						@update:model-value="save">
						{{ signatureMethod.label }}
					</NcCheckboxRadioSwitch>
				</fieldset>
			</div>
		</div>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, watch } from 'vue'

import { useConfigureCheckStore } from '../../store/configureCheck.js'

defineOptions({
	name: 'IdentificationFactors',
})

type SignatureMethod = {
	enabled: boolean
	label?: string
}

type IdentifyMethod = {
	name: string
	friendly_name: string
	enabled: boolean
	can_create_account?: boolean
	mandatory?: boolean
	signatureMethods: Record<string, SignatureMethod>
	signatureMethodEnabled?: string
}

type ConfigureCheckStore = {
	isNoneEngine: boolean
	identifyMethods: IdentifyMethod[]
}

type AppConfigGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string) => void
	}
}

const configureCheckStore = useConfigureCheckStore() as ConfigureCheckStore

const description = computed(() => {
	// TRANSLATORS Name of a section at "Administration Settings" of LibreSign that an admin can configure the ways that a person will be identified when accessing the link to sign a document.
	return t('libresign', 'Ways to identify a person who will sign a document.')
})

const isNoneEngine = computed(() => configureCheckStore.isNoneEngine)
const identifyMethods = computed(() => configureCheckStore.identifyMethods)

function updateSignatureMethodsEnabled() {
	identifyMethods.value.forEach((identifyMethod) => {
		const signatureMethodNames = Object.keys(identifyMethod.signatureMethods)
		if (!Object.hasOwn(identifyMethod, 'signatureMethodEnabled')) {
			identifyMethod.signatureMethodEnabled = ''
		}

		if (identifyMethod.signatureMethodEnabled.length === 0) {
			const selectedSignatureMethod = signatureMethodNames
				.reduce((currentSelection, signatureMethodName) => {
					const signatureMethod = identifyMethod.signatureMethods[signatureMethodName]
					if (currentSelection.length === 0 && signatureMethod?.enabled) {
						return signatureMethodName
					}
					return currentSelection
				}, identifyMethod.signatureMethodEnabled)

			identifyMethod.signatureMethodEnabled = selectedSignatureMethod.length > 0
				? selectedSignatureMethod
				: (signatureMethodNames[0] ?? '')
		}

		signatureMethodNames.forEach((signatureMethodName) => {
			const signatureMethod = identifyMethod.signatureMethods[signatureMethodName]
			if (signatureMethod) {
				signatureMethod.enabled = identifyMethod.signatureMethodEnabled === signatureMethodName
			}
		})
	})
}

function save() {
	updateSignatureMethodsEnabled()

	const props = JSON.parse(JSON.stringify(
		identifyMethods.value.filter((identifyMethod) => identifyMethod.enabled),
	)) as IdentifyMethod[]

	props.forEach((identifyMethod) => {
		Object.keys(identifyMethod.signatureMethods).forEach((id) => {
			delete identifyMethod.signatureMethods[id].label
		})
	})

	;(globalThis as typeof globalThis & { OCP: AppConfigGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'identify_methods',
		JSON.stringify(props),
	)
}

onMounted(() => {
	updateSignatureMethodsEnabled()
})

watch(identifyMethods, () => {
	updateSignatureMethodsEnabled()
}, { deep: true })

defineExpose({
	description,
	isNoneEngine,
	identifyMethods,
	updateSignatureMethodsEnabled,
	save,
})
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
