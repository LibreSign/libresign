<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Document signing settings')"
		:description="t('libresign', 'Configure how signing works.')">
		<div class="policy-workbench">
			<header class="policy-workbench__header">
				<div>
					<p class="policy-workbench__eyebrow">
						{{ t('libresign', 'Live settings') }}
					</p>
					<h3>{{ t('libresign', 'One live setting, ready to configure') }}</h3>
					<p>
						{{ t('libresign', 'The workbench lists only settings already connected to the backend contract. Signing order is the current starting point.') }}
					</p>
				</div>
				<div class="policy-workbench__summary">
					<span class="policy-workbench__summary-value">1</span>
					<span>{{ t('libresign', 'setting available now') }}</span>
				</div>
			</header>

			<div class="policy-workbench__catalog">
				<article class="policy-workbench__setting-card">
					<div class="policy-workbench__setting-header">
						<div>
							<p class="policy-workbench__setting-key">signature_flow</p>
							<h4>{{ t('libresign', 'Signing order') }}</h4>
						</div>
						<span class="policy-workbench__status">
							{{ t('libresign', 'Available now') }}
						</span>
					</div>

					<p class="policy-workbench__setting-description">
						{{ t('libresign', 'Choose whether documents are signed in order or all at once.') }}
					</p>

					<ul class="policy-workbench__setting-metadata">
						<li>
							<strong>{{ t('libresign', 'Current effective value') }}:</strong>
							<span>{{ signingOrderSummary }}</span>
						</li>
						<li>
							<strong>{{ t('libresign', 'Available scopes') }}:</strong>
							<span>{{ t('libresign', 'Default for everyone and group rules') }}</span>
						</li>
					</ul>

					<NcButton
						variant="primary"
						:aria-label="t('libresign', 'Manage signing order')"
						@click="openSigningOrderDialog">
						{{ t('libresign', 'Manage') }}
					</NcButton>
				</article>
			</div>

			<NcDialog
				v-if="showSigningOrderDialog"
				:name="t('libresign', 'Signing order')"
				size="full"
				:can-close="true"
				@closing="closeSigningOrderDialog">
				<div class="policy-workbench__dialog">
					<header class="policy-workbench__dialog-header">
						<div>
							<p class="policy-workbench__eyebrow">
								{{ t('libresign', 'Signing order settings') }}
							</p>
							<h2>{{ t('libresign', 'Signing order') }}</h2>
							<p>
								{{ t('libresign', 'Set a default for everyone, then add group rules only when groups need different behavior.') }}
							</p>
						</div>
						<NcButton
							variant="secondary"
							:aria-label="t('libresign', 'Close signing order settings dialog')"
							@click="closeSigningOrderDialog">
							{{ t('libresign', 'Close') }}
						</NcButton>
					</header>

					<div class="policy-workbench__workspace">
						<SignatureFlow />
						<SignatureFlowGroupPolicy />
					</div>
				</div>
			</NcDialog>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../../store/policies'
import SignatureFlow from '../SignatureFlow.vue'
import SignatureFlowGroupPolicy from '../SignatureFlowGroupPolicy.vue'

defineOptions({
	name: 'SettingsPolicyWorkbench',
})

const policiesStore = usePoliciesStore()
const showSigningOrderDialog = ref(false)

const signatureFlowPolicy = computed(() => policiesStore.getPolicy('signature_flow'))

const signingOrderSummary = computed(() => {
	switch (signatureFlowPolicy.value?.effectiveValue) {
	case 'parallel':
		return t('libresign', 'Simultaneous (Parallel)')
	case 'ordered_numeric':
		return t('libresign', 'Sequential')
	case 'none':
		return t('libresign', 'Simultaneous or Sequential')
	default:
		return t('libresign', 'Waiting for current policy value')
	}
})

function openSigningOrderDialog() {
	showSigningOrderDialog.value = true
}

function closeSigningOrderDialog() {
	showSigningOrderDialog.value = false
}

onMounted(async () => {
	if (!signatureFlowPolicy.value) {
		await policiesStore.fetchEffectivePolicies()
	}
})

defineExpose({
	showSigningOrderDialog,
	openSigningOrderDialog,
	closeSigningOrderDialog,
})
</script>

<style scoped lang="scss">
.policy-workbench {
	margin-top: 1rem;
	display: flex;
	flex-direction: column;
	gap: 1.25rem;

	&__header {
		display: flex;
		justify-content: space-between;
		gap: 1rem;
		align-items: flex-start;
		padding: 1.25rem;
		border-radius: 20px;
		background:
			radial-gradient(circle at top left, color-mix(in srgb, var(--color-primary-element) 16%, transparent), transparent 48%),
			linear-gradient(180deg, color-mix(in srgb, var(--color-main-background) 94%, white), var(--color-main-background));
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 18%, var(--color-border-maxcontrast));

		h3,
		p {
			margin: 0;
		}
	}

	&__eyebrow,
	&__setting-key {
		margin: 0 0 0.4rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		font-size: 0.72rem;
		color: var(--color-text-maxcontrast);
	}

	&__summary {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		text-align: right;
		color: var(--color-text-maxcontrast);
	}

	&__summary-value {
		font-size: 2rem;
		line-height: 1;
		font-weight: 700;
		color: var(--color-main-text);
	}

	&__catalog {
		display: grid;
		grid-template-columns: minmax(280px, 520px);
		gap: 1rem;
	}

	&__setting-card {
		display: flex;
		flex-direction: column;
		gap: 0.85rem;
		padding: 1.15rem;
		border-radius: 18px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 18%, var(--color-border-maxcontrast));
		background: var(--color-main-background);
	}

	&__setting-header {
		display: flex;
		justify-content: space-between;
		gap: 1rem;
		align-items: flex-start;

		h4,
		p {
			margin: 0;
		}
	}

	&__status {
		padding: 0.3rem 0.7rem;
		border-radius: 999px;
		font-size: 0.8rem;
		white-space: nowrap;
		background: color-mix(in srgb, #1e7a46 18%, var(--color-main-background));
	}

	&__setting-description {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}

	&__setting-metadata {
		margin: 0;
		padding: 0;
		list-style: none;
		display: flex;
		flex-direction: column;
		gap: 0.35rem;

		li {
			display: flex;
			gap: 0.35rem;
			align-items: baseline;

			strong {
				white-space: nowrap;
			}
		}
	}

	&__dialog {
		width: min(1280px, 100%);
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		gap: 1.25rem;
	}

	&__dialog-header {
		display: flex;
		justify-content: space-between;
		gap: 1rem;
		align-items: flex-start;

		h2,
		p {
			margin: 0;
		}
	}

	&__workspace {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 1rem;
		align-items: start;
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__header,
		&__dialog-header,
		&__workspace {
			display: flex;
			flex-direction: column;
		}

		&__summary {
			align-items: flex-start;
			text-align: left;
		}

		&__catalog {
			grid-template-columns: 1fr;
		}
	}
}
</style>
