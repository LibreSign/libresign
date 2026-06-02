<template>
  <div class="payment-step">

    <!-- HEADER -->
    <div class="header">
      <h2>Choose payment method</h2>
      <p>Select your preferred way to settle the balance.</p>
    </div>

    <!-- LOADING -->
    <div v-if="isLoadingData" class="loading">
      <NcLoadingIcon :size="24" />
    </div>

    <!-- METHODS -->
    <div v-else class="methods">

      <!-- ================================================== -->
      <!-- MOBILE MONEY CARD                                  -->
      <!-- ================================================== -->
      <div
        class="method-card"
        :class="{ active: selectedMethod === 'mobile', disabled: isProcessing }"
        @click="selectMethod('mobile')"
      >
        <div class="method-header">
          <div class="left">
            <NcIconSvgWrapper :path="mdiCellphone" :size="20" />
            <span>Mobile Money</span>
          </div>

          <div class="method-header-right">
            <!-- MNO DETECTED BADGE (shown in header once detected) -->
            <transition name="fade">
              <div
                v-if="selectedMethod === 'mobile' && mnoDetection.state === 'detected' && mnoDetection.mno"
                class="mno-badge"
              >
                <span class="mno-badge__check">✓</span>
                <span class="mno-badge__label">Paying via {{ formatMnoLabel(mnoDetection.mno) }}</span>
                <button
                  class="mno-badge__change"
                  @click.stop="openMnoSelector"
                >
                  Change?
                </button>
              </div>
            </transition>

            <div class="radio" :class="{ active: selectedMethod === 'mobile' }"></div>
          </div>
        </div>

        <!-- EXPANDED BODY -->
        <transition name="expand">
          <div v-if="selectedMethod === 'mobile'" class="method-body">

            <!-- PHONE INPUT ROW -->
            <div class="phone-field">
              <label class="phone-label">PHONE NUMBER</label>

              <div class="phone-input-row" :class="{ focused: phoneFocused, valid: mnoDetection.state === 'detected' }">

                <!-- FLAG + DIAL CODE -->
                <div class="dial-prefix">
                  <span v-if="detectedFlag" class="dial-flag">{{ detectedFlag }}</span>
                  <span class="dial-code">{{ detectedDialCode }}</span>
                </div>

                <input
                  ref="phoneInputRef"
                  v-model="phoneInput"
                  type="tel"
				  inputmode="tel"
                  class="phone-input"
                  placeholder="+254 712 345 678"
                  :disabled="isProcessing"
                  @focus="phoneFocused = true"
                  @blur="onPhoneBlur"
                  @input="onPhoneInput"
                />

                <!-- VALID CHECK ICON -->
                <transition name="fade">
                  <span v-if="mnoDetection.state === 'detected'" class="phone-valid-icon">✓</span>
                </transition>

                <!-- DETECTING SPINNER -->
                <transition name="fade">
                  <span v-if="mnoDetection.state === 'detecting'" class="phone-detecting">
                    <span class="spinner spinner--sm"></span>
                  </span>
                </transition>
              </div>

              <!-- HELPER TEXT (always visible) -->
              <div class="phone-helper">
                <span class="phone-helper__icon">ℹ</span>
                <span>We’ll detect your country and network automatically from your phone number</span>
              </div>
            </div>

            <!-- ============================================ -->
            <!-- MNO DETECTION STATUS AREA                   -->
            <!-- Three states: detecting / ambiguous / none  -->
            <!-- ============================================ -->

            <!-- DETECTING SKELETON -->
            <transition name="fade">
              <div v-if="mnoDetection.state === 'detecting'" class="mno-status mno-status--skeleton">
                <div class="skeleton-line skeleton-line--short"></div>
              </div>
            </transition>


			<!-- HIGH CONFIDENCE IN MNO BUT GIVING THE USER OPTION TO CHANGE (FULL AUTONOMY) -->
			<transition name="fade">
				<div v-if="mnoDetection.state === 'detected'" class="mno-badge">
					{{ formatMnoLabel(mnoDetection.mno!) }}
					<button @click="openMnoSelector">Change</button>
				</div>
			</transition>


			<!-- AMBIGUOUS (MNO MIGHT CONTAIN OVERLAPS) REQUEST USER FOR CONFIRMATION -->
			<transition name="fade">
				<div v-if="mnoDetection.state === 'suggested'" class="mno-suggestion">
					<span>We think it's {{ formatMnoLabel(mnoDetection.mno!) }}</span>

					<NcButton @click="confirmSuggestedMno">
						Use this?
					</NcButton>

					<NcButton @click="openMnoSelector">
						Change?
					</NcButton>
				</div>
			</transition>

            <!-- AMBIGUOUS / UNKNOWN → PROVIDER SELECTOR -->
            <transition name="expand-smooth">
              <div
                v-if="!isDaraja && (mnoDetection.state === 'requires-selection' || mnoDetection.showSelector)"
                class="mno-selector"
              >
                <div class="mno-selector__label">Select your provider</div>
                <div class="mno-selector__chips">
                  <button
                    v-for="option in mnoDetection.options"
                    :key="option.provider"
                    class="mno-chip"
                    :class="{ selected: mnoDetection.selected?.provider === option.provider }"
                    @click.stop="selectMnoOption(option)"
                  >
                    {{ formatMnoLabel(option.provider) }}
                  </button>
                </div>
              </div>
            </transition>

          </div>
        </transition>
      </div>

      <!-- ================================================== -->
      <!-- CARD PAYMENT                                       -->
      <!-- ================================================== -->
      <div
        class="method-card"
        :class="{ active: selectedMethod === 'card', disabled: isProcessing }"
        @click="selectMethod('card')"
      >
        <div class="method-header">
          <div class="left">
            <NcIconSvgWrapper :path="mdiCreditCardOutline" :size="20" />
            <span>Credit/Debit Card</span>
          </div>

          <div class="radio" :class="{ active: selectedMethod === 'card' }"></div>
        </div>

        <transition name="expand">
          <div v-if="selectedMethod === 'card'" class="method-body">
            <div class="card-logos">
              <span class="card-logo">VISA</span>
              <span class="card-logo">MC</span>
            </div>
            <div class="hint">
              <span class="hint__icon">🔒</span>
              Secure redirect. No card details stored here.
            </div>
          </div>
        </transition>
      </div>

    </div>

    <!-- ================================================== -->
    <!-- PAYMENT STATUS MESSAGES                            -->
    <!-- ================================================== -->
    <div class="status">

			<transition :name="transitionName" mode="out-in">
				<div :key="statusKey">

					<!-- OFFLINE -->
					<div v-if="payment.isOffline.value" class="status-box status-box--offline">
						<span class="spinner spinner--sm"></span>
						No connection. Waiting to reconnect…
					</div>

					<!-- PROCESSING -->
					<div v-else-if="payment.state.value === 'processing'" class="status-box status-box--processing">
						<div class="spinner"></div>

						<div class="status-content">
							<span class="status-message">
								{{ paymentMessage }}
							</span>

							<span class="subtle-note">
								This usually takes a few seconds. Don’t close this window.
							</span>
						</div>
					</div>

					<!-- TIMEOUT -->
					<div v-else-if="payment.state.value === 'timeout'" class="status-box status-box--warning">
						Still processing… you can retry
					</div>

					<!-- ERROR -->
					<div v-else-if="payment.state.value === 'error'" class="status-box status-box--error">
						{{ payment.error.value }}
					</div>

					<!-- SUCCESS -->
					<div v-else-if="payment.state.value === 'success'" class="status-box status-box--success">
						✓ Payment successful
					</div>

				</div>
			</transition>

    </div>

    <!-- ================================================== -->
    <!-- CTA                                                -->
    <!-- ================================================== -->
    <div class="actions">
      <NcButton
        class="cta"
        variant="primary"
        :disabled="!canContinue || isProcessing"
        @click="handlePay"
      >
        <template #icon>
          <NcLoadingIcon v-if="payment.state.value === 'initiating'" :size="18" />
        </template>
        {{ buttonLabel }}
      </NcButton>
    </div>

    <div class="footer">
      <span class="footer__lock">🔒</span>
      SECURED
      <span class="footer__links">
        <a href="#">Terms</a>
        <a href="#">Privacy</a>
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
 * - resolve provider (Daraja vs DPO)
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
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { mdiCellphone, mdiCreditCardOutline } from '@mdi/js'

import { usePayment } from '@/payment'
import { getProductByCode } from '@/payment/product'
import { getUser } from '@/payment/user'
import { notifyError, notifyInfo, notifySuccess } from '@/services/toast'

import { resolvePhone, formatAsYouType } from '@/utils/phoneResolver'
import { normaliseRegion } from '@/utils/mobileMoney'

// ─────────────────────────────────────────────────────────────
// Props / Emits
// ─────────────────────────────────────────────────────────────

const props = defineProps<{
  signUuid: string
  signRequestId: number
  document: any
  signer: any
  productCode: string
}>()

const emit = defineEmits(['payment-success', 'state-change'])

// ─────────────────────────────────────────────────────────────
// External state
// ─────────────────────────────────────────────────────────────

const payment = usePayment()

// ─────────────────────────────────────────────────────────────
// UI state
// ─────────────────────────────────────────────────────────────

const selectedMethod = ref<'mobile' | 'card'>('mobile')
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
  options?: Array<{ provider: string; country: string }>
  selected: { provider: string; country: string } | null
  showSelector: boolean      // true when user clicks "Change?" on a high-confidence result
}>( {
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

/**
 * Derived dial code and flag from MNO detection result.
 * Falls back to +254 (KE) as default until detection resolves.
 */
const REGION_META: Record<string, { dialCode: string; flag: string }> = {
  KE: { dialCode: '+254', flag: '🇰🇪' },
  TZ: { dialCode: '+255', flag: '🇹🇿' },
  UG: { dialCode: '+256', flag: '🇺🇬' },
  RW: { dialCode: '+250', flag: '🇷🇼' },
  MW: { dialCode: '+265', flag: '🇲🇼' },
  ZM: { dialCode: '+260', flag: '🇿🇲' },
  ZW: { dialCode: '+263', flag: '🇿🇼' },
}

const detectedRegion = ref<string>('KE')

const detectedDialCode = computed(() =>
  REGION_META[detectedRegion.value]?.dialCode ?? '+254'
)

const detectedFlag = computed(() =>
  REGION_META[detectedRegion.value]?.flag ?? null
)

const isDaraja = computed(() => resolution.value?.provider === 'daraja')

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

const isProcessing = computed(() =>
  payment.state.value === 'processing' ||
  payment.state.value === 'initiating' ||
  payment.isOffline.value
)

const buttonLabel = computed(() => {
  if (payment.isOffline.value) return 'Waiting for connection...'

  switch (payment.state.value) {
    case 'timeout':   return 'Retry Payment'
    case 'error':     return 'Try Again'
    case 'processing': return 'Waiting for confirmation...'
    default:
      return product.value
        ? `Pay ${formatAmount(product.value.amount, product.value.currency)}`
        : 'Continue to payment'
  }
})

let hasShownOfflineToast = false

const paymentMessage = computed(() => {
  if (payment.isOffline.value) {
    return 'Connection lost. We’ll resume automatically…'
  }

  if (instructions.value) return instructions.value

  if (processingStage.value === 1) {
    return 'Still working… this can take a few seconds'
  }

  if (processingStage.value === 2) {
    return 'Almost there… waiting for confirmation'
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
  if (payment.state.value === 'success') {
    return 'success-pop'
  }

  return 'fade'
})

/**
 * Sync payment state → parent modal
 *
 * - success → triggers signing flow
 * - updates UI state externally
 */
watch(() => payment.state.value, (state) => {
  emit('state-change', state)
   if (state === 'success' && !hasEmittedSuccess.value) {
		hasEmittedSuccess.value = true
		emit('payment-success')
   }
})


/**
 * Phone input watcher
 *
 * - formats input for UX (as-you-type)
 * - resolves phone → region + provider
 *
 */
watch(phoneInput, (val) => {
  const formatted = formatAsYouType(val)

  if (formatted !== val) {
    phoneInput.value = formatted
    return
  }

  const res = resolvePhone(val)
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
      notifyInfo({
        message: `Connection lost. We’ll keep trying…`,
      })
      hasShownOfflineToast = true
    }
  } else {
    notifySuccess({
      message: 'Back online. Resuming payment…',
    })
    hasShownOfflineToast = false
  }
})

/**
 * Payment state watcher
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

/**
 * Reset MNO state whenever the user edits the phone field.
 * We don't re-detect on every keystroke — only on blur.
 */
function onPhoneInput() {
  mnoDetection.state = 'idle'
  mnoDetection.confidence = null
  mnoDetection.mno = null
  mnoDetection.reference = null
  mnoDetection.options = []
  mnoDetection.selected = null
  mnoDetection.showSelector = false
}

/**
 * On blur: if there's a plausible number, trigger initiate().
 * This is the single entry point for MNO detection during DPO mobile_direct flow.
 */
async function onPhoneBlur() {
  phoneFocused.value = false

  const r = resolution.value

  if (!r?.isValid) return

  // skip DPO detection entirely for daraja
  if (r.provider === 'daraja') return

  await detectAndInitiate(r.e164!)
}

/**
 * Phase 1: Detect + initiate
 * Meant only for DPO (mobile_direct) flow
 * Triggered on:
 * - input blur
 *
 * Responsibilities:
 * - call initiateOnly()
 * - get reference + flow + confidence
 * - adapt UI (auto / suggest / require selection)
 *
 * Does NOT:
 * - charge user
 */
async function detectAndInitiate(phone: string) {
  if (isDaraja.value) return

  mnoDetection.state = 'detecting'

  try {
    const res = await payment.initiateOnly({
      signRequestId: props.signRequestId,
      signUuid: props.signUuid,
      phoneNumber: phone,
      method: 'mobile',
      email: props.signer.email,
      userId: user.value?.uid,
      productCode: props.productCode,
    })
   /**
	* Backend returns:
	* - confidence (high | ambiguous | unknown)
	*
	* UI behavior:
	* - high → auto-ready
	* - ambiguous → suggest + allow change
	* - unknown → require selection
	*
	*/

    // Store reference — used later in handlePay() without re-initiating
    mnoDetection.reference = res.reference
    mnoDetection.confidence = res.confidence
    mnoDetection.mno = res.mno
    mnoDetection.country = res.country

    // Update the flag/dial code display from the region the BE detected
    const region = normaliseRegion(res.country)

	if (region) {
		detectedRegion.value = region
	}

    if (res.confidence === 'high') {
		// Seamless path — provider shown in badge, no selector
		mnoDetection.state = 'detected'
    }
	// The next blocks represent Ambiguous or unknown
	// We populate options from initiate response
	// (initiate already called getMobilePaymentOptions on the BE so
	//  we don't need a second round-trip here)
	else if (res.confidence === 'ambiguous') {
		// Seamless path — provider shown in badge, no selector
		mnoDetection.state = 'suggested'
		mnoDetection.options = res.options ?? []

	} else {

      mnoDetection.options = res.options ?? []
      mnoDetection.state = 'requires-selection'
    }

  } catch (err: any) {
    // Detection failure is non-fatal — fall back to selector with empty options
    mnoDetection.state = 'requires-selection'
    mnoDetection.options = []

    notifyError({
      message: 'Could not detect your network. Please select your provider.',
      important: false,
    })
  }
}

// ─────────────────────────────────────────────────────────────
// MNO selector actions
// ─────────────────────────────────────────────────────────────

/**
 * Provider selection (fallback UX)
 *
 * Only shown when:
 * - confidence = unknown
 * - or user clicks "Change?"
 *
 * Hidden by default to keep flow seamless
 */
async function openMnoSelector() {
  mnoDetection.showSelector = false

  setTimeout(() => {
    mnoDetection.state = 'requires-selection'
    mnoDetection.showSelector = true
  }, 120)

  const options = mnoDetection.options && mnoDetection.options.length ? mnoDetection.options : []
  if (!options.length && mnoDetection.reference) {
    try {
      const response = await payment.fetchMobileOptions(mnoDetection.reference)
	  console.log(`[Payment] Fetch mobile options: `, response)
      mnoDetection.options = response.options;
    } catch {
      // options fetch failed — selector will be empty, user can't change
      // this is an edge case; the high-confidence path is still usable
    }
  }
}

/**
 * User selected a provider chip.
 */
function selectMnoOption(option: { provider: string; country: string }) {
  mnoDetection.selected = option
  mnoDetection.mno = option.provider
  mnoDetection.country = option.country
  mnoDetection.state = 'selected'
  mnoDetection.showSelector = false
}

/**
 * User confirms payment
 *
 * Behavior depends on flow:
 *
 * - Daraja:
 *   initiateOnly() → polling
 *
 * - DPO mobile:
 *   chargeExistingReference() → polling
 *
 * - Card:
 *   startPayment() → redirect
 *
 */
async function handlePay() {
  console.log(`[Payment] mnoDetection`, mnoDetection)

  // prevent duplicate triggers
  if (payment.state.value === 'processing') return

  // 1. RETRY PATH FIRST (bypass normal validation)
  if (payment.state.value === 'timeout' || payment.state.value === 'error') {
    return retryPayment()
  }

  // 2. NORMAL VALIDATION (ONLY for fresh flows)
  if (!canContinue.value) {
    notifyError({
      message: selectedMethod.value === 'mobile'
        ? mnoDetection.state === 'requires-selection'
          ? 'Please select your mobile provider'
          : 'Enter a valid phone number'
        : 'Something went wrong',
      important: true,
	  rich: true,
    })
    return
  }

  try {
    if (selectedMethod.value === 'mobile') {

      const r = resolution.value

      if (!r?.isValid) {
        notifyError({ message: 'Enter a valid phone number', important: true })
        return
      }

   /**
	* Daraja flow (M-Pesa)
	*
	* - no provider selection needed
	* - no second step
	* - polling starts immediately
	*/
      if (r.provider === 'daraja') {

        const res = await payment.initiateOnly({
			provider: 'daraja',
			paymentMethod: 'mobile',
			phoneNumber: r.e164,
			signRequestId: props.signRequestId,
			signUuid: props.signUuid,
			productCode: props.productCode,
			userId: user.value?.uid,
			email: props.signer.email,
        })

        // sanity check
        if (res.flow !== 'callback') {
          notifyError({ message: 'Unexpected payment flow', important: true, rich: true })
          return
        }

        // optional instructions from BE
        instructions.value = res.instructions ?? 'Check your phone and enter your M-Pesa PIN'

        // start polling
        payment.startPolling(res.reference, res.flow)

        return
      }

	  // DPO mobile_direct flow

		const mnoToUse =
			mnoDetection.selected?.provider ??
			mnoDetection.mno

		const countryToUse =
			mnoDetection.selected?.country ??
			mnoDetection.country

		// Edge case user clicks pay before blur
		if (!mnoDetection.reference) {
			notifyError({
				message: 'Please enter your phone number first',
				important: true,
			})
			return
		}

      // DPO FLOW (UNCHANGED)
      await payment.chargeExistingReference({
        reference: mnoDetection.reference!,
        phoneNumber: normalisedPhone.value,
        mno: mnoToUse ?? undefined,
        mnoCountry: countryToUse ?? undefined,
      })

    } else {
      // CARD FLOW (UNCHANGED)
      await payment.startPayment({
        signRequestId: props.signRequestId,
        signUuid: props.signUuid,
        method: 'card',
        email: props.signer.email,
        userId: user.value?.uid,
        productCode: props.productCode,
      })
    }

  } catch (err) {
    console.error('[PaymentStep] payment failed', err)
    notifyError({ message: 'Payment failed. Please try again.', important: true })
  }
}

async function retryPayment() {
  try {
    const res = await payment.startPayment({
      signRequestId: props.signRequestId,
      signUuid: props.signUuid,
      method: selectedMethod.value === 'card' ? 'card' : 'mobile',
      phoneNumber: normalisedPhone.value || undefined,
      email: props.signer.email,
      userId: user.value?.uid,
      productCode: props.productCode,
    })

	// Network error handled in usePayment
	if (!res) return

    /*
     * 1. SUCCESS/FAILED SHORT-CIRCUIT
     */
    if (res.status === 'SUCCESS' || res.status === 'FAILED') {
      // usePayment already handles state, just exit
      return
    }

	/*
     * 2. SYNC BASE STATE
     */
    mnoDetection.reference = res.reference
    instructions.value = res.instructions ?? null

    selectedMethod.value =
      res.flow === 'redirect' ? 'card' : 'mobile'

	/*
	 * 3. REDIRECT FLOW (CARD)
	 */
    if (res.flow === 'redirect') {
      // usePayment already handles redirect
      return
    }

	/*
	 * 4. ASYNC FLOW (DARAJA)
	 */
    if (res.flow === 'callback') {
      instructions.value =
        res.instructions ?? 'Check your phone and enter your M-Pesa PIN'

		// Optional but explicit (safer UX sync)
		// ensures UI is aligned even if something changes later
		if (payment.state.value !== 'processing') {
			// future-safe
		}

      return
    }

	/*
	 * 5. MOBILE DIRECT (DPO)
	 */
    if (res.flow === 'mobile_direct') {

      mnoDetection.confidence = res.confidence
      mnoDetection.mno = res.mno
      mnoDetection.country = res.country

      // ---- CASE A: NEED SELECTION ----
      if (res.requiresProviderSelection) {
        mnoDetection.state = 'requires-selection'
        mnoDetection.showSelector = true
        mnoDetection.options = res.options ?? []
        return
      }

      // ---- CASE B: ALREADY CHARGED ----
      if (res.instructions) {
        mnoDetection.state = 'detected'
        payment.startPolling(res.reference, res.flow)
        return
      }

      // ---- CASE C: NEED CHARGE ----
      // happens when backend resumed but no charge yet
      mnoDetection.state =
        res.confidence === 'high'
          ? 'detected'
          : res.confidence === 'ambiguous'
          ? 'suggested'
          : 'requires-selection'

      mnoDetection.options = res.options ?? []

      return
    }

  } catch (err) {
    notifyError({
      message: 'Retry failed. Please try again.',
      important: true,
    })
  }
}

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function selectMethod(m: 'mobile' | 'card') {
  if (isProcessing.value) return
  selectedMethod.value = m
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

/**
 * Maps internal MNO keys to display labels.
 * Keys come from MnoRegistry (lowercase).
 */
function formatMnoLabel(mno: string): string {
  const labels: Record<string, string> = {
    mpesa:    'M-Pesa',
    airtel:   'Airtel Money',
    mtn:      'MTN Mobile Money',
    vodacom:  'Vodacom M-Pesa',
    tigo:     'Tigo Pesa',
    halotel:  'Halotel',
    zantel:   'Zantel',
    ttcl:     'TTCL',
    ecocash:  'EcoCash',
    onemoney: 'OneWallet',
    telecash: 'Telecash',
    zamtel:   'Zamtel',
    tnm:      'TNM Mpamba',
    africell: 'Africell',
    faiba:    'Faiba',
  }
  return labels[mno.toLowerCase()] ?? mno
}

// ─────────────────────────────────────────────────────────────
// Init
// ─────────────────────────────────────────────────────────────

onMounted(async () => {
  try {
    const [u, p] = await Promise.all([
      getUser(),
      getProductByCode(props.productCode),
    ])

    user.value = u
    product.value = p
  } catch (e) {
    notifyError({
      message: 'Failed to load payment data',
      important: true,
    })
  } finally {
    isLoadingData.value = false
  }

  // Resume any existing session (e.g. after page refresh mid-payment)
  payment.resumePayment()
})

onUnmounted(() => {
  payment.stopPolling()
})
</script>

<style lang="scss" scoped>
.payment-step {
  padding: 22px 0;
  max-width: 420px;
  margin: 0 auto;
}

/* ─── HEADER ─────────────────────────────────────────────── */

.header h2 {
  font-size: 20px;
  font-weight: 600;
}

.header p {
  font-size: 13px;
  opacity: 0.6;
  margin-top: 2px;
}

/* ─── METHODS ────────────────────────────────────────────── */

.methods {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 14px;
}

.method-card {
  border-radius: 14px;
  padding: 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  transition: all 0.25s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.method-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

.method-card.active {
  border-color: #04d56d;
  box-shadow: 0 0 0 2px rgba(4,213,109,0.15);
}

/* Green left accent bar on active card */
.method-card.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0px;
  bottom: 0px;
  width: 5px;
  border-radius: 4px;
  background: #04d56d;
}

.method-card.disabled {
  opacity: 0.6;
  pointer-events: none;
}

/* ─── METHOD HEADER ──────────────────────────────────────── */

.method-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.method-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.left {
  display: flex;
  gap: 10px;
  align-items: center;
  font-weight: 600;
}

/* ─── RADIO ──────────────────────────────────────────────── */

.radio {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #ccc;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.radio.active {
  border-color: #04d56d;
  box-shadow: inset 0 0 0 4px #04d56d;
}

/* ─── METHOD BODY ────────────────────────────────────────── */

.method-body {
  margin-top: 10px;
}

/* ─── MNO DETECTED BADGE (in card header) ────────────────── */

.mno-badge {
  display: flex;
  align-items: center;
  gap: 5px;
  background: rgba(4, 213, 109, 0.12);
  border: 1px solid rgba(4, 213, 109, 0.3);
  border-radius: 20px;
  padding: 3px 10px 3px 7px;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.mno-badge__check {
  color: #04d56d;
  font-size: 11px;
  font-weight: 700;
}

.mno-badge__label {
  color: #1a6640;
}

.mno-badge__change {
  background: none;
  border: none;
  padding: 0;
  margin-left: 2px;
  font-size: 11px;
  color: #0f7a45;
  text-decoration: underline;
  cursor: pointer;
  font-weight: 500;

  &:hover {
    color: #04d56d;
  }
}

/* ─── PHONE FIELD ─────────────────────────────────────────── */

.phone-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.phone-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.8px;
  opacity: 0.5;
  text-transform: uppercase;
}

.phone-input-row {
  display: flex;
  align-items: center;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;

  &.focused {
    border-color: #6366f1;
	box-shadow: 0 0 0 2px rgba(99,102,241,0.08);
  }

  &.valid {
    border-color: #16a34a;
	box-shadow: 0 0 0 2px rgba(22,163,74,0.08);
  }
}

.dial-prefix {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 10px;
  border-right: 1px solid #e0e0e0;
  height: 38px;
  background: #f8f9fa;
  flex-shrink: 0;
}

.dial-flag {
  font-size: 16px;
  line-height: 1;
}

.dial-code {
  font-size: 13px;
  font-weight: 500;
  color: #444;
  white-space: nowrap;
}

.phone-input {
  flex: 1;
  border: none;
  outline: none;
  padding: 0 10px;
  height: 38px;
  font-size: 14px;
  background: transparent;
  color: inherit;

  &::placeholder {
    color: #bbb;
  }

  &:disabled {
    opacity: 0.5;
  }
}

.phone-valid-icon {
  padding-right: 10px;
  color: #04d56d;
  font-size: 15px;
  font-weight: 700;
  flex-shrink: 0;
}

.phone-detecting {
  padding-right: 10px;
  flex-shrink: 0;
}

/* ─── PHONE HELPER TEXT ──────────────────────────────────── */

.phone-helper {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  opacity: 0.55;
  margin-top: 2px;
}

.phone-helper__icon {
  font-size: 11px;
  opacity: 0.7;
}

/* ─── MNO STATUS / SKELETON ──────────────────────────────── */

.mno-status--skeleton {
  margin-top: 8px;
}

.skeleton-line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;

  &--short { width: 140px; }
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ─── MNO CHIP SELECTOR ──────────────────────────────────── */

.mno-selector {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f0f0f0;
}

.mno-selector__label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.5px;
  opacity: 0.5;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.mno-selector__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.mno-chip {
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid #e0e0e0;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  color: inherit;

  &:hover {
    border-color: #04d56d;
    background: rgba(4, 213, 109, 0.06);
  }

  &.selected {
    border-color: #04d56d;
    background: rgba(4, 213, 109, 0.12);
    color: #0f7a45;
  }
}

/* ─── CARD LOGOS ─────────────────────────────────────────── */

.card-logos {
  display: flex;
  gap: 6px;
  margin-bottom: 8px;
}

.card-logo {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 7px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  opacity: 0.5;
  letter-spacing: 0.5px;
}

/* ─── HINT ───────────────────────────────────────────────── */

.hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  opacity: 0.6;
  margin-top: 4px;
}

.hint__icon {
  font-size: 13px;
}

/* ─── STATUS ─────────────────────────────────────────────── */

.status {
  min-height: 48px;
  margin-top: 10px;
}

.status-box {
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 8px;

  & .status-content {
	display: flex;
	flex-direction: column;
	line-height: 1.3;
	gap: 2px;

	& .status-message {
		font-weight: 500;
	}
  }
}

.status-box--processing {
  background: #f5f5f5;
  color: #333;

  & .subtle-note {
	font-size: 11px;
	opacity: 0.5;
	margin-top: 2px;
  }
}

.status-box--warning {
  background: #fffbeb;
  color: #f59e0b;
}

.status-box--error {
  background: #fef2f2;
  color: #ef4444;
}

.status-box--success {
  background: #f0fdf4;
  color: #04d56d;
  font-weight: 600;

  animation: success-pop 0.35s ease;
}

.status-box--offline {
  background: #eef2ff;
  color: #4f46e5;

  & .spinner {
	animation: spin 0.7s linear infinite, pulse 1.5s ease infinite;
  }
}

/* ─── CTA ────────────────────────────────────────────────── */

.actions {
  margin-top: 16px;
}

.cta {
  width: 100%;
  font-weight: 600;
  letter-spacing: 0.2px;
  transition: transform 0.15s ease;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
  }

  &:active {
    transform: scale(0.97);
  }

  &:disabled {
	cursor: not-allowed;
	opacity: 0.7;
  }

  /* Subtle pulse when ready */
  &:not(:disabled) {
	position: relative;
	overflow: hidden;
    animation: pulse 1s ease-out 1;
  }

  &:not(:disabled)::after {
	content: '';
	position: absolute;
	inset: 0;
	background: linear-gradient(
		120deg,
		transparent,
		rgba(255,255,255,0.4),
		transparent
	);
	transform: translateX(-100%);
	animation: shimmer-cta 2.5s infinite;
  }
}

@keyframes pulse {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.03); }
  100% { transform: scale(1); }
}

/* ─── SPINNERS ───────────────────────────────────────────── */

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #ccc;
  border-top-color: #04d56d;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;

  &--sm {
    width: 12px;
    height: 12px;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ─── FOOTER ─────────────────────────────────────────────── */

.footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-align: center;
  font-size: 11px;
  opacity: 0.5;
  margin-top: 12px;
  letter-spacing: 0.4px;
}

.footer__links {
  margin-left: auto;
  display: flex;
  gap: 10px;

  a {
    color: inherit;
    text-decoration: none;
    opacity: 0.7;

    &:hover { opacity: 1; }
  }
}

/* ─── TRANSITIONS ────────────────────────────────────────── */

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.expand-enter-active {
  transition: all 0.25s ease;
}

.expand-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

/* Smooth height expand for MNO selector */
.expand-smooth-enter-active {
  transition: all 0.25s ease;
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
  transition: all 0.2s ease;
  overflow: hidden;
}

.expand-smooth-leave-to {
  opacity: 0;
  max-height: 0;
}

.success-pop-enter-active {
  transition: all 0.3s ease;
}

.success-pop-enter-from {
  opacity: 0;
  transform: scale(0.9);
}

@keyframes success-pop {
  0% {
    opacity: 0;
    transform: scale(0.92);
  }
  70% {
    transform: scale(1.04);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes shimmer-cta {
  100% {
    transform: translateX(100%);
  }
}

@keyframes offlinePulse {
  0%, 100% { opacity: 0.6 }
  50% { opacity: 1 }
}
</style>
