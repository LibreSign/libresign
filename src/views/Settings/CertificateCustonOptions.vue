<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="form-group">
		<label for="optionalAttribute">{{ t('libresign', 'Optional attributes') }}</label>
		<NcPopover container="body" :popper-hide-triggers="(triggers) => [...triggers, 'click']">
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
							({{ t('libresign', 'Maximum {max} items', {max: $options.MAX_ARRAY_ITEMS}) }})
						</span>
						<NcButton :aria-label="t('libresign', 'Add new')"
							:disabled="isMaxItemsReached(certificate)"
							@click="addArrayEntry(certificate.id)">
							<template #icon>
								<Plus :size="20" />
							</template>
						</NcButton>
						<NcButton :aria-label="t('libresign', 'Remove custom name entry from root certificate')"
							@click="removeOptionalAttribute(certificate.id)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
					<div v-for="(item, index) in certificate.value"
						:key="`${certificate.id}-${index}`"
						class="array-item">
						<NcTextField :id="`${certificate.id}-${index}`"
							v-model="certificate.value[index]"
							:placeholder="t('libresign', 'Item {index}', {index: index + 1})"
							@update:value="validateArray(certificate.id)" />
						<NcButton v-if="certificate.value.length > 1"
							:aria-label="t('libresign', 'Remove')"
							@click="removeArrayEntry(certificate.id, index)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
				<div v-else class="item">
					<NcTextField v-if="certificate"
						:id="certificate.id"
						v-model="certificate.value"
						:success="typeof certificate.error === 'boolean' && !certificate.error"
						:error="certificate.error"
						:maxlength="getOptionProperty(certificate.id, 'max')"
						:label="getOptionProperty(certificate.id, 'label')"
						:helper-text="getOptionProperty(certificate.id, 'helperText')"
						@update:value="validate(certificate.id)" />
					<NcButton :aria-label="t('libresign', 'Remove custom name entry from root certificate')"
						@click="removeOptionalAttribute(certificate.id)">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { options, selectCustonOption } from '../../helpers/certification.js'

const MAX_ARRAY_ITEMS = 10

export default {
	name: 'CertificateCustonOptions',
	MAX_ARRAY_ITEMS,
	components: {
		NcButton,
		NcTextField,
		NcPopover,
		NcListItem,
		Delete,
		Plus,
	},
	props: {
		names: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			certificateList: [],
		}
	},
	computed: {
		customNamesOptions() {
			return this.options.filter(itemA =>
				!this.certificateList.some(itemB => itemB.id === itemA.id),
			)
		},
		options() {
			return options.filter(option => option.id !== 'CN')
		},
	},
	watch: {
		names(values) {
			this.certificateList = values
		},
	},
	methods: {
		getOptionProperty(id, property) {
			return this.options.find(option => option.id === id)[property]
		},
		isMaxItemsReached(certificate) {
			if (!Array.isArray(certificate.value)) return false
			return certificate.value.length >= MAX_ARRAY_ITEMS
		},
		validateMin(item) {
			return item.value.length >= item.min
		},
		validateMax(item) {
			if (Object.hasOwn(item, 'max')) {
				return item.value.length <= item.max
			}
			return true
		},
		validate(id) {
			const custonOption = selectCustonOption(id)
			if (custonOption.isSome()) {
				const item = custonOption.unwrap()
				if (this.validateMin(item) && this.validateMax(item)) {
					item.error = false
				} else {
					item.error = true
				}
				const listToSave = this.certificateList.map(certificate => ({
					id: certificate.id,
					value: certificate.value,
				}))
				emit('libresign:update:certificateToSave', listToSave)
			}
		},
		validateArray(id) {
			const listToSave = this.certificateList.map(certificate => ({
				id: certificate.id,
				value: certificate.value,
			}))
			emit('libresign:update:certificateToSave', listToSave)
		},
		addArrayEntry(id) {
			const certificate = this.certificateList.find(cert => cert.id === id)
			if (certificate && Array.isArray(certificate.value)) {
				if (certificate.value.length < MAX_ARRAY_ITEMS) {
					certificate.value.push('')
					this.validateArray(id)
				}
			}
		},
		removeArrayEntry(id, index) {
			const certificate = this.certificateList.find(cert => cert.id === id)
			if (certificate && Array.isArray(certificate.value) && certificate.value.length > 1) {
				certificate.value.splice(index, 1)
				this.validateArray(id)
			}
		},
		async removeOptionalAttribute(id) {
			const custonOption = selectCustonOption(id)
			if (custonOption.isSome()) {
				const itemSelected = {
					...custonOption.unwrap(),
					value: '',
				}
				const list = this.certificateList.filter(item => item.id !== itemSelected.id)
				this.certificateList = list
			}
		},
		async onOptionalAttributeSelect(selected) {
			const custonOption = selectCustonOption(selected.id)
			if (custonOption.isSome()) {
				const option = custonOption.unwrap()
				if (option.id === 'OU') {
					option.value = ['']
				}
				this.certificateList = [option, ...this.certificateList]
			}
		},

	},
}
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
