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
				<label :for="certificate.id" class="form-heading--required">
					{{ certificate.label }}
				</label>
				<div class="item">
					<NcTextField v-if="certificate"
						:id="certificate.id"
						:value.sync="certificate.value"
						:success="typeof certificate.error === 'boolean' && !certificate.error"
						:error="certificate.error"
						:maxlength="certificate.max ? certificate.max : undefined"
						:label="certificate.label"
						:helper-text="certificate.helperText"
						@update:value="validate(certificate.id)" />
					<NcButton :aria-label="t('settings', 'Remove custom name entry from root certificate')"
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

import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { options, selectCustonOption } from '../../helpers/certification.js'

export default {
	name: 'CertificateCustonOptions',
	components: {
		NcButton,
		NcTextField,
		NcPopover,
		NcListItem,
		Delete,
	},
	props: {
		names: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			customNamesOptions: options,
			certificateList: [],
			options,
		}
	},
	methods: {
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
		async removeOptionalAttribute(id) {
			const custonOption = selectCustonOption(id)
			if (custonOption.isSome()) {
				const itemSelected = {
					...custonOption.unwrap(),
					value: '',
				}
				const list = this.certificateList.filter(item => item.id !== itemSelected.id)
				this.certificateList = list
				this.customNamesOptions = [...this.customNamesOptions, itemSelected]
			}
		},
		async onOptionalAttributeSelect(selected) {
			const custonOption = selectCustonOption(selected.id)
			if (custonOption.isSome()) {
				this.certificateList = [custonOption.unwrap(), ...this.certificateList]
				this.customNamesOptions = this.customNamesOptions.filter(item => item.id !== selected.id)
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
	.item {
		display: grid;
		grid-template-columns: auto 54px;
		input[type='text'] {
			width: 100%;
		}
		.button-vue {
			margin-left: 10px;
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
