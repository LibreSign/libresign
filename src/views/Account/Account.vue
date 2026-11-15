<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppContent>
		<div class="content-account">
			<div class="user">
				<UserImage v-bind="{ user }" />
				<div class="details">
					<div class="user-details">
						<h3>{{ t('libresign', 'Details') }}</h3>
						<div class="user-display-name icon-user">
							<p>{{ user.displayName }}</p>
						</div>
						<p v-if="accountError" class="account-error" role="status">
							<!-- TRANSLATORS Shown when the account contact-detail request fails. -->
							{{ t('libresign', 'Email and phone details could not be loaded.') }}
						</p>
						<dl v-else class="user-contact">
							<dt>{{ t('libresign', 'Email') }}</dt>
							<dd>{{ loadingAccount ? t('libresign', 'Loading …') : emailAddress }}</dd>
							<dt>{{ t('libresign', 'Phone') }}</dt>
							<dd>{{ loadingAccount ? t('libresign', 'Loading …') : phoneNumber }}</dd>
						</dl>
						<p v-if="currentUser" class="user-contact-edit">
							<!-- TRANSLATORS Opens the signed-in user's contact settings in Nextcloud, not an editor inside LibreSign. -->
							<a :href="contactSettingsUrl">{{ t('libresign', 'Edit contact information') }}</a>
						</p>
					</div>
					<div class="user-password">
						<h3>{{ t('libresign', 'Certificate') }}</h3>
						<ManagePassword />
					</div>
				</div>
			</div>

			<div class="user">
				<Signatures />
				<Documents />
			</div>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import Documents from './partials/Documents.vue'
import ManagePassword from './partials/ManagePassword.vue'
import Signatures from './partials/Signatures.vue'
import UserImage from './partials/UserImage.vue'
import type { components } from '../../types/openapi/openapi'

defineOptions({
	name: 'Account',
})

type User = {
	uid: string
	displayName: string
}

type AccountMeResponse = components['schemas']['AccountMeResponse']
type OcsResponseData<T> = {
	ocs: {
		data: T
	}
}

const currentUser = getCurrentUser()
const user: User = {
	uid: currentUser?.uid ?? '',
	displayName: currentUser?.displayName ?? '',
}

const account = ref<AccountMeResponse | null>(null)
const loadingAccount = ref(Boolean(currentUser))
const accountError = ref(false)

// TRANSLATORS Fallback when the account has no email address configured.
const emailAddress = computed(() => account.value?.account.emailAddress?.trim() || t('libresign', 'Not provided'))
// TRANSLATORS Fallback when the account has no phone number configured.
const phoneNumber = computed(() => account.value?.settings.phoneNumber?.trim() || t('libresign', 'Not provided'))
const contactSettingsUrl = generateUrl('/settings/user/profile-contact')

onMounted(async () => {
	if (!currentUser) {
		return
	}

	try {
		const response = await axios.get<OcsResponseData<AccountMeResponse>>(
			generateOcsUrl('/apps/libresign/api/v1/account/me'),
		)
		account.value = response.data.ocs.data
	} catch {
		accountError.value = true
	} finally {
		loadingAccount.value = false
	}
})

defineExpose({
	user,
	t,
})
</script>

<style lang="scss" scoped>

.app-content{
	.content-account{
		width: 100%;
		margin-top: 50px;
		display: flex;
		height: 100%;

		.user{
			width: 50%;
			max-width: 350px;
			display: flex;
			flex-direction: column;
			align-items: flex-start;

			&:first-child {
				width: 25%;
				min-width: 240px;
			}

			@media screen and (max-width: 768px) {
				&, &:first-child {
					width: 50%;
				}
			}

			.details{
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 10px;
				border: 0;
			}

			.user-details{
				display: flex;
				flex-direction: column;
				width: 100%;
				border: 0;

				.user-display-name[class*='icon']{
					width: 100%;
					background-position: 0px 4px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
					margin-left: 12px;
					padding-left: 22px;
				}

				.user-contact{
					margin: 0 12px;

					dt{
						font-weight: bold;
					}

					dd{
						margin: 0 0 12px;
						overflow-wrap: anywhere;
					}
				}

				.account-error{
					margin: 0 12px;
					overflow-wrap: anywhere;
				}

				.user-contact-edit{
					margin: 0 12px;
				}
			}

			.user-password{
				display: flex;
				flex-direction: column;
			}
		}

		@media (max-width: 650px) {
			flex-direction: column;

			.user{
				width: 100%;
			}
		}
	}

}
</style>
