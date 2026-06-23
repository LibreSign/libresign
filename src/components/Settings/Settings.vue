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
import type { EffectivePolicyState } from '../../types/index'
import {
	canRenderPersonalPreferencePolicy,
	canRenderWorkbenchPolicyForGroupAdmin,
	isWorkbenchPolicyKey,
} from '../../views/Preferences/personalPreferenceVisibility'
import { realDefinitions } from '../../views/Settings/PolicyWorkbench/settings/realDefinitions'
import type { RealPolicyPersonalPreferenceContext } from '../../views/Settings/PolicyWorkbench/settings/realTypes'

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
const canManageGroupPolicies = config.can_manage_group_policies === true
const initialEffectivePolicies = loadState('libresign', 'effective_policies', { policies: {} }) as {
	policies?: Record<string, {
		editableByCurrentActor?: boolean
		canSaveAsUserDefault?: boolean
	}>
}
const policiesStore = usePoliciesStore()
const preferenceBehaviorContext: RealPolicyPersonalPreferenceContext = {
	getPolicy: (policyKey: string) => policiesStore.getPolicy(policyKey),
	saveUserPreference: (policyKey: string, value) => policiesStore.saveUserPreference(policyKey, value),
	clearUserPreference: (policyKey: string) => policiesStore.clearUserPreference(policyKey),
}

function getPreferenceBehaviorFor(policyKey: string) {
	return realDefinitions[policyKey as keyof typeof realDefinitions]?.personalPreferenceBehavior
}

function hasManageableWorkbenchPolicy(
	policies: Record<string, {
		editableByCurrentActor?: boolean
		canSaveAsUserDefault?: boolean
	}> | undefined,
): boolean {
	if (!policies) {
		return false
	}

	return Object.entries(policies).some(([policyKey, policy]) => {
		return isWorkbenchPolicyKey(policyKey)
			&& canRenderWorkbenchPolicyForGroupAdmin(
				policyKey,
				policy as Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault'>,
			)
	})
}

const canManagePreferences = computed(() => {
	return Object.entries(policiesStore.policies).some(([policyKey, policy]) => {
		const behavior = getPreferenceBehaviorFor(policyKey)
		if (behavior?.shouldRender) {
			return behavior.shouldRender(policy as EffectivePolicyState | null, preferenceBehaviorContext)
		}

		return canRenderPersonalPreferencePolicy(
			policyKey,
			policy as EffectivePolicyState | null,
		)
	})
})

const canManagePolicies = computed(() => {
	if (isAdmin) {
		return true
	}

	if (!canManageGroupPolicies) {
		return false
	}

	return hasManageableWorkbenchPolicy(policiesStore.policies as Record<string, {
		editableByCurrentActor?: boolean
		canSaveAsUserDefault?: boolean
	}>)
		|| hasManageableWorkbenchPolicy(initialEffectivePolicies.policies)
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
