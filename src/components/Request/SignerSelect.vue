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
			<template #option="slotProps">
				<div class="account-or-email__option">
					<NcAvatar :display-name="getOptionLabel(slotProps)"
						:size="28"
						:is-no-user="true" />
					<div class="account-or-email__option__text">
						<div class="account-or-email__option__title">{{ getOptionLabel(slotProps) }}</div>
						<div v-if="getOptionSubname(slotProps)"
							class="account-or-email__option__subtitle">
							{{ getOptionSubname(slotProps) }}
						</div>
					</div>
					<NcIconSvgWrapper v-if="getOptionIcon(slotProps)"
						class="account-or-email__option__type"
						:svg="getOptionIcon(slotProps)"
						:size="18" />
				</div>
			</template>
			<template #no-options="{ search }">
				{{ search ? noResultText : t('libresign', 'No recommendations. Start typing.') }}
			</template>
		</NcSelect>
		<p v-if="haveError"
			id="account-or-email-field"
			class="account-or-email__helper-text-message account-or-email__helper-text-message--error">
			<NcIconSvgWrapper :path="mdiAlertCircle" class="account-or-email__helper-text-message__icon" :size="18" />
			{{ t('libresign', 'Signer is mandatory') }}
		</p>
	</div>
</template>
<script>
import { t } from '@nextcloud/l10n'
import {
	mdiAlertCircle,
} from '@mdi/js'
import svgAccount from '@mdi/svg/svg/account.svg?raw'
import svgEmail from '@mdi/svg/svg/email.svg?raw'
import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'
import debounce from 'debounce'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import svgSignal from '../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../img/logo-telegram-app.svg?raw'
const iconMap = {
	svgAccount,
	svgEmail,
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

const apiIconToKey = {
	'icon-user': 'svgAccount',
	'icon-mail': 'svgEmail',
	'icon-sms': 'svgSms',
	'icon-whatsapp': 'svgWhatsapp',
	'icon-signal': 'svgSignal',
	'icon-telegram': 'svgTelegram',
	'icon-xmpp': 'svgXmpp',
}
export default {
	name: 'SignerSelect',
	components: {
		NcAvatar,
		NcSelect,
		NcIconSvgWrapper,
	},
	setup() {
		return {
			mdiAlertCircle,
		}
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
			intersectionObserver: null,
			activeRequestId: 0,
		}
	},
	computed: {
		noResultText() {
			if (this.loading) {
				return t('libresign', 'Searching …')
			}
			return t('libresign', 'No signers.')
		},
	},
	watch: {
		method() {
			this.options = []
			this.haveError = false
			this.loading = false
		},
		selectedSigner(selected) {
			this.haveError = selected === null
			this.$emit('update:signer', selected)
		},
	},
	mounted() {
		if (Object.keys(this.signer).length > 0) {
			this.selectedSigner = this.signer
		}
		this.setupVisibilityObserver()
		this.focusInput()
	},
	beforeDestroy() {
		if (this.intersectionObserver) {
			this.intersectionObserver.disconnect()
		}
	},
	methods: {
		t,
		async _asyncFind(search, lookup = false) {
			search = search.trim()
			if (!search) {
				this.options = []
				this.loading = false
				return
			}

			const requestId = ++this.activeRequestId
			this.loading = true
			try {
				const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
					params: {
						search,
						method: this.method,
	},
				})
				if (requestId !== this.activeRequestId) {
					return
				}
				this.options = this.injectIcons(response.data.ocs.data)
			} catch (error) {
				if (requestId === this.activeRequestId) {
					this.haveError = true
				}
			} finally {
				if (requestId === this.activeRequestId) {
					this.loading = false
				}
			}
		},
		asyncFind: debounce(function(search, lookup = false) {
			this._asyncFind(search, lookup)
		}, 500),
		injectIcons(items) {
			return items.map(item => {
				const { iconSvg: _iconSvg, ...safeItem } = item
				const iconFromApi = item.icon ? iconMap[apiIconToKey[item.icon]] : undefined
				const iconFromSvgKey = item.iconSvg ? iconMap[item.iconSvg] : undefined
				const icon = iconFromApi || iconFromSvgKey
				return {
					...safeItem,
					...(icon ? { iconSvg: icon } : {}),
					label: item.label ?? item.displayName ?? item.id ?? item.subname ?? '',
					displayName: item.displayName ?? '',
					subname: item.subname ?? '',
				}
			})
		},
		getOption(slotProps) {
			if (slotProps?.option) {
				return slotProps.option
			}
			if (slotProps && typeof slotProps === 'object') {
				return slotProps
			}
			return {}
		},
		getOptionLabel(slotProps) {
			const option = this.getOption(slotProps)
			return option.displayName || option.label || option.id || option.subname || ''
		},
		getOptionSubname(slotProps) {
			const option = this.getOption(slotProps)
			if (!option.subname || option.subname === option.displayName) {
				return ''
			}
			return option.subname
		},
		getOptionIcon(slotProps) {
			return this.getOption(slotProps).iconSvg || ''
		},
		focusInput() {
			if (this.selectedSigner) {
				return
			}
			this.$nextTick(() => {
				const input = this.$refs.select?.$el?.querySelector('input')
				if (input) {
					input.focus()
				}
			})
		},
		setupVisibilityObserver() {
			const container = this.$el
			if (!container) {
				return
			}
			this.intersectionObserver = new IntersectionObserver((entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						this.focusInput()
					}
				})
			}, {
				threshold: 0.1,
			})
			this.intersectionObserver.observe(container)
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

	&__option {
		display: flex;
		align-items: center;
		gap: 8px;
		width: 100%;

		&__text {
			display: flex;
			flex-direction: column;
			min-width: 0;
			flex: 1;
		}

		&__title,
		&__subtitle {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__subtitle {
			opacity: 0.7;
			font-size: 12px;
		}

		&__type {
			flex-shrink: 0;
			opacity: 0.8;
		}
	}
}
</style>
