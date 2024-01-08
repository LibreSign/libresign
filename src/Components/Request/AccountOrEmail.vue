<template>
	<div id="account-or-email">
		<label for="identify-search-input">{{ t('libresign', 'Search signer') }}</label>
		<NcSelect ref="select"
			v-model="selectedSigner"
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
			id="account-or-email-field"
			class="account-or-email__helper-text-message account-or-email__helper-text-message--error">
			<AlertCircle class="account-or-email__helper-text-message__icon" :size="18" />
			{{ t('libresign', 'Signer is mandatory') }}
		</p>
	</div>
</template>
<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import AlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'AccountOrEmail',
	components: {
		NcSelect,
		AlertCircle,
	},
	props: {
		required: {
			type: Boolean,
			default: false,
			required: false,
		},
		signer: {
			type: Object,
			default: () => {},
			required: false,
		},
	},
	data() {
		return {
			loading: false,
			options: [],
			selectedSigner: null,
			haveError: this.required,
		}
	},
	computed: {
		noResultText() {
			if (this.loading) {
				return t('libesign', 'Searching …')
			}
			return t('libesign', 'No signers.')
		},
	},
	watch: {
		selectedSigner(selected) {
			let type = this.getTypeFromItem(selected)
			this.haveError = selected === null && this.required
			this.$emit('update:' + type, selected)
		},
	},
	mounted() {
		if (Object.keys(this.account).length > 0) {
			this.selectedSigner = this.account
		}
	},
	methods: {
		getTypeFromItem(item) {
			if (item.isNoUser && item.icon === 'icon-mail') {
				return 'email'
			}
			return 'account'
		},
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
.account-or-email {
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
