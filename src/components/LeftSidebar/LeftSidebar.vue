<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sidebar-wrapper">
		<NcAppNavigation>
			<template #list>

				<div class="sidebar-header">
					<h2>GoPaperless</h2>
					<p>Sign, Seal and Deliver</p>

					<NcButton class="new-doc-btn" :wide="true" variant="primary" :disabled="!canRequestSign" @click="goToRequest()">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" :size="20" />
						</template>
						<span class="button-text">New Document</span>
					</NcButton>
				</div>
			<div class="sidebar-navigation">
				<div class="sidebar-section">
					<p class="section-title">Core Actions</p>
					<NcAppNavigationItem v-if="canRequestSign"
						id="dashboard"
						:to="{name: 'Dashboard'}"
						:name="t('libresign', 'Dashboard')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiMonitorDashboard" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="canRequestSign"
						id="request-files"
						:to="{name: 'requestFiles'}"
						:name="t('libresign', 'Request')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiFileSign" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem id="fileslist"
						:to="{ name: 'fileslist' }"
						:name="t('libresign', 'Documents')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiFolderOutline" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
					<!-- TRANSLATORS: "Validate" here is a technical process: checking the cryptographic integrity of the signatures, the certificate chain and revocation status. It does NOT mean approving or authorizing something. Choose a word in your language that conveys "to check" or "to verify", not "to approve" or "to authorize". -->
					<NcAppNavigationItem id="validation"
						:to="{name: 'validation'}"
						:name="t('libresign', 'Validate')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiFileCheckOutline" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="config.identificationDocumentsFlow && config.isApprover"
						:to="{name: 'DocsIdDocsValidation'}"
						:name="t('libresign', 'Documents Validation')">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiAccountCheckOutline" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
				</div>

				<div class="sidebar-section" v-if="isAdmin">
					<p class="section-title">Admin Actions</p>
					<!-- ADMIN LINKS -->
					<NcAppNavigationItem
						:to="{name: 'AdminProducts'}"
						:name="t('libresign', 'Product Management')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiAnimationOutline" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem
						:to="{name: 'CrlManagement'}"
						:name="t('libresign', 'CRL Management')"
						@click="unselectFile">
						<template #icon>
							<div class="icon-wrapper">
								<NcIconSvgWrapper :path="mdiShieldLockOutline" :size="20" />
							</div>
						</template>
					</NcAppNavigationItem>
				</div>
			</div>
		</template>
		<template #footer>
			<NcAppNavigationSettings :title="t('libresign', 'Settings')">
				<Settings />
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { getCurrentInstance } from 'vue'
import {
	mdiAccountCheckOutline,
	mdiFileCheckOutline,
	mdiFileSign,
	mdiFolderOutline,
	mdiShieldLockOutline,
	mdiAnimationOutline,
	mdiPlus,
	mdiCloudUpload,
	mdiMonitorDashboard,
} from '@mdi/js'


import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { useRouter } from 'vue-router'

import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSettings from '@nextcloud/vue/components/NcAppNavigationSettings'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'

import Settings from '../Settings/Settings.vue'

import { useFilesStore } from '../../store/files.js'

defineOptions({
	name: 'LeftSidebar',
})

const router = useRouter()
const filesStore = useFilesStore()
const canRequestSign = loadState('libresign', 'can_request_sign', false)
const config = loadState('libresign', 'config', {
	identificationDocumentsFlow: false,
	isApprover: false,
})

const isAdmin = computed(() => {
	const user = getCurrentUser()
	return user?.isAdmin ?? false
})

function unselectFile() {
	filesStore.selectFile()
}

function goToSign() {
	const router = getCurrentInstance()?.proxy?.$router
	if (!router) {
		return
	}
	const route = router.resolve({ name: 'SignPDF' })
	window.location.href = route.href
}

function goToRequest() {
	unselectFile()
	filesStore.$reset()
	router.replace({
		name: 'requestFiles',
	})
}

defineExpose({
	isAdmin,
	goToSign,
	goToRequest,
})
</script>

<style lang="scss" scoped>

.sidebar-wrapper {

	&:deep(.app-navigation) {
		--color-main-background-blur: #F8FAFC;
	}
}

.sidebar-header {
  margin: 20px 0;
  padding: 8px 8px;

  &:first-child {
	margin-top: 0;
  }

  h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
  }

  p {
    font-size: 11px;
    color: var(--color-text-maxcontrast);
    letter-spacing: 1px;
  }
}

.new-doc-btn {
  margin-top: 16px;
  width: 100%;
  height: 44px;

  border-radius: 10px;

  background: linear-gradient(
    135deg,
    #04D56D,
    #22c55e
  );

  & span.button-text {
	color: #000;
	font-size: 14px;
	font-weight: 700;
    margin-bottom: 1px;
    padding: 2px 0;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
  }
}

.sidebar-navigation {
   --color-main-text: var(--color-text-maxcontrast);

   &:deep(.app-navigation-entry) {
		margin: 0 4px;
   }

   &:deep(.app-navigation-entry.active .icon-wrapper) {
	    --color-primary-element: #04994f;
		color: var(--color-primary-element);
   }

   &:deep(.app-navigation-entry:hover) {
		--color-main-text: #000;
		--color-background-hover: rgba(4, 213, 109, 0.06);
		transform: translateX(2px);
   }

   &:deep(.app-navigation-entry.active) {
	    --color-primary-element: rgba(4, 213, 109, 0.10);
		font-weight: 700;
   }

   &:deep(.app-navigation-entry.active:hover) {
	    --color-primary-element-hover: rgba(4, 213, 109, 0.3);
   }
}

.section-title {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
  letter-spacing: 1px;
  opacity: 0.7;
  color: var(--color-text-maxcontrast);
  margin: 18px 8px 8px 12px;
}

.app-navigation-entry  {
	padding: 5px 20px !important;
	border-radius: 8px !important;
	margin: 2px 4px;
	--color-main-text: var(--color-text-maxcontrast);
}

.app-navigation-entry:hover {
	background-color: var(--color-background-hover) !important;
	transition: all 0.2s ease;
}

.app-navigation-entry:active {
	background-color: var(--gp-primary-element) !important;
	transition: all 0.2s ease;
}

</style>
