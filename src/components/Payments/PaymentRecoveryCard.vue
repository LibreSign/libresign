<template>
	<div class="payment-recovery-card">

		<!-- Header -->
		<div class="payment-recovery-card__header">

			<div class="payment-recovery-card__icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 12a9 9 0 1 1-3.51-7.09" />
					<polyline points="21 3 21 9 15 9" />
				</svg>
			</div>

			<div class="payment-recovery-card__header-copy">

				<h3 class="payment-recovery-card__title">
					Resume payment
				</h3>

				<p class="payment-recovery-card__subtitle">
					You have an unfinished payment session.
				</p>

				<p v-if="parsedUpdatedAt" class="payment-recovery-card__session-age">
					Last active
					<NcDateTime :timestamp="parsedUpdatedAt" relative-time="short" ignore-seconds />
				</p>
			</div>
		</div>

		<!-- Content -->
		<div class="payment-recovery-card__content">

			<!-- Provider -->
			<div class="payment-recovery-card__provider">
				<PaymentRouteSummary mode="compact" passive :provider="providerLabel" :country="country"
					:region="props.payment.region || ''" :logo="selectedOption?.logo" :subtitle="instructions"
					:editable="false" />
			</div>

			<!-- Meta -->
			<div class="payment-recovery-card__meta">

				<div v-if="displayAmountFormatted" class="payment-recovery-card__meta-block">
					<div class="payment-recovery-card__meta-label">
						Amount
					</div>

					<div class="payment-recovery-card__meta-value">
						{{ displayAmountFormatted }}
					</div>
				</div>

				<div v-if="phoneNumber" class="payment-recovery-card__meta-block">
					<div class="payment-recovery-card__meta-label">
						Phone number
					</div>

					<div class="payment-recovery-card__meta-value">
						{{ phoneNumber }}
					</div>
				</div>
			</div>

			<div class="payment-recovery-card__note">
				<div class="payment-recovery-card__note-title">
					Please note:
				</div>

				<p class="payment-recovery-card__note-copy">
					If you did not receive the payment request on your phone,
					starting over is recommended.
				</p>

				<p class="payment-recovery-card__note-copy">
					If you already approved or completed the payment,
					you can safely resume checking the payment status.
				</p>
			</div>
		</div>

		<!-- Actions -->
		<div class="payment-recovery-card__actions">

			<button type="button" class="payment-recovery-card__discard" @click="$emit('start-over')">
				Start over
			</button>

			<button type="button" class="payment-recovery-card__resume" @click="$emit('resume')">
				Resume payment
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import NcDateTime from '@nextcloud/vue/components/NcDateTime'

import PaymentRouteSummary from './PaymentRouteSummary.vue'

import type { HydratedPayment } from '@/payment/types'

const props = defineProps<{
	payment: HydratedPayment
}>()

defineEmits<{
	resume: []
	'start-over': []
}>()

const selectedOption = computed(() => {
	if (!props.payment.options?.length) {
		return null
	}

	return props.payment.options.find(
		option =>
			option.provider === props.payment.mno &&
			option.country === props.payment.country,
	) ?? props.payment.options[0]
})

const providerLabel = computed(() => {

	if (!props.payment.mno) {
		return 'Mobile payment'
	}

	return formatProviderLabel(
		props.payment.mno,
	)
})

const parsedUpdatedAt = computed(() => {
   if (!props.payment.updatedAt) {
	 return null
   }

   return new Date(props?.payment?.updatedAt)
})

const country = computed(() => {
	return props.payment.country ?? 'Unknown region'
})

const phoneNumber = computed(() => {
	return props.payment.phoneNumber
})

const instructions = computed(() => {
	return props.payment.instructions
})

const displayAmountFormatted = computed(() => {
	return props.payment.displayAmountFormatted
})

function formatProviderLabel(value: string) {

	return value
		.split(/[\s_-]/g)
		.filter(Boolean)
		.map(
			part =>
				part.charAt(0).toUpperCase() +
				part.slice(1),
		)
		.join(' ')
}
</script>

<style scoped lang="scss">
.payment-recovery-card {
	display: flex;
	flex-direction: column;
	gap: 18px;
	width: 100%;
	max-width: 560px;
	margin: 20px auto;
	padding: 20px;
	border-radius: 20px;
	background: var(--color-main-background);
	animation:
		recovery-card-in 180ms ease;
}

@keyframes recovery-card-in {
	from {
		opacity: 0;
		transform: translateY(6px);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* ========================================================= */
/* HEADER */
/* ========================================================= */

.payment-recovery-card__header {
	display: flex;
	align-items: flex-start;
	gap: 14px;
}

.payment-recovery-card__icon {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 42px;
	height: 42px;

	border-radius: 999px;

	background:
		color-mix(in srgb,
			var(--color-primary-element) 12%,
			transparent);

	color: var(--color-primary-element);

	flex-shrink: 0;
}

.payment-recovery-card__header-copy {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.payment-recovery-card__title {
	margin: 0;

	font-size: 18px;
	font-weight: 700;
	line-height: 1.2;

	color: var(--color-main-text);
}

.payment-recovery-card__subtitle {
	margin: 0;

	font-size: 14px;
	line-height: 1.45;

	color: var(--color-text-maxcontrast);
}

.payment-recovery-card__session-age {
	margin: 2px 0 0;

	font-size: 12px;
	font-weight: 500;

	color: var(--color-text-maxcontrast);
	opacity: 0.62;
}

/* ========================================================= */
/* CONTENT */
/* ========================================================= */

.payment-recovery-card__content {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.payment-recovery-card__provider {
	display: flex;
	flex-direction: column;
}

/* ========================================================= */
/* META */
/* ========================================================= */

.payment-recovery-card__meta {
	display: flex;
	flex-direction: column;
	gap: 14px;

	padding-top: 2px;
}

.payment-recovery-card__meta-block {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.payment-recovery-card__meta-label {
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;

	color: var(--color-text-maxcontrast);
	opacity: 0.75;
}

.payment-recovery-card__meta-value {
	font-size: 15px;
	font-weight: 700;
	line-height: 1.3;

	color: var(--color-main-text);

	word-break: break-word;
}


/* ========================================================= */
/* NOTE */
/* ========================================================= */

.payment-recovery-card__note {
	display: flex;
	flex-direction: column;
	gap: 8px;

	padding: 14px 16px;

	border-radius: 14px;

	background:
		color-mix(
			in srgb,
			var(--color-warning) 10%,
			var(--color-main-background)
		);

	border:
		1px solid
		color-mix(
			in srgb,
			var(--color-warning) 18%,
			transparent
		);
}

.payment-recovery-card__note-title {
	font-size: 13px;
	font-weight: 700;

	color: var(--color-main-text);
}

.payment-recovery-card__note-copy {
	margin: 0;

	font-size: 13px;
	line-height: 1.5;

	color: var(--color-text-maxcontrast);

	opacity: 0.9;
}

/* ========================================================= */
/* ACTIONS */
/* ========================================================= */

.payment-recovery-card__actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;

	flex-wrap: wrap;

	gap: 10px;

	padding-top: 4px;
}

.payment-recovery-card__discard,
.payment-recovery-card__resume {
	display: inline-flex;
	align-items: center;
	justify-content: center;

	height: 42px;

	padding: 0 18px;

	border-radius: 12px;

	font-size: 13px;
	font-weight: 650;

	cursor: pointer;

	transition:
		transform 120ms ease,
		opacity 120ms ease,
		background 120ms ease,
		border-color 120ms ease;

	-webkit-tap-highlight-color: transparent;
}

.payment-recovery-card__discard {
	flex: 1;
	min-width: 140px;

	border: 1px solid var(--color-border);

	background: transparent;

	color: var(--color-text-maxcontrast);

	&:hover {
		background: var(--color-background-hover);
	}
}

.payment-recovery-card__resume {
	flex: 1;
	min-width: 180px;

	border: none;

	background: var(--color-primary-element);

	color: var(--color-primary-element-text);

	box-shadow:
		0 6px 18px rgba(0, 0, 0, 0.08);

	&:hover {
		opacity: 0.94;
		transform: translateY(-1px);
	}

	&:active {
		transform: translateY(0);
	}
}

/* ========================================================= */
/* MOBILE */
/* ========================================================= */

@media (max-width: 640px) {

	.payment-recovery-card {
		padding: 16px;
		border-radius: 18px;
		gap: 16px;
	}

	.payment-recovery-card__header {
		gap: 12px;
	}

	.payment-recovery-card__icon {
		width: 38px;
		height: 38px;
	}

	.payment-recovery-card__title {
		font-size: 17px;
	}

	.payment-recovery-card__subtitle {
		font-size: 13px;
	}

	.payment-recovery-card__actions {
		flex-direction: column-reverse;
		align-items: stretch;
	}

	.payment-recovery-card__discard,
	.payment-recovery-card__resume {
		width: 100%;
		min-width: 0;
	}
}
</style>
