<template>
  <NcDialog
    name="Complete payment"
    size="normal"
    @closing="handleClose"
  >
    <!-- Recovery loading -->
	<div
		v-if="isChecking"
		class="payment-modal-loading"
	>
		Checking payment session…
	</div>

	<!-- Recovery -->
	<PaymentRecoveryCard
		v-else-if="
			hasRecovery &&
			recoveryPayment &&
			!hasAcceptedRecovery
		"
		:payment="recoveryPayment"
		@resume="handleResume"
		@start-over="handleStartOver"
	/>

	<!-- Payment execution -->
    <PaymentStep
	  v-else
      :document="document"
      :signer="signer"
      :sign-request-id="signRequestId"
      :sign-uuid="signUuid"
      :product-code="productCode"
	  :initial-payment="hydratedPayment"
      @payment-success="onSuccess"
      @state-change="onStateChange"
	  @payment-runtime-invalid="onPaymentRuntimeInvalid"
    />
  </NcDialog>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import PaymentStep from '@/components/Payments/PaymentStep.vue'
import PaymentRecoveryCard from '@/components/Payments/PaymentRecoveryCard.vue'
import { usePaymentRecovery } from '@/payment/usePaymentRecovery'
import { type PaymentState } from '@/payment/usePayment'
import type { HydratedPayment } from '@/payment/types'
import { notifyInfo } from '@/services/toast'

const props = defineProps<{
  signUuid: string
  signRequestId: number
  document: any
  signer: any
  productCode: string
}>()

const emit = defineEmits([
	'close',
	'success'
])

const hydratedPayment = ref<HydratedPayment | null>(
	null
)

const {
	isChecking,
	hasRecovery,
	recoveryPayment,
	checkRecovery,
	resumeRecovery,
	discardRecovery,
} = usePaymentRecovery()

const hasAcceptedRecovery = ref(false)

// controls whether modal can close
const canClose = ref(false)

// track PaymentStep state to prevent closing during critical phases
const currentState = ref<PaymentState>('idle')


onMounted(async () => {
	await checkRecovery({
		signRequestId: props.signRequestId,
		signUuid: props.signUuid,
	})
})

function handleClose() {
  // CRITICAL: block closing during payment execution
   if (
	currentState.value === 'processing' ||
	currentState.value === 'initiating' ||
	currentState.value === 'requesting' ||
	currentState.value === 'hydrating'
   ) {
	notifyInfo({
		message: `You are in the middle of an active payment session`
	})
	return
   }

  emit('close')
}


function handleResume() {
  const payment = resumeRecovery()

  if (!payment) {
    return
  }

  hydratedPayment.value = payment
  hasAcceptedRecovery.value = true
}

function handleStartOver() {
	discardRecovery()

	hydratedPayment.value = null
}


// called when payment completes successfully
function onSuccess() {
  discardRecovery()
  canClose.value = true
  emit('success') // triggers retry signing flow
}

// receive state updates from PaymentStep
function onStateChange(state: typeof currentState.value) {
  currentState.value = state
}

function onPaymentRuntimeInvalid() {
   canClose.value = true
   emit('close')
}
</script>
<style lang="scss" scoped>
.payment-modal-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;

	min-height: 220px;

	font-size: 13px;
	font-weight: 500;
	letter-spacing: 0.01em;

	color: #64748b;

	&::before {
		content: '';

		width: 16px;
		height: 16px;

		border-radius: 50%;
		border: 2px solid rgba(100, 116, 139, 0.2);
		border-top-color: #64748b;

		animation: payment-modal-spin 0.7s linear infinite;
	}
}

@keyframes payment-modal-spin {
	to {
		transform: rotate(360deg);
	}
}
</style>
