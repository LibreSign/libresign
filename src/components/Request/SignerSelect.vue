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
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
import type { IdentifyAccountRecord } from '../../types'
const iconMap = {
	svgAccount,
	svgEmail,
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

type IconKey = keyof typeof iconMap
type IconName = NonNullable<IdentifyAccountRecord['iconName']>

defineOptions({
	name: 'SignerSelect',
})

type SignerOption = {
	identify?: string
	displayName?: string
	subname?: string
	label?: string
	method?: string
	iconName?: IconName
	acceptsEmailNotifications?: boolean
}

type NormalizedSignerOption = SignerOption & {
	identify: string
	displayName: string
	subname: string
	label: string
}

const emit = defineEmits<{
	(event: 'update:signer', signer: SignerOption | null): void
}>()

const props = withDefaults(defineProps<{
	signer?: Record<string, unknown>
	method?: string
	placeholder?: string
}>(), {
	signer: () => ({}),
	method: 'all',
	placeholder: t('libresign', 'Name'),
})

const select = ref<{ $el?: HTMLElement } | null>(null)
const container = ref<HTMLElement | null>(null)
const loading = ref(false)
const options = ref<NormalizedSignerOption[]>([])
const selectedSigner = ref<SignerOption | null>(null)
const haveError = ref(false)
const intersectionObserver = ref<IntersectionObserver | null>(null)
const activeRequestId = ref(0)

const noResultText = computed(() => loading.value ? t('libresign', 'Searching …') : t('libresign', 'No signers.'))

function handleMethodChange() {
	options.value = []
	haveError.value = false
	loading.value = false
}

watch(() => props.method, () => {
	handleMethodChange()
})

watch(selectedSigner, (selected) => {
	haveError.value = selected === null
	emit('update:signer', selected)
})

function injectIcons(items: IdentifyAccountRecord[]): NormalizedSignerOption[] {
	return items.map((item) => {
		const iconName = getIconName(item.iconName)
		return {
			identify: item.identify,
			displayName: item.displayName,
			subname: item.subname,
			method: item.method,
			acceptsEmailNotifications: item.acceptsEmailNotifications,
			...(iconName ? { iconName } : {}),
			label: item.displayName,
		}
	})
}

function normalizeSignerOption(item: SignerOption): SignerOption {
	const iconName = getIconName(item.iconName)
	return {
		...item,
		...(iconName ? { iconName } : {}),
		label: item.label ?? item.displayName ?? '',
		displayName: item.displayName ?? '',
		subname: item.subname ?? '',
	}
}

function toIconKey(iconName?: string): IconKey | undefined {
	if (typeof iconName !== 'string' || iconName.length === 0) {
		return undefined
	}

	const iconKey = `svg${iconName.charAt(0).toUpperCase()}${iconName.slice(1)}` as IconKey
	return iconKey in iconMap ? iconKey : undefined
}

function getIconName(iconName?: string): IconName | undefined {
	return toIconKey(iconName) ? iconName as IconName : undefined
}

function getOption(slotProps?: { option?: SignerOption } | SignerOption) {
	if ((slotProps as { option?: SignerOption })?.option) {
		return (slotProps as { option: SignerOption }).option
	}
	if (slotProps && typeof slotProps === 'object') {
		return slotProps as SignerOption
	}
	return {}
}

function getOptionLabel(slotProps?: { option?: SignerOption } | SignerOption) {
	const option = getOption(slotProps)
	return option.displayName || option.label || option.identify || option.subname || ''
}

function getOptionSubname(slotProps?: { option?: SignerOption } | SignerOption) {
	const option = getOption(slotProps)
	if (!option.subname || option.subname === option.displayName) {
		return ''
	}
	return option.subname
}

function getOptionIcon(slotProps?: { option?: SignerOption } | SignerOption) {
	const iconKey = toIconKey(getOption(slotProps).iconName)
	return iconKey ? iconMap[iconKey] : ''
}

async function _asyncFind(search: string, lookup = false) {
	search = search.trim()
	if (!search) {
		options.value = []
		loading.value = false
		return
	}

	const requestId = ++activeRequestId.value
	loading.value = true
	try {
		const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
			params: {
				search,
				method: props.method,
			},
		})
		if (requestId !== activeRequestId.value) {
			return
		}
		options.value = injectIcons(response.data.ocs.data as IdentifyAccountRecord[])
	} catch (error) {
		if (requestId === activeRequestId.value) {
			haveError.value = true
		}
	} finally {
		if (requestId === activeRequestId.value) {
			loading.value = false
		}
	}
}

const asyncFind = debounce((search: string, lookup = false) => {
	_asyncFind(search, lookup)
}, 500)

function focusInput() {
	if (selectedSigner.value) {
		return
	}
	nextTick(() => {
		const inputElement = select.value?.$el?.querySelector('input') as HTMLInputElement | null
		inputElement?.focus()
	})
}

function setupVisibilityObserver() {
	if (typeof IntersectionObserver === 'undefined' || !container.value) {
		return
	}
	intersectionObserver.value = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				focusInput()
			}
		})
	}, {
		threshold: 0.1,
	})
	intersectionObserver.value.observe(container.value)
}

onMounted(() => {
	if (Object.keys(props.signer).length > 0) {
		selectedSigner.value = normalizeSignerOption(props.signer as SignerOption)
	}
	setupVisibilityObserver()
	focusInput()
})

onBeforeUnmount(() => {
	intersectionObserver.value?.disconnect()
})

defineExpose({
	loading,
	options,
	selectedSigner,
	haveError,
	activeRequestId,
	noResultText,
	handleMethodChange,
	injectIcons,
	normalizeSignerOption,
	getOption,
	getOptionLabel,
	getOptionSubname,
	getOptionIcon,
	_asyncFind,
	asyncFind,
	focusInput,
	setupVisibilityObserver,
})
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
