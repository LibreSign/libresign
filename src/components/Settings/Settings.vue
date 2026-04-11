<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<NcAppNavigationItem icon="icon-user" :name="t('libresign', 'Account')"
			:to="{name: 'Account'}">
			<template #icon>
				<NcIconSvgWrapper class="account-icon" :path="mdiAccount" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem :name="t('libresign', 'Preferences')"
			:to="{name: 'Preferences'}">
			<template #icon>
				<NcIconSvgWrapper class="preferences-icon" :path="mdiTuneVariant" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem v-if="canManagePolicies"
			:name="t('libresign', 'Policies')"
			:to="{name: 'Policies'}">
			<template #icon>
				<NcIconSvgWrapper class="policies-icon" :path="mdiShieldCheckOutline" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem v-if="isAdmin"
			:name="t('libresign', 'Administration')"
			:href="getAdminRoute()">
			<template #icon>
				<NcIconSvgWrapper class="tune-icon" :path="mdiCogOutline" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem icon="icon-star" :name="t('libresign', 'Rate LibreSign  ❤️')"
			href="https://apps.nextcloud.com/apps/libresign#comments">
			<template #icon>
				<NcIconSvgWrapper class="star-icon" :path="mdiStar" :size="20" />
			</template>
		</NcAppNavigationItem>
	</ul>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import type { EffectivePoliciesResponse } from '../../types'


import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiAccount,
	mdiCogOutline,
	mdiStar,
	mdiShieldCheckOutline,
	mdiTuneVariant,
} from '@mdi/js'

defineOptions({
	name: 'Settings',
})

const isAdmin = getCurrentUser()?.isAdmin ?? false
const config = loadState<{ can_manage_group_policies?: boolean }>('libresign', 'config', {})
const effectivePoliciesState = loadState<EffectivePoliciesResponse>('libresign', 'effective_policies', { policies: {} })
const hasDelegatedEditablePolicies = Object.values(effectivePoliciesState.policies ?? {}).some((policy) => {
	if (!policy || typeof policy !== 'object') {
		return false
	}
	const policyState = policy as {
		editableByCurrentActor?: boolean
		groupCount?: number
		userCount?: number
	}
	const hasDelegatedRule = (policyState.groupCount ?? 0) > 0 || (policyState.userCount ?? 0) > 0
	if (!hasDelegatedRule) {
		return false
	}

	return Boolean(policyState.editableByCurrentActor)
})

const canManagePolicies = isAdmin || (Boolean(config.can_manage_group_policies) && hasDelegatedEditablePolicies)

function getAdminRoute() {
	return generateUrl('settings/admin/libresign')
}

defineExpose({
	getAdminRoute,
	canManagePolicies,
})
</script>
