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

import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'
import debounce from 'debounce'

import AlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcSelect from '@nextcloud/vue/components/NcSelect'

import svgSignal from '../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../img/logo-telegram-app.svg?raw'

const iconMap = {
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

export default {
	name: 'SignerSelect',
	components: {
		NcSelect,
		AlertCircle,
	},
	props: {
		signer: {
			type: Object,
			default: () => {},
		},
		method: {
			type: String,
			default: 'all',
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
			haveError: false,
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
			this.haveError = selected === null
			this.$emit('update:signer', selected)
		},
	},
	mounted() {
		if (Object.keys(this.signer).length > 0) {
			this.selectedSigner = this.signer
		}
	},
	methods: {
		async _asyncFind(search, lookup = false) {
			search = search.trim()
			this.loading = true

			let response = null
			try {
				response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
					params: {
						search,
						method: this.method,
					},
				})
			} catch (error) {
				this.haveError = true
				return
			}

			this.options = this.injectIcons(response.data.ocs.data)
			this.loading = false
		},

		asyncFind: debounce(function(search, lookup = false) {
			this._asyncFind(search, lookup)
		}, 500),

		injectIcons(items) {
			return items.map(item => {
				const icon = item.iconSvg ? iconMap[item.iconSvg] : undefined
				return {
					...item,
					...(icon ? { iconSvg: icon } : {}),
				}
			})
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
