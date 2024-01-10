<template>
	<div id="signer-name">
		<label for="name-input">{{ t('libresign', 'Signer name') }}</label>
		<NcTextField aria-describedby="name-input"
			autocapitalize="none"
			:value.sync="displayName"
			:label="t('libresign', 'Signer name')"
			:required="true"
			:error="haveError"
			:helper-text="helperText"
			@update:value="onNameChange" />
	</div>
</template>
<script>
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'SignerName',
	components: {
		NcTextField,
	},
	props: {
		name: {
			type: String,
			required: false,
			default: '',
		},
	},
	data() {
		return {
			displayName: this.name,
			helperText: '',
			haveError: this.required,
		}
	},
	watch: {
		name(name) {
			this.displayName = name
		}
	},
	methods: {
		onNameChange() {
			const name = this.displayName.trim()
			if (name.length > 2) {
				this.helperText = ''
				this.haveError = false
				this.$emit('update:name', name)
				return
			}
			this.$emit('update:name', name)
			this.helperText = t('libresign', 'Please enter signer name.')
			this.haveError = true
		},
	},
}
</script>
