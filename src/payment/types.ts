export type PaymentProvider = 'daraja' | 'dpo';
export type PaymentStatus =
  | 'PENDING'
  | 'SUCCESS'
  | 'FAILED'
  | 'INITIATION_FAILED';

export type PaymentReason =
	| 'pending'
	| 'paid'
	| 'failed'
	| 'initiation_failed'

export type PaymentConfidence = 'high' | 'ambiguous' | 'unknown'

export type PaymentMobileFlow = 'mobile_direct' | 'callback'
export type PaymentFlow = 'redirect' | PaymentMobileFlow
export type PaymentMethod = 'mobile' | 'card'

export type ProviderExecutionState =
	'initiated'
	| 'requires_selection'
	| 'executing'
	| 'reconciling'
	| 'success'
	| 'failed'
	| 'expired'
	| 'cancelled'
	| 'invalid_request'

export interface CreatePaymentRequest {
  signRequestId: string;
  signUuid: string;
  phoneNumber: string;
  provider: PaymentProvider;
  redirectUrl: string;
  userEmail: string;
  userId: string;
  productCode: string;
}

export interface MnoOption {
	provider: string;
	country: string;
	countryCode?: string;
	prefix?: string;
	currency?: string;
	instructions?: string;
	logo?: string;
}

export interface SelectedMno {
	mno: string
	country: string
}

export interface PaymentResponse {
  updatedAt?: string;
  paymentId?: number;
  signRequestId: number;
  signUuid: string;
  reference: string;
  provider: PaymentProvider;
  status: PaymentStatus;
  flow: PaymentFlow;
  method: PaymentMethod;
  redirectUrl?: string;
  alreadyCharged?: boolean;
  instructions?: string;
  message?: string;
  confidence?: PaymentConfidence;
  mno?: string;
  country?: string;
  selected?: SelectedMno;
  /**
   * @Deprecated
   */
  region?: string;
  requiresProviderSelection?: boolean;
  options?: MnoOption[];
  failureReason?: string
  phoneNumber?: string
  phoneNumberRegion?: string
  phoneNumberCountry?: string
  displayAmount?: any
  displayAmountFormatted?: string
  displayCurrency?: string
  providerExecutionState?: ProviderExecutionState
}


export interface InitiateResponse extends PaymentResponse {}

export interface ResumeResponse extends PaymentResponse {}

export interface HydratedPayment extends ResumeResponse {}

export interface ChargedPaymentResponse extends PaymentResponse {}

export interface ApiResumeResponse {
	success: boolean
	result: ResumeResponse | null
}

export interface VerifyPaymentResponse {
	status: PaymentStatus;
	reason?: string;
}

export type SupportedRegion = 'KE' | 'TZ' | 'UG' | 'RW' | 'MW' | 'ZM' | 'ZW'


export interface ResolvedPaymentRoute {
	provider: string
	country: string
	logo?: string
	currency?: string
	instructions?: string
	flow: PaymentMobileFlow
	source:
		| 'daraja'
		| 'dpo-detected'
		| 'dpo-selected'
}

export interface MobilePaymentContext {
	displayAmount?: number
	displayAmountFormatted?: string
	displayCurrency?: string
	phoneNumber?: string
}

export interface StartPaymentPayload {
	userEmail: string
	signUuid: string
	signRequestId: number
	userId: string

	/**
	 * Used for redirect/card payment flows.
	 * Backend may ignore this for mobile flows.
	 */
	redirectUrl?: string | null

	/**
	 * Monetisation / entitlement strategy key.
	 */
	productCode: string

	/**
	 * Optional client-generated attempt lineage identifier.
	 * Useful for retries/restarts/idempotency later.
	 */
	paymentAttemptId?: string | null

	/**
	 * Optional provider hint.
	 *
	 * Backend remains authoritative and may override
	 * based on:
	 * - provider availability
	 * - routing capabilities
	 * - region support
	 * - failover strategy
	 */
	provider?: PaymentProvider | null

	/**
	 * Required for mobile payment flows.
	 */
	phoneNumber?: string | null

	/**
	 * Optional async callback/deep-link override.
	 */
	callbackUrl?: string | null

	/**
	 * Desired payment method.
	 */
	paymentMethod?: PaymentMethod
}
