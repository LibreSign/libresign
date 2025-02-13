<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="account-or-email">
		<label for="account-or-email-input">{{ t('libresign', 'Search signer') }}</label>
		<NcSelect ref="select"
			v-model="selectedSigner"
			input-id="account-or-email-input"
			class="account-or-email__input"
			:loading="loading"
			:filterable="false"
			:aria-label-combobox="placeholder"
			:placeholder="placeholder"
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
import AlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcSelect from '@nextcloud/vue/components/NcSelect'

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
		placeholder: {
			type: String,
			default: t('libresign', 'Name'),
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
			this.haveError = selected === null && this.required
			if (selected === null) {
				this.$emit('update:email', false)
				this.$emit('update:account', false)
				this.$emit('update:display-name', '')
			} else if (selected.isNoUser && selected.icon === 'icon-mail') {
				this.$emit('update:email', selected)
				this.$emit('update:account', false)
				this.$emit('update:display-name', selected.displayName)
			} else {
				this.$emit('update:email', false)
				this.$emit('update:account', selected)
				this.$emit('update:display-name', selected.displayName)
			}
		},
	},
	mounted() {
		if (Object.keys(this.signer).length > 0) {
			this.selectedSigner = this.signer
		}
	},
	methods: {
		async asyncFind(search, lookup = false) {
			search = search.trim()
			this.loading = true

			let response = null
			try {
				response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
					params: {
						search,
					},
				})
			} catch (error) {
				this.haveError = true
				return
			}

			this.options = response.data.ocs.data
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
		position: relative;
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
