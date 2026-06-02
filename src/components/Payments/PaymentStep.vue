<template>
	<Toaster richColors position="bottom-center" />
	<div class="payment-step">
		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- NOTECARD — always visible, anchors context             -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<transition name="slide-down" appear>
			<div v-if="!isLoadingData && product && displayAmount" class="notecard">
				<div class="notecard-left">

					<!-- Document -->
					<div class="notecard-doc">
						<div class="notecard-doc__icon">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
								stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
								<polyline points="14,2 14,8 20,8" />
							</svg>
						</div>

						<span class="notecard-doc__name">
							{{ document?.name ?? 'Document' }}
						</span>
					</div>

					<!-- Reason -->
					<div class="notecard-reason">
						Signing fee — one-time charge
					</div>

					<!-- FX -->
					<transition name="fade-soft">
						<div v-if="displayAmount.hasFx" class="notecard-fx">
							Charged in your local currency
						</div>
					</transition>
				</div>

				<!-- Amount -->
				<div class="notecard-right">

					<div class="notecard-amount">
						{{ displayAmount.primary }}
					</div>

					<transition name="fade-soft">
						<div v-if="displayAmount.secondary" class="notecard-secondary">
							≈ {{ displayAmount.secondary }}
						</div>
					</transition>

					<div class="notecard-currency">
						{{ displayAmount.currency }}
					</div>
				</div>
			</div>
		</transition>

		<!-- LOADING SKELETON -->
		<div v-if="isLoadingData" class="notecard notecard--skeleton">
			<div class="skeleton-block" style="width: 140px; height: 13px; border-radius: 4px;"></div>
			<div class="skeleton-block" style="width: 70px; height: 20px; border-radius: 4px;"></div>
		</div>

		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- METHOD TOGGLE                                          -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<div class="method-toggle" :class="{ 'method-toggle--locked': isMethodLocked }">
			<button class="method-tab" :class="{ active: selectedMethod === 'mobile' }" :disabled="isMethodLocked"
				@click="selectMethod('mobile')">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
					<line x1="12" y1="18" x2="12.01" y2="18" />
				</svg>
				Mobile money
			</button>
			<button class="method-tab" :class="{ active: selectedMethod === 'card' }" :disabled="isMethodLocked"
				@click="selectMethod('card')">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
					<line x1="1" y1="10" x2="23" y2="10" />
				</svg>
				Card
			</button>
		</div>

		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- METHOD BODY                                            -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<div class="method-body">

			<!-- ─── MOBILE TAB ─────────────────────────────────────── -->
			<transition name="tab-switch" mode="out-in">
				<div v-if="selectedMethod === 'mobile'" key="mobile" class="tab-content">

					<!-- PHONE FIELD -->
					<div class="phone-field">
						<label class="field-label">Mobile money number</label>
						<div class="phone-row" :class="{
							'phone-row--valid': resolution?.isValid,
							'phone-row--focused': phoneFocused,
							'phone-row--locked': hasActivePaymentSession || mnoDetection.state === 'detecting',
						}">
							<!-- <div class="phone-prefix">
                <span v-if="detectedFlag" class="phone-flag">{{ detectedFlag }}</span>
                <span class="phone-dialcode">{{ detectedDialCode }}</span>
              </div> -->
							<input ref="phoneInputRef" v-model="phoneInput" type="tel" inputmode="tel"
								class="phone-input" placeholder="+254 712 345 678"
								:disabled="hasActivePaymentSession || mnoDetection.state === 'detecting'"
								@focus="phoneFocused = true" @input="onPhoneInput" />
							<transition name="fade-icon">
								<span v-if="resolution?.isValid && mnoDetection.state !== 'detecting'"
									class="phone-valid-icon" aria-label="Valid">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
										<polyline points="20,6 9,17 4,12" />
									</svg>
								</span>
							</transition>
							<transition name="fade-icon">
								<span v-if="mnoDetection.state === 'detecting'" class="phone-detecting-icon"
									aria-label="Detecting">
									<span class="spinner spinner--xs"></span>
								</span>
							</transition>
						</div>
						<transition name="fade-soft">
							<p v-if="mnoDetection.state === 'idle' || !resolution?.isValid" class="phone-helper">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<circle cx="12" cy="12" r="10" />
									<line x1="12" y1="8" x2="12" y2="12" />
									<line x1="12" y1="16" x2="12.01" y2="16" />
								</svg>
								Enter the phone number linked to your mobile money account.
							</p>
						</transition>
					</div>

					<!-- ── MNO RESOLUTION AREA ─────────────────────────── -->

					<!-- DETECTING SKELETON -->
					<transition name="fade-soft">
						<div v-if="mnoDetection.state === 'detecting'" class="mno-detecting">
							<span class="spinner spinner--sm"></span>
							<span class="mno-detecting__text">Detecting your network&hellip;</span>
						</div>
					</transition>

					<!-- HIGH CONFIDENCE DETECTED -->
					<transition name="slide-up">
						<PaymentRouteSummary v-if="resolvedRoute" :provider="resolvedRoute.provider"
							:country="resolvedRoute.country" :logo="resolvedRoute.logo" :region="detectedRegion"
							subtitle="Payment requests will be sent to this number"
							:editable="canEditProvider && !isDaraja" :disabled="!canEditProvider || isDaraja"
							@change="openMnoSelector" />
					</transition>

					<!-- AMBIGUOUS — CHIP CONFIRMATION REQUIRED -->
					<transition name="slide-up">
						<div v-if="mnoDetection.state === 'suggested'" class="mno-chips-section">
							<p class="mno-chips-label">Looks like <span class="detected">{{ mnoDetection.mno }}.</span>
								Confirm below or pick another:</p>
							<div class="chips" role="group" aria-label="Select mobile provider">
								<button v-for="option in mnoDetection.options" :key="option.provider" class="chip"
									:class="{
										'chip--preselected': mnoDetection.mno === option.provider && !mnoDetection.selected,
										'chip--selected': mnoDetection.selected?.provider === option.provider,
									}" @click.stop="selectMnoOption(option)" :disabled="isProcessing">
									<img v-if="option.logo" :src="option.logo" :alt="option.provider"
										class="chip__logo">
									{{ formatMnoLabel(option.provider) }}
								</button>
							</div>
						</div>
					</transition>

					<!-- UNKNOWN — FULL SELECTOR -->
					<transition name="expand-smooth">
						<div v-if="!isDaraja && (mnoDetection.state === 'requires-selection' || mnoDetection.showSelector)"
							class="mno-chips-section">
							<p class="mno-chips-label">Select your provider...</p>
							<div class="chips" role="group" aria-label="Select mobile provider">
								<button v-for="option in mnoDetection.options" :key="option.provider" class="chip"
									:class="{ 'chip--selected': mnoDetection.selected?.provider === option.provider }"
									@click.stop="selectMnoOption(option)" :disabled="isProcessing">
									<img v-if="option.logo" :src="option.logo" :alt="option.provider"
										class="chip__logo">
									{{ formatMnoLabel(option.provider) }}
								</button>
							</div>
						</div>
					</transition>

				</div>

				<!-- ─── CARD TAB ────────────────────────────────────────── -->
				<div v-else key="card" class="tab-content tab-content--card">
					<div class="card-logos">
						<span class="card-logo">VISA</span>
						<span class="card-logo">MC</span>
					</div>
					<div class="card-hint">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
							<polyline points="15,3 21,3 21,9" />
							<line x1="10" y1="14" x2="21" y2="3" />
						</svg>
						You'll be redirected to our secure payment page. No card details are stored here.
					</div>
				</div>
			</transition>

		</div>

		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- STATUS MESSAGES                                        -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<transition :name="transitionName" mode="out-in">
			<div :key="statusKey">

				<!-- OFFLINE -->
				<div v-if="payment.isOffline.value" class="status-box status-box--offline">
					<span class="spinner spinner--sm"></span>
					<div class="status-text">
						<span class="status-text__main">No connection</span>
						<span class="status-text__sub">Waiting to reconnect&hellip;</span>
					</div>
				</div>

				<!-- PROCESSING -->
				<div v-else-if="payment.state.value === 'processing'" class="status-box status-box--processing">
					<span class="spinner spinner--sm"></span>
					<div class="status-text">
						<span class="status-text__main">{{ paymentMessage }}</span>
						<span class="status-text__sub">Don't close this window</span>
					</div>
					<div class="progress-dots" aria-hidden="true">
						<span class="dot" :class="{ 'dot--on': processingStage >= 0 }"></span>
						<span class="dot" :class="{ 'dot--on': processingStage >= 1 }"></span>
						<span class="dot" :class="{ 'dot--on': processingStage >= 2 }"></span>
					</div>
				</div>

				<!-- TIMEOUT -->
				<div v-else-if="payment.state.value === 'timeout'" class="status-box status-box--warning">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="12" cy="12" r="10" />
						<polyline points="12,6 12,12 16,14" />
					</svg>
					<div class="status-text">
						<span class="status-text__main">Still waiting for payment confirmation</span>
						<span class="status-text__sub">
							Click Retry Payment below to check the latest payment status.
						</span>
					</div>
				</div>

				<!-- ERROR -->
				<div v-else-if="payment.state.value === 'error'" class="status-box status-box--error">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="12" cy="12" r="10" />
						<line x1="12" y1="8" x2="12" y2="12" />
						<line x1="12" y1="16" x2="12.01" y2="16" />
					</svg>
					<div class="status-text">
						<span class="status-text__main">{{ payment.error.value }}</span>
						<span class="status-text__sub">You can still try again</span>
					</div>
				</div>

				<!-- SUCCESS -->
				<div v-else-if="payment.state.value === 'success'" class="status-box status-box--success">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
						stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="20,6 9,17 4,12" />
					</svg>
					<span class="status-text__main">Payment confirmed</span>
				</div>

			</div>
		</transition>

		<transition name="fade-soft">
			<div v-if="payment.showRecoveryAction.value" class="payment-reset-card">
				<div class="payment-reset-card__content">
					<div class="payment-reset-card__title">
						Having trouble completing payment?
					</div>

					<div class="payment-reset-card__description">
						You can safely restart the payment flow or choose a different
						payment method.
					</div>
				</div>

				<button class="payment-reset-card__button" @click="handleCancelPaymentSession">
					Restart Payment
				</button>
			</div>
		</transition>

		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- CTA                                                    -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<button class="cta" :class="{
			'cta--active': canContinue && !isProcessing,
			'cta--loading': isProcessing,
			'cta--disabled': !canContinue || isProcessing,
			'cta--card': selectedMethod === 'card' && canContinue,
		}" :disabled="!canContinue || isProcessing" @click="handlePay">
			<transition name="fade-icon" mode="out-in">
				<span v-if="isProcessing" key="spin" class="cta__spinner">
					<span class="spinner spinner--sm spinner--light"></span>
				</span>
				<span v-else-if="selectedMethod === 'card' && canContinue" key="arrow" class="cta__icon"
					aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<line x1="5" y1="12" x2="19" y2="12" />
						<polyline points="12,5 19,12 12,19" />
					</svg>
				</span>
			</transition>
			<span class="cta__label">{{ buttonLabel }}</span>
		</button>

		<!-- ═══════════════════════════════════════════════════════ -->
		<!-- FOOTER                                                 -->
		<!-- ═══════════════════════════════════════════════════════ -->
		<div class="footer">
			<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
				stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
				<path d="M7 11V7a5 5 0 0 1 10 0v4" />
			</svg>
			Secured
			<span class="footer__links">
				<a href="https://tendaworld.com/gopaperless/" target="_blank" class="footer__link">Terms</a>
				<a href="https://tendaworld.com/gopaperless/" target="_blank" class="footer__link">Privacy</a>
			</span>
		</div>

	</div>
</template>

<script setup lang="ts">
/**
 * =========================================================
 * PAYMENT STEP (UX ORCHESTRATOR)
 * =========================================================
 *
 * This component controls the USER FLOW only.
 *
 * It does NOT manage payment state — backend does.
 *
 * Responsibilities:
 * - capture phone input
 * - resolve provider hint (Daraja vs DPO)
 * - handle UX phases (detect → confirm → charge)
 * - trigger usePayment actions
 *
 * Flow split:
 * - Mobile (DPO): detect → confirm → charge → poll
 * - Daraja: initiate → poll
 * - Card: initiate → redirect
 *
 * Retry / resume flows are handled by usePayment (not here).
 */
import { ref, computed, watch, onMounted, onUnmounted, reactive } from 'vue'
import { Toaster } from 'vue-sonner'
import 'vue-sonner/style.css'

import {
	usePayment,
	type MnoOption,
	type MobilePaymentContext,
	type PaymentMethod,
	type ResolvedPaymentRoute,
	type HydratedPayment,
	type SelectedMno
} from '@/payment'
import { getProductByCode } from '@/payment/product'
import { getUser } from '@/payment/user'
import { showError, showInfo, showSuccess } from '@/services/toast'

import { resolvePhone, formatAsYouType } from '@/utils/phoneResolver'
import { normaliseRegion } from '@/utils/mobileMoney'
import '@/style/global.scss'
import PaymentRouteSummary from './PaymentRouteSummary.vue'
import { usePaymentRecovery } from '@/payment/usePaymentRecovery'
import { usePaymentRouting } from '@/payment/usePaymentRouting'

// ─────────────────────────────────────────────────────────────
// Props / Emits
// ─────────────────────────────────────────────────────────────

const props = defineProps<{
	signUuid: string
	signRequestId: number
	document: any
	signer: any
	productCode: string
	initialPayment?: HydratedPayment | null
}>()

const emit = defineEmits([
	'payment-success',
	'state-change',
	'payment-runtime-invalid',
])

// ─────────────────────────────────────────────────────────────
// External state
// ─────────────────────────────────────────────────────────────

const payment = usePayment()
const { discardRecovery } = usePaymentRecovery()
const routing = usePaymentRouting()

// ─────────────────────────────────────────────────────────────
// UI state
// ─────────────────────────────────────────────────────────────

const selectedMethod = ref<PaymentMethod>('mobile')
const phoneFocused = ref(false)
const phoneInputRef = ref<HTMLInputElement | null>(null)
const phoneInput = ref('')
const resolution = ref<ReturnType<typeof resolvePhone> | null>(null)
const instructions = ref<string | null>(null)
const processingStage = ref(0)

const product = ref<any>(null)
const user = ref<any>(null)
const isLoadingData = ref(true)
const hasEmittedSuccess = ref(false)
const mobilePaymentContext = ref<MobilePaymentContext | null>(null)
const isHydrating = ref(false)

// ─────────────────────────────────────────────────────────────
// MNO DETECTION STATE
//
// This reactive object drives all the MNO UX in the template.
//
// States:
//   idle             → user hasn't blurred yet
//   detecting        → initiate() call in flight (shows skeleton)
//   detected         → confidence=high, mno resolved silently
//   requires-selection → confidence=ambiguous/unknown, show chip selector
//   selected         → user picked from selector manually
// ─────────────────────────────────────────────────────────────
const mnoDetection = reactive<{
	state:
	| 'idle'
	| 'detecting'
	| 'detected' // high confidence
	| 'suggested' // ambiguous
	| 'requires-selection' // unknown
	| 'selected'
	confidence?: 'high' | 'ambiguous' | 'unknown' | null
	mno?: string | null
	country?: string | null
	reference: string | null
	options?: MnoOption[]
	selected: MnoOption | null
	showSelector: boolean      // true when user clicks "Change?" on a high-confidence result
}>({
	state: 'idle',
	confidence: null,
	mno: null,
	country: null,
	reference: null,
	options: [],
	selected: null,
	showSelector: false,
})

// ─────────────────────────────────────────────────────────────
// Phone helpers
// ─────────────────────────────────────────────────────────────
const detectedRegion = ref<string>('KE')

const isDaraja = computed(() => {
	/**
	 * ACTIVE EXECUTION CONTEXT
	 * Backend-selected provider is source of truth.
	 */
	if (payment.provider.value) {
		return payment.provider.value === 'daraja'
	}

	/**
	 * PRE-EXECUTION UX HINT
	 * Used before orchestration begins.
	 */
	return resolution.value?.provider === 'daraja'
})

// ─────────────────────────────────────────────────────────────
// Normalised phone (for payload)
// ─────────────────────────────────────────────────────────────

const normalisedPhone = computed(() => resolution.value?.e164 ?? '')

/**
 * Controls CTA availability
 *
 * Ensures:
 * - valid phone input
 * - provider resolved (if required)
 */
const canContinue = computed(() => {
	if (selectedMethod.value === 'card') return true

	const hasValidPhone = !!resolution.value?.isValid

	if (!hasValidPhone) return false

	if (isDaraja.value) {
		return true // Daraja doesn't require MNO, so as long as phone is valid we can continue
	}

	// DPO first click should still be allowed
	if (!mnoDetection.reference && !isDaraja.value) {
		return true
	}

	// Otherwise, any resolved state is fine
	switch (mnoDetection.state) {
		// high confidence
		case 'detected':
			return true

		// user selected
		case 'selected':
			return true

		// ambiguous
		case 'suggested':
			return !!mnoDetection.selected // ambiguous must confirm

		// unknown
		case 'requires-selection':
			return !!mnoDetection.selected

		// otherwise
		default:
			return false
	}
})

const isProcessing = payment.isProcessing

const buttonLabel = computed(() => {
	if (payment.isOffline.value) return 'Waiting for connection…'

	if (mnoDetection.state === 'detecting') {
		return 'Detecting your network…'
	}

	switch (payment.state.value) {
		case 'timeout':
			return 'Retry payment'

		case 'error':
			return 'Try again'

		case 'processing':
			return 'Approve on your phone…'

		case 'requesting':
			return 'Sending payment request...'

		case 'initiating':
			return 'Starting payment…'

		default:

			if (selectedMethod.value === 'card') {
				return 'Continue to card payment'
			}

			if (
				mnoDetection.state === 'detected' ||
				mnoDetection.state === 'selected'
			) {
				return paymentDisplayLabel.value
					? `Confirm — pay ${paymentDisplayLabel.value}`
					: 'Confirm & pay'
			}

			if (
				mnoDetection.state === 'suggested' ||
				mnoDetection.state === 'requires-selection'
			) {
				return mnoDetection.selected
					? `Confirm — pay ${paymentDisplayLabel.value ?? ''}`
					: 'Select a provider to continue'
			}

			return 'Continue'
	}
})

let hasShownOfflineToast = false

const paymentMessage = computed(() => {
	if (payment.isOffline.value) {
		return `Connection lost. We'll resume automatically…`
	}

	if (instructions.value) return instructions.value

	if (processingStage.value === 2) {
		return 'Almost there… waiting for confirmation'
	}

	if (processingStage.value === 1) {
		return 'Still working… this can take a few seconds'
	}

	return resolution.value?.provider === 'daraja'
		? 'Check your phone and enter your M-Pesa PIN'
		: 'Approve payment on your phone'
})

const statusKey = computed(() => {
	if (payment.isOffline.value) return 'offline'
	return payment.state.value
})

const transitionName = computed(() => {
	if (payment.state.value === 'success') return 'success-pop'
	return 'status-swap'
})


const resolvedRoute = computed<ResolvedPaymentRoute | null>(() => {
	// Daraja has a fixed flow and doesn't require MNO detection,
	// so we can shortcut to the resolved route immediately
	if (isDaraja.value && resolution.value?.region === 'KE') {
		return {
			provider: 'M-Pesa',
			country: 'KE',
			flow: 'callback',
			source: 'daraja',
		}
	}

	// USER SELECTED
	if (mnoDetection.selected) {
		return {
			...mnoDetection.selected,
			flow: 'mobile_direct',
			source: 'dpo-selected',
		}
	}

	// AUTO DETECTED
	if (
		mnoDetection.mno &&
		mnoDetection.country
	) {
		const matchedOption = mnoDetection.options?.find(option =>
			option.provider === mnoDetection.mno &&
			option.country === mnoDetection.country
		)

		return {
			provider: matchedOption?.provider ?? mnoDetection.mno,
			country: matchedOption?.country ?? mnoDetection.country,
			logo: matchedOption?.logo,
			currency: matchedOption?.currency,
			instructions: matchedOption?.instructions,
			flow: 'mobile_direct',
			source: 'dpo-detected',
		}
	}
	return null
})

const paymentDisplayLabel = computed(() => {
	if (displayAmount.value?.primary) {
		return displayAmount.value.primary
	}

	if (product.value) {
		return formatAmount(
			product.value.amount,
			product.value.currency
		)
	}

	return null
})

const displayAmount = computed(() => {
	if (!product.value) {
		return null
	}

	// FX/payment-aware amount
	if (
		mobilePaymentContext.value?.displayAmountFormatted &&
		mobilePaymentContext.value?.displayCurrency
	) {
		return {
			primary:
				mobilePaymentContext.value.displayAmountFormatted,

			currency:
				mobilePaymentContext.value.displayCurrency,

			secondary:
				formatAmount(
					product.value.amount,
					product.value.currency
				),

			hasFx: (
				mobilePaymentContext.value.displayCurrency !==
				product.value.currency
			),
		}
	}

	// fallback before payment creation
	return {
		primary: formatAmount(
			product.value.amount,
			product.value.currency
		),

		currency: product.value.currency,

		secondary: null,

		hasFx: false,
	}
})

const isMethodLocked = computed(() => {
	return (
		isProcessing.value ||
		!!payment.activeReference.value
	)
})

const hasActivePaymentSession = computed(() => {
	return !!payment.activeReference.value
})

const hasChargedPayment = computed(() => {
	return payment.alreadyCharged.value
})

const canEditProvider = computed(() => {
	return (
		!!payment.activeReference.value &&
		!hasChargedPayment.value &&
		payment.state.value === 'idle'
	)
})

/**
 * Sync payment state → parent modal
 */
watch(() => payment.state.value, (state) => {
	emit('state-change', state)
	if (state === 'success' && !hasEmittedSuccess.value) {
		hasEmittedSuccess.value = true

		// allow animation to finish then close modal and sign
		setTimeout(() => {
			emit('payment-success')
		}, 1000)
	}
})

/**
 * Phone input watcher
 */
watch(phoneInput, (val) => {
	if (isHydrating.value) return

	/**
	 * Prevent runtime provider drift during
	 * active orchestration session.
	 */
	if (payment.providerLocked.value) {
		return
	}

	const formatted = formatAsYouType(val)

	if (formatted !== val) {
		phoneInput.value = formatted
		return
	}

	const res = resolvePhone(formatted)

	resolution.value = res

	if (res?.region) {
		detectedRegion.value = res.region
	}
})

/**
 * Network detection watcher
 */
watch(payment.isOffline, (offline) => {
	if (offline) {
		if (!hasShownOfflineToast) {
			showInfo(`Connection lost. We'll keep trying…`)
			hasShownOfflineToast = true
		}
	} else {
		if (payment.state.value === 'processing') {
			showSuccess('Back online. Resuming payment…')
			hasShownOfflineToast = false
		}
	}
})

/**
 * Processing stage timer
 */
watch(() => payment.state.value, (state) => {
	if (state === 'processing') {
		processingStage.value = 0
		setTimeout(() => (processingStage.value = 1), 2500)
		setTimeout(() => (processingStage.value = 2), 6000)
	}
})

// ─────────────────────────────────────────────────────────────
// Phone input handlers
// ─────────────────────────────────────────────────────────────

function onPhoneInput() {
	if (hasActivePaymentSession.value) {
		return
	}

	resetMnoDetectionState()
}

// ─────────────────────────────────────────────────────────────
// MNO selector actions
// ─────────────────────────────────────────────────────────────

async function openMnoSelector() {
	if (isDaraja.value) {
		return
	}

	if (
		isProcessing.value ||
		!canEditProvider.value
	) {
		return
	}
	mnoDetection.showSelector = false

	setTimeout(() => {
		mnoDetection.state = 'requires-selection'
		mnoDetection.showSelector = true
	}, 120)

	const options = mnoDetection.options && mnoDetection.options.length ? mnoDetection.options : []
	if (!options.length && mnoDetection.reference && mnoDetection.country) {
		try {
			const response = await payment.fetchMobileOptions(mnoDetection.reference, mnoDetection.country)
			mnoDetection.options = response.options
		} catch {
			// options fetch failed — selector will be empty
		}
	}
}

function selectMnoOption(option: MnoOption) {
	mnoDetection.selected = option
	mnoDetection.mno = option.provider
	mnoDetection.country = option.country
	mnoDetection.state = 'selected'
	mnoDetection.showSelector = false
}

async function handlePay() {
	if (payment.state.value === 'processing' || payment.state.value === 'hydrating') return

	const canContinueExistingFlow =
		!!mnoDetection.reference &&
		(
			mnoDetection.state === 'detected' ||
			mnoDetection.state === 'selected' ||
			mnoDetection.state === 'requires-selection' ||
			mnoDetection.state === 'suggested'
		)

	if (
		(payment.state.value === 'timeout' ||
			payment.state.value === 'error') &&
		!canContinueExistingFlow
	) {
		return retryPayment()
	}

	try {
		if (selectedMethod.value === 'mobile') {
			await handleMobilePayment()
		} else if (selectedMethod.value === 'card') {
			await handleCardPayment()
		} else {
			showError(`Invalid payment method selected`)
			return
		}


	} catch (err) {
		payment.state.value = 'error'

		console.error('[PaymentStep] Payment failed', err)
	}
}

// To be used within handlePay
async function handleMobilePayment() {

	const resolvedPhone = resolution.value

	if (!resolvedPhone?.isValid) {
		showError(
			'Enter a valid phone number and try again.'
		)

		return
	}

	/**
	 * MOBILE PAYMENT PHASE 1
	 *
	 * Creates or initiates a payment session.
	 *
	 * Depending on backend routing strategy this may:
	 * - immediately dispatch provider execution
	 * - require explicit MNO confirmation
	 * - create a resumable payment reference
	 */
	if (!payment.activeReference.value) {

		payment.state.value = 'initiating'

		const response = await payment.initiateOnly({
			// UX/provider preference hint only.
			// Backend remains orchestration authority.
			provider: isDaraja.value
				? 'daraja'
				: 'dpo',

			paymentMethod: 'mobile',
			phoneNumber: resolvedPhone.e164,
			signRequestId: props.signRequestId,
			signUuid: props.signUuid,
			productCode: props.productCode,
			userId: user.value?.uid,
			userEmail: props.signer.email || user.value?.emailAddress,
		})
		/**
		 * Defensive validation.
		 *
		 * A payment session without:
		 * - reference
		 * - flow
		 *
		 * is considered unrecoverable.
		 */
		if (!response?.reference || !response?.flow) {
			payment.state.value = 'error'
			payment.error.value =
				'Unable to start payment session. Please try again.'

			showError(`Unable to start payment session.`)

			setTimeout(() => {
				showInfo(
					'Restarting payment session. Please try again.'
				)
				payment.activeReference.value = null
				mnoDetection.reference = null
				resetPaymentFlow()
				emit('payment-runtime-invalid')
			}, 2500)

			throw new Error(
				'[Payment] invalid initiation response'
			)
		}
		/**
		 * Active payment runtime reference.
		 *
		 * Source of truth for:
		 * - recovery
		 * - polling
		 * - reconciliation
		 * - resume flows
		 */
		payment.activeReference.value =
			response.reference

		// Assign provider to payment execution state and lock provider (BE routing truth)
		payment.lockPaymentProvider(response.provider)

		/**
		 * Shared mobile payment display context.
		 */
		mobilePaymentContext.value = {
			displayAmount:
				response.displayAmount,

			displayAmountFormatted:
				response.displayAmountFormatted,

			displayCurrency:
				response.displayCurrency,

			phoneNumber:
				response.phoneNumber,
		}

		/**
		 * Hydrate detected region.
		 *
		 * Prefer backend-resolved region first,
		 * otherwise fallback to FE normalization.
		 */
		const resolvedRegion =
			response.phoneNumberRegion ??
			normaliseRegion(response.country)

		if (resolvedRegion) {
			detectedRegion.value =
				resolvedRegion
		}

		/**
		 * Preserve backwards compatibility
		 * while payment.activeReference becomes
		 * the primary runtime reference source.
		 */
		mnoDetection.reference =
			response.reference

		mnoDetection.confidence =
			response.confidence

		mnoDetection.mno =
			response.mno

		mnoDetection.country =
			response.country

		mnoDetection.options =
			response.options ?? []

		/**
		 * ==================================================
		 * CALLBACK / AUTO-EXECUTION FLOWS
		 * ==================================================
		 *
		 * Provider execution already occurred.
		 * FE should immediately begin reconciliation.
		 */
		if (routing.shouldStartPolling(response)) {

			instructions.value =
				response.instructions ??
				'Check your phone and complete the payment request.'

			payment.state.value = 'processing'

			payment.startPolling(
				response.reference,
				response.flow
			)

			return
		}

		/**
		 * ==================================================
		 * MOBILE_DIRECT / SELECTION FLOWS
		 * ==================================================
		 */

		switch (response.confidence) {

			case 'high':
				mnoDetection.state = 'detected'
				break

			case 'ambiguous':
				mnoDetection.state = 'suggested'
				break

			case 'unknown':
			default:
				mnoDetection.state = 'requires-selection'
				break
		}

		/**
		 * Waiting for explicit user confirmation.
		 */
		payment.state.value = 'idle'

		return
	}

	/**
	 * ==================================================
	 * MOBILE PAYMENT PHASE 2
	 * ==================================================
	 *
	 * User explicitly confirmed:
	 * - MNO
	 * - country
	 *
	 * Existing payment reference is charged.
	 */

	const mnoToUse =
		mnoDetection.selected?.provider ??
		mnoDetection.mno

	const countryToUse =
		mnoDetection.selected?.country ??
		mnoDetection.country

	/**
	 * Prevent execution before provider
	 * selection requirements are satisfied.
	 */
	if (
		payment.activeReference.value &&
		!payment.alreadyCharged
	) {

		const readyForCharge =
			mnoDetection.state === 'detected' ||
			(
				mnoDetection.state === 'selected' &&
				!!mnoToUse
			)

		if (!readyForCharge) {

			showError(
				'Please select your mobile network provider to continue.'
			)

			return
		}
	}

	/**
	 * Prefer backend-confirmed phone context.
	 * Fallback to current normalized input.
	 */
	const phoneNumber =
		mobilePaymentContext.value?.phoneNumber ??
		normalisedPhone.value

	/**
	 * Final defensive validation before charge.
	 */
	if (
		!payment.activeReference.value ||
		!phoneNumber ||
		!mnoToUse ||
		!countryToUse
	) {

		payment.state.value = 'error'

		payment.error.value =
			'Unable to continue payment. Please restart the payment process.'

		showError(
			'Payment session expired or became invalid. Restarting payment flow.'
		)

		payment.activeReference.value = null
		mnoDetection.reference = null
		setTimeout(() => {
			resetPaymentFlow()
			emit('payment-runtime-invalid')
		}, 2500)

		throw new Error(
			'[Payment] invalid charge payload'
		)
	}

	await payment.chargeExistingReference({
		reference:
			payment.activeReference.value,
		phoneNumber,
		mno: mnoToUse,
		mnoCountry: countryToUse,
		signRequestId:
			props.signRequestId,
		signUuid:
			props.signUuid,
	})
}

// To be used within handlePay
async function handleCardPayment() {
	// Card payment flow is simpler since we don't have to detect MNO or poll for status.
	// We just initiate the payment and redirect the user to the card form.

	// State handled directly in usePayment
	// If initiation is successful, usePayment will
	// handle the redirect and subsequent state changes.
	await payment.startPayment({
		provider: 'dpo',
		signRequestId: props.signRequestId,
		signUuid: props.signUuid,
		paymentMethod: 'card',
		userEmail: props.signer.email,
		userId: user.value?.uid,
		productCode: props.productCode,
		redirectUrl: payment.buildPaymentRedirectUrl()
	})
}

/**
 * Retry existing payment session.
 *
 * Reuses existing backend payment reference
 * without creating a new payment session.
 */
async function retryPayment() {
	try {
		const payRes = await payment.startPayment({
			signRequestId: props.signRequestId,
			signUuid: props.signUuid,
			paymentMethod: selectedMethod.value === 'card' ? 'card' : 'mobile',
			phoneNumber: normalisedPhone.value || undefined,
			userEmail: props.signer.email || user.value?.email,
			userId: user.value?.uid,
			productCode: props.productCode,
		})

		if (!payRes) return

		if (payRes.status === 'SUCCESS') {
			payment.state.value = 'success'
			return
		}

		if (payRes.status === 'FAILED') {
			payment.state.value = 'error'
			return
		}

		payment.activeReference.value = payRes.reference
		// Assign provider to payment execution state and lock provider (BE routing truth)
		payment.lockPaymentProvider(payRes.provider)
		mnoDetection.reference = payRes.reference
		instructions.value = payRes.instructions ?? null
		selectedMethod.value = payRes.method
		payment.alreadyCharged.value = !!payRes.alreadyCharged

		if (payRes.flow === 'redirect') return

		if (payRes.flow === 'callback') {
			// Add retry count for daraja polling
			payment.retryCount.value += 1
			instructions.value = payRes.instructions ?? 'Check your phone and enter your M-Pesa PIN'
			return
		}

		if (payRes.flow === 'mobile_direct') {

			const selected = payRes.selected;
			const isSelectedValid = !!selected && isValidSelectedProvider(selected)
			mnoDetection.confidence = payRes.confidence

			mnoDetection.mno = isSelectedValid ? selected.mno : payRes.mno
			mnoDetection.country = isSelectedValid ? selected.country : payRes.country

			// Polling handled in handleBackendDirectedFlow in usePayment
			if (payRes.alreadyCharged) {
				// Add retry count for mobile_direct polling
				payment.retryCount.value += 1
				mnoDetection.state =
					isSelectedValid
						? 'selected'
						: 'detected'

				return
			}

			if (!isSelectedValid && payRes.requiresProviderSelection) {
				mnoDetection.state = 'requires-selection'
				mnoDetection.showSelector = true
				mnoDetection.options = payRes.options ?? []
				return
			}

			if (isSelectedValid) {
				mnoDetection.state = 'selected'
				mnoDetection.showSelector = false
			} else {

				switch (payRes.confidence) {

					case 'high':
					    mnoDetection.showSelector = false
						mnoDetection.state = 'detected'
						break

					case 'ambiguous':
					    mnoDetection.showSelector = true
						mnoDetection.state = 'suggested'
						break

					case 'unknown':
					default:
					    mnoDetection.showSelector = true
						mnoDetection.state = 'requires-selection'
						break
				}
			}

			mnoDetection.options = payRes.options ?? []
		}

	} catch (err) {
		// handled in usePayment snackbars etc
	}
}

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function selectMethod(m: PaymentMethod) {
	if (isProcessing.value) return
	selectedMethod.value = m
}

function isValidSelectedProvider(
	selected?: SelectedMno | null
): boolean {
	return !!(
		selected?.mno &&
		selected?.country
	)
}

function confirmSuggestedMno() {
	mnoDetection.selected = {
		provider: mnoDetection.mno!,
		country: mnoDetection.country!,
	}
	mnoDetection.state = 'selected'
}

function formatAmount(amount: number, currency: string) {
	return `${currency} ${(amount / 100).toFixed(2)}`
}

function formatMnoLabel(mno: string): string {
	const labels: Record<string, string> = {
		mpesa: 'M-Pesa',
		airtel: 'Airtel Money',
		mtn: 'MTN Mobile Money',
		vodacom: 'Vodacom M-Pesa',
		tigo: 'Tigo Pesa',
		halotel: 'Halotel',
		zantel: 'Zantel',
		ttcl: 'TTCL',
		ecocash: 'EcoCash',
		onemoney: 'OneWallet',
		telecash: 'Telecash',
		zamtel: 'Zamtel',
		tnm: 'TNM Mpamba',
		africell: 'Africell',
		faiba: 'Faiba',
	}
	return labels[mno.toLowerCase()] ?? mno
}

function resetMnoDetectionState() {
	mnoDetection.state = 'idle'
	mnoDetection.confidence = null
	mnoDetection.mno = null
	mnoDetection.country = null
	mnoDetection.reference = null
	mnoDetection.options = []
	mnoDetection.selected = null
	mnoDetection.showSelector = false
}

/**
 * Restore active payment runtime from hydrated state.
 *
 * Restores:
 * - provider context
 * - MNO selection
 * - FX context
 * - polling/runtime state
 */
function hydratePaymentState(
	hydratedPaymentPayload: HydratedPayment,
) {
	console.log(
		'[PaymentStep] hydrating payment session',
		hydratedPaymentPayload,
	)

	const hydratedPayment = hydratedPaymentPayload

	isHydrating.value = true

	// notifyInfo({ message: `Restoring Payment Session...` })

	if (hydratedPayment.method) {
		selectMethod(hydratedPayment.method);
	}

	/**
	 * Restore payment execution state
	 */
	payment.state.value = 'hydrating'

	/**
	 * Restore phone input
	 */
	if (hydratedPayment.phoneNumber) {

		phoneInput.value =
			hydratedPayment.phoneNumber

		const resolved = resolvePhone(
			hydratedPayment.phoneNumber,
		)

		resolution.value = resolved

		if (hydratedPayment.phoneNumberRegion) {

			detectedRegion.value =
				hydratedPayment.phoneNumberRegion

			mnoDetection.country =
				hydratedPayment.phoneNumberCountry ?? null

		} else {

			const resolved = resolvePhone(
				hydratedPayment.phoneNumber,
			)

			resolution.value = resolved

			if (resolved.region) {
				detectedRegion.value =
					resolved.region
			}
		}
	}
	/**
	 * Restore FX/payment display context
	 */

	mobilePaymentContext.value = {
		displayAmount:
			hydratedPayment.displayAmount,

		displayAmountFormatted:
			hydratedPayment.displayAmountFormatted,

		displayCurrency:
			hydratedPayment.displayCurrency,
	}

	/**
	 * Restore instructions
	 */

	if (hydratedPayment.instructions) {
		instructions.value =
			hydratedPayment.instructions ?? null
	}

	/**
	 * Restore payment references state
	 */
	payment.activeReference.value = hydratedPayment.reference
    payment.lockPaymentProvider(hydratedPayment.provider)
	payment.alreadyCharged.value = !!hydratedPayment.alreadyCharged

	/**
	 * Restore MNO detection state
	 */
	mnoDetection.reference =
		hydratedPayment.reference

	mnoDetection.confidence =
		hydratedPayment.confidence

	mnoDetection.mno =
		hydratedPayment.mno

	mnoDetection.country =
		hydratedPayment.country

	mnoDetection.options =
		hydratedPayment.options ?? []

	const selected = hydratedPayment.selected
	const isSelectedValid = selected && isValidSelectedProvider(selected)

	mnoDetection.selected =
		isSelectedValid
			? {
				provider: selected.mno,
				country: selected.country,
			}
			: null

	/**
	 * Restore provider selection state
	 */

	if (isSelectedValid) {

		mnoDetection.state = 'selected'

	} else {

		switch (hydratedPayment.confidence) {

			case 'high':
				mnoDetection.state = 'detected'
				break

			case 'ambiguous':
				mnoDetection.state = 'suggested'
				break

			case 'unknown':
			default:
				mnoDetection.state =
					'requires-selection'
				break
		}
	}

	mnoDetection.showSelector =
		mnoDetection.state === 'suggested'
		|| mnoDetection.state === 'requires-selection'

	const {
		method,
		redirectUrl
	} = hydratedPayment

	// We redirect or we can ignore
	if (method === 'card' && redirectUrl) {
		discardRecovery()
		window.location.replace(redirectUrl)
		return
	}

	const shouldResumePolling = routing.shouldStartPolling(hydratedPayment)

	payment.state.value =
		shouldResumePolling
			? 'processing'
			: 'idle'

	if (shouldResumePolling) {
		payment.startPolling(
			hydratedPayment.reference,
			hydratedPayment.flow,
		)
	}

	requestAnimationFrame(() => {
		isHydrating.value = false
	})
}

async function handleCancelPaymentSession() {

	await payment.cancelActivePaymentSession()

	resetPaymentFlow()
}

function resetPaymentFlow() {

	selectMethod('mobile')
	phoneInput.value = ''
	detectedRegion.value = 'KE'
	instructions.value = null
	mobilePaymentContext.value = null
	processingStage.value = 0
	hasShownOfflineToast = false

	resetMnoDetectionState()
}

// ─────────────────────────────────────────────────────────────
// Init
// ─────────────────────────────────────────────────────────────

onMounted(async () => {
	if (payment.useMockPayments) {
		showInfo('This is a demo environment. No real money is being transacted.')
	}
	try {
		const [u, p] = await Promise.all([
			getUser(),
			getProductByCode(props.productCode),
		])
		user.value = u
		product.value = p

		if (props.initialPayment) {
			hydratePaymentState(props.initialPayment)
		}
	} catch (e) {
		showError('Failed to load payment data')
	} finally {
		isLoadingData.value = false
	}
})

onUnmounted(() => {
	payment.stopPolling()
})
</script>

<style lang="scss" scoped>
/* ═══════════════════════════════════════════════════════════ */
/* TOKENS                                                      */
/* ═══════════════════════════════════════════════════════════ */

.payment-step {
	--green: #04d56d;
	--green-dim: rgba(4, 213, 109, 0.14);
	--green-border: rgba(4, 213, 109, 0.32);
	--green-text: #0a6636;
	--green-dark: #073d1f;

	--amber: #f59e0b;
	--amber-bg: #fffbeb;
	--amber-border: #fde68a;
	--amber-text: #92400e;

	--red-bg: #fef2f2;
	--red-border: #fecaca;
	--red-text: #991b1b;

	--blue-bg: #eff6ff;
	--blue-border: #bfdbfe;
	--blue-text: #1e40af;

	--radius-sm: 6px;
	--radius-md: 10px;
	--radius-lg: 14px;

	--transition-snappy: 180ms cubic-bezier(0.4, 0, 0.2, 1);
	--transition-smooth: 260ms cubic-bezier(0.4, 0, 0.2, 1);
	--transition-bounce: 320ms cubic-bezier(0.34, 1.56, 0.64, 1);

	padding: 30px 0;
	max-width: 420px;
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	gap: 12px;


	[data-sonner-toaster][dir="ltr"] {
		--toast-icon-margin-end: 12px;
	}
}

[data-sonner-toaster][dir="ltr"] {
	--toast-icon-margin-end: 12px;
}

[data-sonner-toast][data-styled='true'] {
	gap: 12px !important;
}

/* ═══════════════════════════════════════════════════════════ */
/* NOTECARD                                                    */
/* ═══════════════════════════════════════════════════════════ */

.notecard {
	display: flex;
	align-items: center;
	justify-content: space-between;
	background: #f8fafc;
	border: 0.5px solid #e2e8f0;
	border-radius: var(--radius-md);
	padding: 12px 14px;
	gap: 12px;
}

.notecard--skeleton {
	min-height: 58px;
}

.notecard-left {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}

.notecard-doc {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 12.5px;
	font-weight: 500;
	color: #374151;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;

	svg {
		flex-shrink: 0;
		color: #9ca3af;
	}
}

.notecard-doc__name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.notecard-fx {
	margin-top: 5px;

	font-size: 11px;
	font-weight: 500;

	color: var(--green-text);
	opacity: 0.7;
}

.notecard-secondary {
	margin-top: 2px;

	font-size: 11px;
	font-weight: 500;

	color: #94a3b8;
}

.notecard-reason {
	font-size: 11px;
	color: #9ca3af;
	letter-spacing: 0.01em;
}

.notecard-right {
	text-align: right;
	flex-shrink: 0;
}

.notecard-amount {
	font-size: 17px;
	font-weight: 600;
	color: #111827;
	letter-spacing: -0.02em;
	line-height: 1.2;
}

.notecard-currency {
	font-size: 10px;
	color: #9ca3af;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	margin-top: 1px;
}

/* ═══════════════════════════════════════════════════════════ */
/* METHOD TOGGLE                                               */
/* ═══════════════════════════════════════════════════════════ */

.method-toggle {
	display: flex;
	background: #f1f5f9;
	border-radius: var(--radius-md);
	padding: 3px;
	gap: 2px;

	&--locked {
		pointer-events: none;
		opacity: 0.6;
	}
}

.method-tab {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 7px 12px;
	border-radius: 8px;
	font-size: 12.5px;
	font-weight: 500;
	color: #64748b;
	background: transparent;
	border: none;
	cursor: pointer;
	transition: all var(--transition-snappy);
	white-space: nowrap;

	svg {
		transition: color var(--transition-snappy);
	}

	&:hover:not(.active) {
		color: #374151;
		background: rgba(0, 0, 0, 0.04);
	}

	&.active {
		background: #fff;
		color: #111827;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 0 0 0.5px rgba(0, 0, 0, 0.06);

		svg {
			color: var(--green);
		}
	}

	&:disabled {
		cursor: not-allowed;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* METHOD BODY                                                 */
/* ═══════════════════════════════════════════════════════════ */

.method-body {
	border: 0.5px solid #e5e7eb;
	border-radius: var(--radius-lg);
	padding: 14px;
	min-height: 80px;
	display: flex;
	flex-direction: column;
	gap: 10px;
	overflow: hidden;
	transition: border-color var(--transition-smooth);
}

.tab-content {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

/* ═══════════════════════════════════════════════════════════ */
/* PHONE FIELD                                                 */
/* ═══════════════════════════════════════════════════════════ */

.field-label {
	font-size: 10px;
	font-weight: 600;
	letter-spacing: 0.07em;
	text-transform: uppercase;
	color: #9ca3af;
	margin-bottom: 5px;
	display: block;
}

.phone-row {
	display: flex;
	align-items: center;
	border: 1px solid #e5e7eb;
	border-radius: var(--radius-sm);
	overflow: hidden;
	background: #fff;
	transition: border-color var(--transition-snappy), box-shadow var(--transition-snappy);

	&--focused {
		border-color: #6366f1;
		box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
	}

	&--valid {
		border-color: var(--green);
		box-shadow: 0 0 0 3px var(--green-dim);
	}

	&--locked {
		opacity: 0.6;
		pointer-events: none;
	}
}

.phone-prefix {
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 0 10px;
	height: 40px;
	background: #f8fafc;
	border-right: 1px solid #e5e7eb;
	flex-shrink: 0;
}

.phone-flag {
	font-size: 17px;
	line-height: 1;
}

.phone-dialcode {
	font-size: 12.5px;
	font-weight: 500;
	color: #4b5563;
	white-space: nowrap;
}

.phone-input {
	flex: 1;
	border: none;
	outline: none;
	padding: 0 10px;
	height: 40px;
	font-size: 14px;
	background: transparent;
	color: #111827;
	letter-spacing: 0.01em;

	&::placeholder {
		color: #cbd5e1;
	}

	&:disabled {
		opacity: 0.5;
	}
}

.phone-valid-icon {
	padding-right: 10px;
	color: var(--green);
	display: flex;
	align-items: center;
	flex-shrink: 0;
}

.phone-detecting-icon {
	padding-right: 10px;
	display: flex;
	align-items: center;
	flex-shrink: 0;
}

.phone-helper {
	display: flex;
	align-items: center;
	gap: 4px;
	font-size: 11.5px;
	color: #94a3b8;
	margin-top: 1px;
	line-height: 1.4;

	svg {
		flex-shrink: 0;
		opacity: 0.7;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* MNO DETECTION STATES                                        */
/* ═══════════════════════════════════════════════════════════ */

.mno-detecting {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	color: #64748b;
	padding: 2px 0;
}

.mno-detecting__text {
	letter-spacing: 0.01em;
}

.mno-detected {
	display: flex;
	align-items: center;
	justify-content: space-between;
	background: var(--green-dim);
	border: 0.5px solid var(--green-border);
	border-radius: var(--radius-sm);
	padding: 9px 12px;
	animation: pop-in 0.28s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes pop-in {
	from {
		opacity: 0;
		transform: scale(0.97) translateY(4px);
	}

	to {
		opacity: 1;
		transform: scale(1) translateY(0);
	}
}

.mno-detected__left {
	display: flex;
	align-items: center;
	gap: 7px;
	font-size: 12.5px;
	font-weight: 500;
	color: var(--green-text);
}

.mno-detected__check {
	display: flex;
	align-items: center;
	color: var(--green);
}

.mno-change-btn {
	font-size: 11px;
	color: var(--green-text);
	background: none;
	border: none;
	padding: 0;
	text-decoration: underline;
	text-underline-offset: 2px;
	cursor: pointer;
	opacity: 0.65;
	transition: opacity var(--transition-snappy);

	&:hover {
		opacity: 1;
	}

	&:disabled {
		cursor: not-allowed;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* MNO CHIP SELECTOR                                          */
/* ═══════════════════════════════════════════════════════════ */

.mno-chips-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding-top: 2px;
}

.mno-chips-label {
	font-size: 11.5px;
	color: #64748b;
	letter-spacing: 0.01em;

	& .detected {
		font-weight: 600;
		text-transform: capitalize;
	}
}

.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.chip {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 5px 13px;
	border-radius: 20px;
	border: 1px solid #e5e7eb;
	background: #fff;
	font-size: 12.5px;
	font-weight: 450;
	color: #374151;
	cursor: pointer;
	transition: all var(--transition-snappy);
	letter-spacing: 0.01em;

	&:hover {
		border-color: var(--green-border);
		background: var(--green-dim);
		color: var(--green-text);
	}

	&--preselected {
		border-color: var(--green-border);
		background: var(--green-dim);
		color: var(--green-text);
		opacity: 0.55;
	}

	&--selected {
		border-color: var(--green);
		background: var(--green-dim);
		color: var(--green-text);
		font-weight: 500;
		opacity: 1;
		box-shadow: 0 0 0 1px var(--green-border);
		animation: chip-select 0.22s cubic-bezier(0.34, 1.56, 0.64, 1) both;
	}

	&:disabled {
		cursor: not-allowed;
	}
}

.chip__logo {
	width: 16px;
	height: 16px;
	object-fit: contain;
	flex-shrink: 0;
	border-radius: 999px;
	padding: 2px;
	background: var(--color-background-darker);
	border: 1px solid var(--color-border-maxcontrast);
}

@keyframes chip-select {
	from {
		transform: scale(0.95);
	}

	to {
		transform: scale(1);
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* CARD TAB CONTENT                                           */
/* ═══════════════════════════════════════════════════════════ */

.tab-content--card {
	padding: 2px 0;
	gap: 8px;
}

.card-logos {
	display: flex;
	gap: 6px;
}

.card-logo {
	font-size: 10px;
	font-weight: 700;
	padding: 3px 8px;
	border: 0.5px solid #e5e7eb;
	border-radius: 4px;
	color: #94a3b8;
	letter-spacing: 0.06em;
}

.card-hint {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	font-size: 12px;
	color: #64748b;
	line-height: 1.5;

	svg {
		flex-shrink: 0;
		margin-top: 1px;
		color: #94a3b8;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* STATUS BOXES                                               */
/* ═══════════════════════════════════════════════════════════ */

.status-box {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 13px;
	border-radius: var(--radius-md);
	font-size: 12.5px;
	min-height: 46px;
}

.status-text {
	display: flex;
	flex-direction: column;
	gap: 1px;
	flex: 1;

	&__main {
		font-weight: 500;
		color: inherit;
		line-height: 1.3;
	}

	&__sub {
		font-size: 11px;
		opacity: 0.65;
	}
}

.status-box--processing {
	background: #f8fafc;
	color: #374151;
	flex-wrap: wrap;

	.progress-dots {
		margin-left: auto;
		display: flex;
		gap: 4px;
		align-items: center;
	}
}

.status-box--warning {
	background: var(--amber-bg);
	border: 0.5px solid var(--amber-border);
	color: var(--amber-text);
}

.status-box--error {
	background: var(--red-bg);
	border: 0.5px solid var(--red-border);
	color: var(--red-text);
}

.status-box--success {
	background: var(--green-dim);
	border: 0.5px solid var(--green-border);
	color: var(--green-text);
	font-weight: 500;
	animation: success-arrive 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes success-arrive {
	from {
		opacity: 0;
		transform: scale(0.95);
	}

	to {
		opacity: 1;
		transform: scale(1);
	}
}

.status-box--offline {
	background: var(--blue-bg);
	border: 0.5px solid var(--blue-border);
	color: var(--blue-text);
}

.payment-reset-card {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 14px;
	padding: 13px 14px;
	border-radius: var(--radius-md);
	background: #fafbfd;
	border: 0.5px solid #e2e8f0;
	margin-top: 5px;
	animation: recovery-fade-in 220ms ease;
}

.payment-reset-card__content {
	display: flex;
	flex-direction: column;
	gap: 3px;
}

.payment-reset-card__title {
	font-size: 12.5px;
	font-weight: 600;
	color: #374151;
}

.payment-reset-card__description {
	font-size: 11.5px;
	line-height: 1.45;
	color: #64748b;
	max-width: 250px;
}

.payment-reset-card__button {
	flex-shrink: 0;
	border: none;
	background: #fff;
	padding: 8px 12px;
	border-radius: 8px;
	font-size: 12px;
	font-weight: 600;
	color: #111827;
	cursor: pointer;
	border: 1px solid #e5e7eb;
	transition: all var(--transition-snappy);

	&:hover {
		background: #f9fafb;
		border-color: #d1d5db;
	}
}

.payment-reset-card__button {
	flex-shrink: 0;

	display: inline-flex;
	align-items: center;
	justify-content: center;

	padding: 8px 12px;

	border-radius: 8px;

	background: #fff;
	border: 1px solid #e5e7eb;

	font-size: 12px;
	font-weight: 600;
	color: #111827;

	cursor: pointer;

	transition:
		background var(--transition-snappy),
		border-color var(--transition-snappy),
		transform var(--transition-snappy),
		box-shadow var(--transition-snappy);

	&:hover {
		background: #f9fafb;
		border-color: #d1d5db;

		transform: translateY(-1px);
	}

	&:active {
		transform: scale(0.98);
	}

	&:focus-visible {
		outline: none;

		box-shadow:
			0 0 0 3px rgba(4, 213, 109, 0.14);
	}
}

.payment-recovery-inline {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px 14px;
	border-radius: var(--radius-md);

	background: #f8fafc;
	border: 0.5px solid #e2e8f0;

	animation: recovery-fade-in 220ms ease;
}

.payment-recovery-inline__hint {
	font-size: 11.5px;
	line-height: 1.5;
	color: #64748b;
}

.payment-recovery-inline__button {
	align-self: flex-start;

	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;

	padding: 8px 12px;

	border-radius: 8px;
	border: 1px solid #e5e7eb;

	background: #fff;

	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.01em;

	color: #374151;

	cursor: pointer;

	transition:
		background var(--transition-snappy),
		border-color var(--transition-snappy),
		transform var(--transition-snappy),
		box-shadow var(--transition-snappy);

	&:hover {
		background: #f9fafb;
		border-color: #d1d5db;

		transform: translateY(-1px);
	}

	&:active {
		transform: scale(0.98);
	}

	&:focus-visible {
		outline: none;

		box-shadow:
			0 0 0 3px rgba(4, 213, 109, 0.14);
	}
}

@keyframes recovery-fade-in {
	from {
		opacity: 0;
		transform: translateY(4px);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Progress dots */
.progress-dots {
	display: flex;
	gap: 4px;
	align-items: center;
}

.dot {
	width: 5px;
	height: 5px;
	border-radius: 50%;
	background: var(--green);
	opacity: 0.2;
	transition: opacity 0.4s ease;

	&--on {
		opacity: 1;
		animation: dot-pulse 1.6s ease-in-out infinite;
	}
}

@keyframes dot-pulse {

	0%,
	100% {
		opacity: 0.9;
	}

	50% {
		opacity: 0.4;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* CTA BUTTON                                                 */
/* ═══════════════════════════════════════════════════════════ */

.cta {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 12px 20px;
	border-radius: var(--radius-md);
	font-size: 13.5px;
	font-weight: 600;
	letter-spacing: 0.01em;
	border: none;
	cursor: pointer;
	transition: all var(--transition-snappy);
	position: relative;
	overflow: hidden;
	outline: none;

	&__label {
		transition: opacity var(--transition-snappy);
	}

	&__spinner {
		display: flex;
		align-items: center;
	}

	&__icon {
		display: flex;
		align-items: center;
		transition: transform var(--transition-snappy);
	}

	&--disabled {
		background: #f1f5f9;
		color: #94a3b8;
		cursor: not-allowed;
	}

	&--active {
		background: var(--green);
		color: var(--green-dark);

		&:hover {
			background: #05e87a;
			transform: translateY(-1px);
			box-shadow: 0 4px 14px rgba(4, 213, 109, 0.35);
		}

		&:active {
			transform: scale(0.98) translateY(0);
			box-shadow: none;
		}

		/* Shimmer on initial enable */
		&::after {
			content: '';
			position: absolute;
			inset: 0;
			background: linear-gradient(105deg,
					transparent 40%,
					rgba(255, 255, 255, 0.28) 50%,
					transparent 60%);
			transform: translateX(-100%);
			animation: shimmer-once 0.7s 0.1s ease forwards;
		}
	}

	&--loading {
		background: var(--green);
		color: var(--green-dark);
		cursor: wait;
		opacity: 0.85;
	}

	&--card.cta--active:hover &__icon {
		transform: translateX(3px);
	}
}

@keyframes shimmer-once {
	to {
		transform: translateX(200%);
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* SPINNERS                                                    */
/* ═══════════════════════════════════════════════════════════ */

.spinner {
	display: inline-block;
	border-radius: 50%;
	border-style: solid;
	border-color: transparent;
	animation: spin 0.65s linear infinite;
	flex-shrink: 0;

	&--xs {
		width: 11px;
		height: 11px;
		border-width: 1.5px;
		border-top-color: #94a3b8;
	}

	&--sm {
		width: 13px;
		height: 13px;
		border-width: 2px;
		border-top-color: #64748b;
	}

	&--light {
		border-top-color: var(--green-dark) !important;
	}
}

@keyframes spin {
	to {
		transform: rotate(360deg);
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* SKELETON                                                    */
/* ═══════════════════════════════════════════════════════════ */

.skeleton-block {
	background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
	background-size: 200% 100%;
	animation: shimmer-skeleton 1.4s infinite;
}

@keyframes shimmer-skeleton {
	0% {
		background-position: 200% 0;
	}

	100% {
		background-position: -200% 0;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* FOOTER                                                     */
/* ═══════════════════════════════════════════════════════════ */

.footer {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	color: #cbd5e1;
	letter-spacing: 0.04em;
	padding-top: 2px;

	svg {
		flex-shrink: 0;
	}
}

.footer__links {
	margin-left: auto;
	display: flex;
	gap: 12px;
}

.footer__link {
	color: #cbd5e1;
	text-decoration: none;
	transition: color var(--transition-snappy);

	&:hover {
		color: #94a3b8;
	}
}

/* ═══════════════════════════════════════════════════════════ */
/* TRANSITIONS                                                 */
/* ═══════════════════════════════════════════════════════════ */

/* Notecard entry */
.slide-down-enter-active {
	transition: all 0.32s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-down-enter-from {
	opacity: 0;
	transform: translateY(-6px);
}

/* Tab switch */
.tab-switch-enter-active,
.tab-switch-leave-active {
	transition: opacity 0.16s ease, transform 0.16s ease;
}

.tab-switch-enter-from {
	opacity: 0;
	transform: translateY(5px);
}

.tab-switch-leave-to {
	opacity: 0;
	transform: translateY(-5px);
}

/* Fade soft — for helper text */
.fade-soft-enter-active,
.fade-soft-leave-active {
	transition: opacity 0.2s ease;
}

.fade-soft-enter-from,
.fade-soft-leave-to {
	opacity: 0;
}

/* Fade icon — for valid tick / spinner in input */
.fade-icon-enter-active,
.fade-icon-leave-active {
	transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-icon-enter-from {
	opacity: 0;
	transform: scale(0.7);
}

.fade-icon-leave-to {
	opacity: 0;
	transform: scale(0.7);
}

/* Slide up — for MNO detected / chips */
.slide-up-enter-active {
	transition: opacity 0.24s ease, transform 0.24s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-enter-from {
	opacity: 0;
	transform: translateY(8px);
}

.slide-up-leave-active {
	transition: opacity 0.15s ease;
}

.slide-up-leave-to {
	opacity: 0;
}

/* Expand smooth — for chip selector slide-in */
.expand-smooth-enter-active {
	transition: all 0.26s cubic-bezier(0.4, 0, 0.2, 1);
	overflow: hidden;
}

.expand-smooth-enter-from {
	opacity: 0;
	max-height: 0;
	transform: translateY(-4px);
}

.expand-smooth-enter-to {
	opacity: 1;
	max-height: 200px;
}

.expand-smooth-leave-active {
	transition: all 0.18s ease;
	overflow: hidden;
}

.expand-smooth-leave-to {
	opacity: 0;
	max-height: 0;
}

/* Status swap */
.status-swap-enter-active,
.status-swap-leave-active {
	transition: opacity 0.18s ease, transform 0.18s ease;
}

.status-swap-enter-from {
	opacity: 0;
	transform: translateY(4px);
}

.status-swap-leave-to {
	opacity: 0;
	transform: translateY(-4px);
}

/* Success pop */
.success-pop-enter-active {
	transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.success-pop-enter-from {
	opacity: 0;
	transform: scale(0.92);
}
</style>
