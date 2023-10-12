<template>
	<div id="identify-account">
		<label for="identify-search-input">{{ t('libresign', 'Search signer by account') }}</label>
		<NcSelect ref="select"
			v-model="selectedAccount"
			input-id="identify-search-input"
			class="identify-search__input"
			:loading="loading"
			:filterable="false"
			:placeholder="t('libresign', 'Name')"
			:user-select="true"
			:options="options"
			@search="asyncFind">
			<template #no-options="{ search }">
				{{ search ? noResultText : t('libresign', 'No recommendations. Start typing.') }}
			</template>
		</NcSelect>
		<p v-if="haveError"
			id="identify-account-field"
			class="identify-account__helper-text-message identify-account__helper-text-message--error">
			<AlertCircle class="identify-account__helper-text-message__icon" :size="18" />
			{{ t('libresign', 'Account is mandatory') }}
		</p>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import AlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'IdentifyAccount',
	components: {
		NcButton,
		NcSelect,
		AlertCircle,
	},
	props: {
		required: {
			type: Boolean,
			default: false,
			required: false,
		},
	},
	watch: {
		selectedAccount(account) {
			this.haveError = account === null && this.required
		}
	},
	data() {
		return {
			loading: false,
			options: [],
			selectedAccount: null,
			haveError: this.required,
		}
	},
	computed: {
		noResultText() {
			if (this.loading) {
				return t('libesign', 'Searching …')
			}
			return t('libesign', 'No elements found.')
		},
	},
	methods: {
		async asyncFind(search, lookup = false) {
			search = search.trim()
			this.loading = true

			let request = null
			try {
				request = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
					params: {
						search,
					},
				})
			} catch (error) {
				this.haveError = true
				return
			}

			this.options = request.data.ocs.data
			this.loading = false
		},
	},
}
</script>

<style lang="scss" scoped>
.identify-account {
	display: flex;
	flex-direction: column;
	margin-bottom: 4px;

	label[for="sharing-search-input"] {
		margin-bottom: 2px;
	}

	&__input {
		width: 100%;
		margin: 10px 0;
	}

	input {
		grid-area: 1 / 1;
		width: 100%;
	}

	&__helper-text-message {
		padding: 4px 0;
		display: flex;
		align-items: center;

		&__icon {
			margin-right: 8px;
			align-self: start;
			margin-top: 4px;
		}

		&--error {
			color: var(--color-error);
		}
	}
}
</style>
