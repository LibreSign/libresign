<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="form-group">
		<label for="optionalAttribute">{{ t('libresign', 'Optional attributes') }}</label>
		<NcPopover container="body" :popper-hide-triggers="extendHideTriggers">
			<template #trigger>
				<NcButton :disabled="customNamesOptions.length === 0">
					{{ t('libresign', 'Select a custom name') }}
				</NcButton>
			</template>
			<template #default>
				<ul style="width: 350px;">
					<div v-for="option in customNamesOptions" :key="option.id">
						<NcListItem :name="option.label"
							@click="onOptionalAttributeSelect(option)">
							<template #subname>
								{{ option.label }}
							</template>
						</NcListItem>
					</div>
				</ul>
			</template>
		</NcPopover>
		<div v-if="certificateList.length > 0">
			<div v-for="certificate in certificateList"
				:key="certificate.id"
				class="customNames">
				<div v-if="certificate.id === 'OU' && Array.isArray(certificate.value)">
					<div class="array-field-header">
						<strong>{{ getOptionProperty(certificate.id, 'label') }}</strong>
						<span v-if="isMaxItemsReached(certificate)" class="max-items-warning">
							({{ t('libresign', 'Maximum {max} items', {max: MAX_ARRAY_ITEMS}) }})
						</span>
						<NcButton :aria-label="t('libresign', 'Add new')"
							:disabled="isMaxItemsReached(certificate)"
							@click="addArrayEntry(certificate.id)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPlus" :size="20" />
							</template>
						</NcButton>
						<NcButton :aria-label="t('libresign', 'Remove custom name entry from root certificate')"
							@click="removeOptionalAttribute(certificate.id)">
							<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</div>
					<div v-for="(item, index) in certificate.value"
						:key="`${certificate.id}-${index}`"
						class="array-item">
						<NcTextField :id="`${certificate.id}-${index}`"
							v-model="certificate.value[index]"
							:placeholder="t('libresign', 'Item {index}', {index: index + 1})"
							@update:modelValue="validateArray(certificate.id)" />
						<NcButton v-if="certificate.value.length > 1"
							:aria-label="t('libresign', 'Remove')"
							@click="removeArrayEntry(certificate.id, index)">
							<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
				<div v-else class="item">
					<NcTextField v-if="certificate"
						:id="certificate.id"
						:model-value="typeof certificate.value === 'string' ? certificate.value : ''"
						:success="typeof certificate.error === 'boolean' && !certificate.error"
						:error="certificate.error"
						:maxlength="getOptionMax(certificate.id)"
						:label="getOptionLabel(certificate.id)"
						:helper-text="getOptionHelperText(certificate.id)"
						@update:modelValue="updateCertificateValue(certificate, $event)" />
					<NcButton :aria-label="t('libresign', 'Remove custom name entry from root certificate')"
						@click="removeOptionalAttribute(certificate.id)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import { computed, ref, watch } from 'vue'

import { emit } from '@nextcloud/event-bus'

import {
	mdiDelete,
	mdiPlus,
} from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { options, selectCustonOption } from '../../helpers/certification'

const MAX_ARRAY_ITEMS = 10

defineOptions({
	name: 'CertificateCustonOptions',
})

interface CertificateOption {
	id: string
	label?: string
	helperText?: string
	min?: number
	max?: number
	value: string | string[]
	error?: boolean
}

const props = defineProps<{
	names: CertificateOption[]
}>()

const certificateList = ref<CertificateOption[]>([])

const availableOptions = computed(() => options.filter(option => option.id !== 'CN'))

const customNamesOptions = computed(() => availableOptions.value.filter(itemA =>
	!certificateList.value.some(itemB => itemB.id === itemA.id),
))

watch(() => props.names, (values) => {
	certificateList.value = values as CertificateOption[]
}, { immediate: true })

function getOptionProperty(id: string, property: 'label' | 'helperText' | 'max') {
	return availableOptions.value.find(option => option.id === id)?.[property]
}

function getOptionLabel(id: string) {
	const label = getOptionProperty(id, 'label')
	return typeof label === 'string' ? label : undefined
}

function getOptionHelperText(id: string) {
	const helperText = getOptionProperty(id, 'helperText')
	return typeof helperText === 'string' ? helperText : undefined
}

function getOptionMax(id: string) {
	const max = getOptionProperty(id, 'max')
	return typeof max === 'number' ? max : undefined
}

function extendHideTriggers(triggers: string[]) {
	return [...triggers, 'click']
}

function isMaxItemsReached(certificate: CertificateOption) {
	if (!Array.isArray(certificate.value)) {
		return false
	}
	return certificate.value.length >= MAX_ARRAY_ITEMS
}

function validateMin(item: CertificateOption) {
	return item.min === undefined || String(item.value).length >= item.min
}

function validateMax(item: CertificateOption) {
	return item.max === undefined || String(item.value).length <= item.max
}

function emitCertificateList() {
	const listToSave = certificateList.value.map(certificate => ({
		id: certificate.id,
		value: certificate.value,
	}))
	emit('libresign:update:certificateToSave', listToSave)
}

function validate(id: string) {
	const metadata = selectCustonOption(id)
	const certificate = certificateList.value.find(item => item.id === id)
	if (metadata.isSome() && certificate) {
		const option = metadata.unwrap()
		certificate.min = option.min
		certificate.max = option.max
		certificate.error = !(validateMin(certificate) && validateMax(certificate))
		emitCertificateList()
	}
}

function updateCertificateValue(certificate: CertificateOption, value: string | number) {
	certificate.value = String(value)
	validate(certificate.id)
}

function validateArray(_id: string) {
	emitCertificateList()
}

function addArrayEntry(id: string) {
	const certificate = certificateList.value.find(cert => cert.id === id)
	if (certificate && Array.isArray(certificate.value) && certificate.value.length < MAX_ARRAY_ITEMS) {
		certificate.value.push('')
		validateArray(id)
	}
}

function removeArrayEntry(id: string, index: number) {
	const certificate = certificateList.value.find(cert => cert.id === id)
	if (certificate && Array.isArray(certificate.value) && certificate.value.length > 1) {
		certificate.value.splice(index, 1)
		validateArray(id)
	}
}

async function removeOptionalAttribute(id: string) {
	const custonOption = selectCustonOption(id)
	if (custonOption.isSome()) {
		certificateList.value = certificateList.value.filter(item => item.id !== custonOption.unwrap().id)
	}
}

async function onOptionalAttributeSelect(selected: { id: string }) {
	const custonOption = selectCustonOption(selected.id)
	if (custonOption.isSome()) {
		const option = custonOption.unwrap()
		certificateList.value = [{
			...option,
			value: option.id === 'OU' ? [''] : option.value,
		}, ...certificateList.value]
	}
}

defineExpose({
	certificateList,
	customNamesOptions,
	availableOptions,
	getOptionProperty,
	isMaxItemsReached,
	validateMin,
	validateMax,
	validate,
	validateArray,
	addArrayEntry,
	removeArrayEntry,
	removeOptionalAttribute,
	onOptionalAttributeSelect,
})
</script>

<style lang="scss" scoped>
#formRootCertificateCfssl{
	text-align: left;
	margin: 20px;
}

.form-group > input[type='text'], .form-group .multiselect {
	width: 100%;
}

.customNames {
	.item,
	.array-item {
		display: grid;
		grid-template-columns: auto 54px;
		align-items: center;
		input[type='text'] {
			width: 100%;
		}
		.button-vue {
			margin-left: 10px;
		}
	}

	.array-item {
		margin-bottom: 5px;
	}

	.array-field-header {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 10px;
		strong {
			flex: 1;
		}
		.max-items-warning {
			color: var(--color-text-maxcontrast);
			font-size: 0.9em;
			font-style: italic;
		}
	}
}

.form-heading--required:after {
	content:" *";
}

.modal__content {
	margin: 50px;
	text-align: center;

	.grid {
		display: flex;
		flex-direction: row;
		align-self: flex-end;
		button {
			margin: 10px;
		}
	}
}

@media screen and (max-width: 500px){
	#formRootCertificateCfssl{
		width: 100%;
	}
}

</style>
