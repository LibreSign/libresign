<template>
	<div class="settings-section">
		<div class="form-group">
			<label class="title"> {{ labelTitle }} </label>
			<label> {{ labelDesciption }}</label>
			<input id="validation_site"
				v-model="url"
				type="text"
				@input="saveGroups()">
		</div>
	</div>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
export default {
	name: 'UrlValidation',
	data() {
		return {
			url: null,
			labelTitle: t('libresign', 'Validation URL'),
			labelDesciption: t('libresign', 'To validate signature of the documents'),
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps' + '/' + 'libresign' + '/' + 'validation_site', {}
			)
			this.url = response.data.ocs.data.data
		},
		saveGroups() {
			OCP.AppConfig.setValue('libresign', 'validation_site', this.url)
		},
	},
}
</script>
<style scoped>
.form-group{
	display: flex;
	flex-direction: column;
}

.title{
	font-weight: bold;
	width: auto;
}

label{
	cursor: default;
}

input{
	width: 400px;
}

@media screen and (max-width: 500px){
	input{
		width: 100%;
	}
}
</style>
