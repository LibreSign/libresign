<template>
	<NcSettingsSection :title="title" :description="description">		
		<NcCheckboxRadioSwitch
			type="switch"
	  	:checked.sync="useUser"
	  	>
 		{{ t('libresign', 'User')}}
	  </NcCheckboxRadioSwitch>
		<div class="container-checkbox">	
			<NcActionCheckbox
	  		:checked.sync="allowedInviteUser"
	  		>
 				{{ t('libresign', 'Allow account creation for new users')}}
	  	</NcActionCheckbox>
		
			<p>{{ t('libresign', 'Allows sending registration email when the user does not have an account.') }}</p>
		</div>
		
		<div class="container-select">
			<label for="selectIdentificationDefault">{{ t('libresign', 'Default signature method')}}</label>

			<NcSelect
				v-model="selectedDefaultIdentification"
				:options="options"
				inputId="selectIdentificationDefault"
			/>
		</div>
		<hr/>

		<NcCheckboxRadioSwitch
			type="switch"
			:checked.sync="useTelegram">
	  		{{ t('libresign', 'Telegram')}}
	  </NcCheckboxRadioSwitch>
		<div class="container-checkbox">	
			<NcActionCheckbox
	  		:checked.sync="requiredTelegram"
	  		>
 				{{ t('libresign', 'Make this method required')}}
	  	</NcActionCheckbox>
		</div>	

		<NcTextField
	  	:value.sync="botTelegramName"
			label="Bot"
			trailing-button-icon="close"
			@trailing-button-click="clearText"
			:label-visible="true"
		/>
	  <NcTextField
	  	:value.sync="botTelegramApi"
			label="API Token"
			trailing-button-icon="close"
			@trailing-button-click="clearText"
		:label-visible="true"
		/>
	
		<hr/>

		<NcCheckboxRadioSwitch
			type="switch"
			:checked.sync="useSMS">
	  		{{ t('libresign', 'SMS')}}
	  </NcCheckboxRadioSwitch>
	  
	  <div class="container-checkbox">	
			<NcActionCheckbox
	  		:checked.sync="requiredSMS"
	  		>
 				{{ t('libresign', 'Make this method required')}}
	  	</NcActionCheckbox>
		</div>

	  <NcTextField
	  	:value.sync="smsApiToken"
			label="API Token"
			trailing-button-icon="close"
			@trailing-button-click="clearText"
		:label-visible="true"
		/>
		<hr/>

		<NcCheckboxRadioSwitch
			type="switch"
			:checked.sync="useSignal">
	  		{{ t('libresign', 'Signal')}}
	  </NcCheckboxRadioSwitch>
	  <div class="container-checkbox">	
			<NcActionCheckbox
	  		:checked.sync="requiredSignal"
	  		>
 			{{ t('libresign', 'add this method required')}}
	  	</NcActionCheckbox>
		</div>
		<div class="container-checkbox">	
			<NcActionCheckbox
	  		:checked.sync="identificationSignal"
	  		>
 				{{ t('libresign', 'allow this method like identifiation')}}
	  	</NcActionCheckbox>
		</div>
		<NcTextField
	  	:value.sync="signalApiToken"
			label="API Token"
			trailing-button-icon="close"
			@trailing-button-click="clearText"
		:label-visible="true"
		/>

</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField'

export default {
	name: 'IdentificationFactor',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcMultiselect,
		NcActionCheckbox,
		NcSelect,
		NcTextField,
	},
	data() {
		return {
	    title: "Fatores de Identificação",
			selectedDefaultIdentification: '',
			options: [
				'Certificado com senha',
				'Boladão',
				'Br'
			],
			allowedInviteUser: false,
			useUser: true,
			useTelegram: false,
			botTelegramName: "",
			botTelegramApi: "",
			signalApiToken: '',
			requiredTelegram: false,
			requiredSMS: false,
			requiredSignal: false,
			identificationSignal: false,
		}
	},
	methods: {
		changeAllowInviteUser(allowedInviteUser) {
			this.allowedInviteUser = allowedInviteUser;	
		}
	}
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
