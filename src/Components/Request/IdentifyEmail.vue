<template>
	<div id="identify-email">
		<label for="identify-email-input">{{ t('libresign', 'E-mail of signer') }}</label>
		<NcTextField type="email"
			aria-describedby="identify-email-input"
			autocapitalize="none"
			autocomplete="off"
			autocorrect="off"
			:value.sync="email"
			:label="t('settings', 'Email')"
			:required="true"
			:error="haveError"
			:helper-text="helperText"
			@update:value="onEmailChange" />
	</div>
</template>
<script>
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

/**
 * Email validation regex
 *
 * Sourced from https://github.com/mpyw/FILTER_VALIDATE_EMAIL.js/blob/71e62ca48841d2246a1b531e7e84f5a01f15e615/src/regexp/ascii.ts*
 */
// eslint-disable-next-line no-control-regex
export const VALIDATE_EMAIL_REGEX = /^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/i

export default {
	name: 'IdentifyEmail',
	components: {
		NcTextField,
	},
	props: {
		required: {
			type: Boolean,
			default: false,
			required: false,
		},
	},
	data() {
		return {
			email: '',
			helperText: '',
			haveError: this.required,
		}
	},
	methods: {
		onEmailChange() {
			const email = this.email.trim()
			if (this.validateEmail(email) || (email.length === 0 && !this.required)) {
				this.helperText = ''
				this.haveError = false
				return
			}
			this.helperText = t('libresign', 'Please enter an email address.')
			this.haveError = true
		},
		validateEmail(input) {
			return typeof input === 'string'
				&& VALIDATE_EMAIL_REGEX.test(input)
				&& input.slice(-1) !== '\n'
				&& input.length <= 256
				&& encodeURIComponent(input).replace(/%../g, 'x').length <= 256
		},
	},
}
</script>
