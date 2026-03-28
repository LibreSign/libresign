<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Policy catalog')"
		:description="t('libresign', 'Browse policy-managed settings in one place. Signing order is already connected to the live policy store.')">
		<div class="policy-catalog">
			<header class="policy-catalog__header">
				<div>
					<p class="policy-catalog__eyebrow">
						{{ t('libresign', 'Unified settings catalog') }}
					</p>
					<h3>{{ t('libresign', 'One list, live settings') }}</h3>
					<p>
						{{ t('libresign', 'Start from one catalog and open the setting you need. Signing order already uses the backend contract.') }}
					</p>
				</div>
				<div class="policy-catalog__summary">
					<span class="policy-catalog__summary-value">{{ itemCount }}</span>
					<span>{{ t('libresign', 'settings listed here') }}</span>
				</div>
			</header>

			<div class="policy-catalog__layout">
				<section class="policy-catalog__list" :aria-label="t('libresign', 'Policy-managed settings')">
					<button
						v-for="item in items"
						:key="item.key"
						type="button"
						class="policy-catalog__item"
						:class="{
							'policy-catalog__item--selected': item.key === selectedItemKey,
							'policy-catalog__item--available': item.available,
						}"
						@click="selectedItemKey = item.key">
						<div class="policy-catalog__item-header">
							<div>
								<p class="policy-catalog__item-key">{{ item.key }}</p>
								<h4>{{ item.label }}</h4>
							</div>
							<span class="policy-catalog__status" :class="`policy-catalog__status--${item.status}`">
								{{ item.statusLabel }}
							</span>
						</div>
						<p class="policy-catalog__item-description">
							{{ item.description }}
						</p>
						<p class="policy-catalog__item-summary">
							{{ item.summary }}
						</p>
					</button>
				</section>

				<section class="policy-catalog__detail">
					<div class="policy-catalog__detail-header">
						<p class="policy-catalog__eyebrow">
							{{ t('libresign', 'Current setting') }}
						</p>
						<h4>{{ selectedItem.label }}</h4>
						<p>
							{{ selectedItem.description }}
						</p>
					</div>

					<SignatureFlow v-if="selectedItem.key === 'signature_flow'" />

					<NcNoteCard v-else type="info">
						{{ t('libresign', 'This setting already appears in the catalog, but its dedicated policy editor is not wired to live persistence yet.') }}
					</NcNoteCard>
				</section>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../../store/policies'
import type { EffectivePolicyState } from '../../../types/index'
import SignatureFlow from '../SignatureFlow.vue'

defineOptions({
	name: 'PolicyCatalog',
})

type PolicyCatalogItem = {
	key: string
	label: string
	description: string
	summary: string
	status: 'ready' | 'planned'
	statusLabel: string
	available: boolean
}

const policiesStore = usePoliciesStore()
const selectedItemKey = ref('signature_flow')

const signatureFlowPolicy = computed(() => policiesStore.getPolicy('signature_flow'))

function getSignatureFlowSummary(policy: EffectivePolicyState | null): string {
	switch (policy?.effectiveValue) {
	case 'parallel':
		return t('libresign', 'Current effective value: Simultaneous (Parallel).')
	case 'ordered_numeric':
		return t('libresign', 'Current effective value: Sequential.')
	case 'none':
		return t('libresign', 'Current effective value: Disabled.')
	default:
		return t('libresign', 'Waiting for the effective policy value.')
	}
}

const items = computed<PolicyCatalogItem[]>(() => [
	{
		key: 'signature_flow',
		label: t('libresign', 'Signing order'),
		description: t('libresign', 'Define whether signers work in parallel or in a sequential order.'),
		summary: getSignatureFlowSummary(signatureFlowPolicy.value),
		status: 'ready',
		statusLabel: t('libresign', 'Available now'),
		available: true,
	},
	{
		key: 'signature_stamp',
		label: t('libresign', 'Signature stamp'),
		description: t('libresign', 'Reserve a dedicated policy shell for stamp placement and defaults.'),
		summary: t('libresign', 'Catalog entry created. Implementation wiring is the next step.'),
		status: 'planned',
		statusLabel: t('libresign', 'Next step'),
		available: false,
	},
	{
		key: 'identify_factors',
		label: t('libresign', 'Identification factors'),
		description: t('libresign', 'Prepare policy-level control over the required identification methods.'),
		summary: t('libresign', 'Catalog entry created. Implementation wiring is the next step.'),
		status: 'planned',
		statusLabel: t('libresign', 'Next step'),
		available: false,
	},
])

const selectedItem = computed(() => {
	return items.value.find(item => item.key === selectedItemKey.value) ?? items.value[0]
})

const itemCount = computed(() => items.value.length)

onMounted(async () => {
	if (!signatureFlowPolicy.value) {
		await policiesStore.fetchEffectivePolicies()
	}
})
</script>

<style scoped lang="scss">
.policy-catalog {
	margin-top: 1rem;
	display: flex;
	flex-direction: column;
	gap: 1.5rem;

	&__header {
		display: flex;
		justify-content: space-between;
		gap: 1rem;
		align-items: flex-start;
		padding: 1.25rem;
		border-radius: 20px;
		background:
			radial-gradient(circle at top left, color-mix(in srgb, var(--color-primary-element) 16%, transparent), transparent 50%),
			linear-gradient(180deg, color-mix(in srgb, var(--color-main-background) 92%, white), var(--color-main-background));
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 16%, var(--color-border-maxcontrast));

		h3,
		p {
			margin: 0;
		}
	}

	&__eyebrow,
	&__item-key {
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

	&__layout {
		display: grid;
		grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.2fr);
		gap: 1.25rem;
		align-items: start;
	}

	&__list,
	&__detail {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__item,
	&__detail {
		padding: 1rem;
		border-radius: 18px;
		border: 1px solid var(--color-border-maxcontrast);
		background: var(--color-main-background);
	}

	&__item {
		text-align: left;
		cursor: pointer;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
		transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease;

		&:hover {
			border-color: color-mix(in srgb, var(--color-primary-element) 30%, var(--color-border-maxcontrast));
			transform: translateY(-1px);
		}

		&--selected {
			border-color: var(--color-primary-element);
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 16%, transparent);
		}
	}

	&__item-header {
		display: flex;
		justify-content: space-between;
		gap: 1rem;
		align-items: flex-start;

		h4,
		p {
			margin: 0;
		}
	}

	&__item-description,
	&__item-summary,
	&__detail-header p {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}

	&__item-summary {
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__status {
		padding: 0.3rem 0.7rem;
		border-radius: 999px;
		font-size: 0.8rem;
		white-space: nowrap;
		background: color-mix(in srgb, var(--color-background-dark) 15%, var(--color-main-background));

		&--ready {
			background: color-mix(in srgb, #1e7a46 18%, var(--color-main-background));
		}

		&--planned {
			background: color-mix(in srgb, #9b6a18 18%, var(--color-main-background));
		}
	}

	&__detail {
		gap: 1rem;
		background:
			linear-gradient(180deg, color-mix(in srgb, var(--color-primary-element) 8%, var(--color-main-background)), var(--color-main-background));
		position: sticky;
		top: 1rem;
	}

	&__detail-header {
		h4,
		p {
			margin: 0;
		}
	}
}

@media (max-width: 1024px) {
	.policy-catalog {
		&__header,
		&__layout {
			display: flex;
			flex-direction: column;
		}

		&__summary {
			align-items: flex-start;
			text-align: left;
		}

		&__detail {
			position: static;
		}
	}
}
</style>
