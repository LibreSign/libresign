<template>
	<div
	class="payment-route-summary"
	:class="{
		'payment-route-summary--compact': mode === 'compact',
		'payment-route-summary--passive': passive,
	}"
>
		<div class="payment-route-summary__left">
			<span
	v-if="!passive"
	class="payment-route-summary__check"
	aria-hidden="true"
>
				<svg
					width="13"
					height="13"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2.5"
					stroke-linecap="round"
					stroke-linejoin="round"
				>
					<polyline points="20,6 9,17 4,12" />
				</svg>
			</span>

			<div class="payment-route-summary__content">
				<div class="payment-route-summary__main">

					<!-- COUNTRY -->
					<span
						v-if="countryMeta?.flag"
						class="payment-route-summary__flag"
					>
						{{ countryMeta.flag }}
					</span>

					<span class="payment-route-summary__country">
						{{ countryMeta?.country ?? region }}
					</span>

					<span class="payment-route-summary__divider">
						•
					</span>

					<!-- PROVIDER -->
					<template v-if="logo">
						<img
							:src="logo"
							:alt="provider"
							class="payment-route-summary__logo"
						>
					</template>

					<span class="payment-route-summary__provider">
						{{ provider }}
					</span>
				</div>

				<div
					v-if="resolvedSubtitle"
					class="payment-route-summary__meta"
				>
					{{ resolvedSubtitle }}
				</div>
			</div>
		</div>

		<button
			v-if="editable"
			class="payment-route-summary__change"
			@click.stop="$emit('change')"
			:disabled="disabled"
			type="button"
		>
			Change
		</button>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import {
	getPaymentCountryMeta,
} from '@/utils/paymentCountryRegistry'

const props = defineProps<{
	provider: string
	region: string
	logo?: string
	subtitle?: string
	editable?: boolean
	disabled?: boolean
	mode?: 'default' | 'compact'
	passive?: boolean
}>()

defineEmits<{
	change: []
}>()

/**
 * Resolve full payment country context
 * from canonical region code.
 */
const countryMeta = computed(() =>
	getPaymentCountryMeta(props.region)
)

/**
 * Default UX copy fallback.
 *
 * Allows future FX/custom messaging
 * while keeping component self-contained.
 */
const resolvedSubtitle = computed(() => {
	if (props.subtitle) {
		return props.subtitle
	}

	return 'Payment requests are sent to this number'
})
</script>

<style lang="scss" scoped>
.payment-route-summary {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 14px;

	background: var(--green-dim);
	border: 1px solid var(--green-border);
	border-radius: var(--radius-sm);

	padding: 11px 13px;

	animation: payment-route-in 0.24s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.payment-route-summary--compact {
	padding: 8px 10px;
	gap: 10px;

	.payment-route-summary__main {
		font-size: 12px;
		gap: 5px;
	}

	.payment-route-summary__meta {
		font-size: 10.5px;
		margin-top: 1px;
		line-height: 1.2;
	}

	.payment-route-summary__logo {
		width: 15px;
		height: 15px;
	}

	.payment-route-summary__change {
		font-size: 10.5px;
	}
}

.payment-route-summary--passive {
	background: #f8fafc;
	border-color: #e2e8f0;

	.payment-route-summary__main {
		color: #374151;
	}

	.payment-route-summary__meta {
		color: #64748b;
		opacity: 0.82;
	}

	.payment-route-summary__change {
		color: #64748b;
		opacity: 0.7;
	}

	.payment-route-summary__logo {
		background: #fff;
	}

	.payment-route-summary__divider {
		opacity: 0.3;
	}
}

@keyframes payment-route-in {
	from {
		opacity: 0;
		transform: translateY(4px) scale(0.98);
	}

	to {
		opacity: 1;
		transform: translateY(0) scale(1);
	}
}

.payment-route-summary__left {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	min-width: 0;
}

.payment-route-summary__check {
	display: flex;
	align-items: center;
	justify-content: center;

	color: var(--green);
	flex-shrink: 0;

	margin-top: 2px;
}

.payment-route-summary__content {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.payment-route-summary__main {
	display: flex;
	align-items: center;
	gap: 6px;

	font-size: 13px;
	font-weight: 600;
	color: var(--green-text);

	line-height: 1.2;
	flex-wrap: wrap;
}

.payment-route-summary__flag {
	font-size: 15px;
	line-height: 1;
}

.payment-route-summary__logo {
	width: 18px;
	height: 18px;
	object-fit: contain;
	border-radius: 999px;
	background: white;
	padding: 2px;

	border: 1px solid rgba(0, 0, 0, 0.05);
}

.payment-route-summary__provider {
	white-space: nowrap;
}

.payment-route-summary__divider {
	opacity: 0.45;
	font-weight: 500;
}

.payment-route-summary__country {
	font-weight: 600;
	white-space: nowrap;
}

.payment-route-summary__meta {
	margin-top: 3px;

	font-size: 11.5px;
	line-height: 1.35;

	color: var(--green-text);
	opacity: 0.72;
}

.payment-route-summary__change {
	border: none;
	background: transparent;

	font-size: 11.5px;
	font-weight: 600;

	color: var(--green-text);

	cursor: pointer;

	opacity: 0.72;

	padding: 0;

	text-decoration: underline;
	text-underline-offset: 2px;

	transition:
		opacity var(--transition-snappy),
		transform var(--transition-snappy);

	flex-shrink: 0;

	&:hover {
		opacity: 1;
		transform: translateY(-1px);
	}

	&:disabled {
		cursor: not-allowed;
		opacity: 0.4;
		transform: none;
	}
}

@media (max-width: 480px) {
	.payment-route-summary {
		align-items: flex-start;
	}

	.payment-route-summary__change {
		padding-top: 1px;
	}
}
</style>
