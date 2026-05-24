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
		<NcAppNavigationItem v-if="canManagePreferences"
			:name="t('libresign', 'Preferences')"
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
import { computed, onMounted } from 'vue'

import { usePoliciesStore } from '../../store/policies'

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
const canRequestSign = loadState<boolean>('libresign', 'can_request_sign', false)
const initialEffectivePolicies = loadState('libresign', 'effective_policies', { policies: {} }) as {
	policies?: Record<string, { editableByCurrentActor?: boolean }>
}
const policiesStore = usePoliciesStore()

const canManagePreferences = computed(() => {
	if (!canRequestSign) {
		return false
	}

	return Object.values(policiesStore.policies).some((policy) => {
		if (!policy || typeof policy !== 'object') {
			return false
		}

		const policyState = policy as {
			canSaveAsUserDefault?: boolean
		}

		return policyState.canSaveAsUserDefault === true
	})
})

const canManagePolicies = computed(() => {
	if (isAdmin) {
		return true
	}

	const groupsRequestSignPolicy = policiesStore.policies.groups_request_sign
	if (groupsRequestSignPolicy && typeof groupsRequestSignPolicy === 'object') {
		const policyState = groupsRequestSignPolicy as { editableByCurrentActor?: boolean }
		if (typeof policyState.editableByCurrentActor === 'boolean') {
			return policyState.editableByCurrentActor
		}
	}

	return initialEffectivePolicies.policies?.groups_request_sign?.editableByCurrentActor === true
})

onMounted(() => {
	void policiesStore.fetchEffectivePolicies()
})

function getAdminRoute() {
	return generateUrl('settings/admin/libresign')
}

defineExpose({
	getAdminRoute,
	canManagePreferences,
	canManagePolicies,
})
</script>
