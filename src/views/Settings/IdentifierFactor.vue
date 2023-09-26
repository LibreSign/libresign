<template>
	<NcSettingsSection :title="title" :description="description">
		<div v-for="(option, index) in options"
			:key="index">
			<hr v-if="index != 0">
			<div v-if="option.name === 'account' && option.enabled">
				<NcCheckboxRadioSwitch type="switch"
					:checked.sync="option.enabled"
					@update:checked="save()">
					{{ option.friendly_name }}
				</NcCheckboxRadioSwitch>
				<div class="container-checkbox">
					<NcActionCheckbox :checked.sync="option.mandatory"
						@change="save()">
						{{ t('libresign', 'Make this method required') }}
					</NcActionCheckbox>
				</div>
				<div class="container-checkbox">
					<NcActionCheckbox :checked.sync="option.can_create_account"
						@change="save()">
						{{ t('libresign', 'Allow account creation for new users') }}
					</NcActionCheckbox>

					<p>{{ t('libresign', 'Allows sending registration email when the user does not have an account.') }}</p>
				</div>

				<div class="container-select">
					<label for="selectIdentificationDefault">{{ t('libresign', 'Default signature method') }}</label>
					<NcSelect v-model="option.signature_method"
						:options="option.allowed_signature_methods"
						input-id="selectIdentificationDefault" />
				</div>
			</div>
			<div v-else>
				<NcCheckboxRadioSwitch type="switch"
					:checked.sync="option.enabled"
					@update:checked="save()">
					{{ option.friendly_name }}
				</NcCheckboxRadioSwitch>
				<div v-if="option.enabled">
					<div class="container-checkbox">
						<NcActionCheckbox :checked.sync="option.mandatory"
							@change="save()">
							{{ t('libresign', 'Make this method required') }}
						</NcActionCheckbox>
					</div>
				</div>
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'IdentifierFactor',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcActionCheckbox,
		NcSelect,
	},
	data() {
		const identifyMethod = loadState('libresign', 'identify_methods')

		return {
			title: t('Identify factors'),
			description: t('Identify factors'),
			isEmpty: identifyMethod.length === 0,
			options: identifyMethod,
		}
	},
	mounted() {
		this.flagAccountIfAllDisabled()
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
		save() {
			this.flagAccountIfAllDisabled()
			OCP.AppConfig.setValue('libresign', 'identify_methods',
				JSON.stringify(
					this.options.filter(item => item.enabled),
				),
			)
		},
	},
}
</script>
<style scoped>
	.container-select {
		display: flex;
		flex-direction: column;
	}

	.container-checkbox {
		list-style: none;

		p {
			padding: 15px;
		}
	}

.identification-documents-content{
	display: flex;
	flex-direction: column;
}
</style>
