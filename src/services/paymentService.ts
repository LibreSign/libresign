/**
 * Represents the signer initiating the payment.
 *
 * This information is mostly used for:
 * - backend auditing
 * - payment reconciliation
 * - receipts / notifications
 *
 * The signer is NOT necessarily a registered platform user.
 * LibreSign supports external signers invited by email.
 */
export interface PaymentDocumentSigner {

	/**
	 * Display name of the signer.
	 * Optional because external signers may not always have
	 * a predefined display name.
	 *
	 * Example:
	 * "James Blake"
	 */
	name?: string;

	/**
	 * Email address of the signer.
	 *
	 * Used for:
	 * - identifying the signer
	 * - payment reconciliation
	 *
	 * Example:
	 * "james@example.com"
	 */
	email?: string;
}


/**
 * Represents the document being signed.
 *
 * The document is included in the payment payload so the backend
 * can link the payment to a specific document signing process.
 */
export interface PaymentDocument {

	/**
	 * Unique identifier of the document
	 *
	 * This corresponds to:
	 * signStore.document.id
	 */
	id: string;

	/**
	 * Optional document name.
	 *
	 * Used mainly for:
	 * - logging
	 * - payment records
	 *
	 * Example:
	 * "Employment Contract.pdf"
	 */
	name?: string;
}


/**
 * Supported mobile money providers.
 *
 * Currently limited to Kenyan providers because the MVP
 * targets the Kenyan market.
 *
 */
export type MobileProvider = 'AIRTEL' | 'SAFARICOM';


/**
 * Mobile money payment details.
 *
 * This object contains the information required by the backend
 * payment gateway integration to initiate a mobile money request.
 */
export interface PaymentMobileMoney {

	/**
	 * Normalized phone number used for payment.
	 *
	 * The number MUST be in international format:
	 *
	 * +254712345678
	 *
	 * This ensures compatibility with:
	 * - M-Pesa STK Push
	 * - Airtel Money APIs
	 */
	phoneNumber: string;

	/**
	 * Mobile network provider detected from the phone number.
	 *
	 * This allows the backend to route the payment request
	 * to the correct mobile money gateway.
	 */
	provider: MobileProvider;
}


/**
 * Supported payment methods.
 *
 * The MVP currently supports mobile money only,
 * but card support will be added later.
 */
export type PaymentMethod = 'MOBILE_MONEY' | 'CARD';


/**
 * Main payment payload sent to the backend payment service.
 *
 * This payload contains everything required to:
 *
 * 1. Identify the signer
 * 2. Identify the document
 * 3. Identify the specific signing session
 * 4. Initiate the payment
 *
 * The backend should treat this payload as the source of truth
 * for creating a payment transaction record.
 */
export interface PaymentPayload {

	/**
	 * Payment method selected by the user.
	 *
	 * Example:
	 * MOBILE_MONEY
	 */
	paymentMethod: PaymentMethod;

	/**
	 * Amount to be charged for signing.
	 *
	 * For the MVP this is fixed:
	 * 80 KES
	 *
	 * Later this may become dynamic depending on:
	 * - document type
	 * - number of signatures
	 * - organisation pricing tiers
	 */
	amount: number;

	/**
	 * Currency used for the transaction.
	 *
	 * Example:
	 * "KES"
	 */
	currency: string;

	/**
	 * Information about the signer initiating the payment.
	 *
	 * This is mainly used for logging and reconciliation.
	 */
	signer: PaymentDocumentSigner;

	/**
	 * Unique identifier for the signing request.
	 *
	 * This is the MOST IMPORTANT identifier for payments.
	 *
	 * Why:
	 * - Each signer has their own signRequestId
	 * - Multiple signers may exist for one document
	 * - Each signer may need to pay separately
	 *
	 * This allows the backend to:
	 * - track payments per signer
	 * - allow retries if payment fails
	 * - support multi-signer documents
	 */
	signRequestId: string;

	/**
	 * Public signing token used by LibreSign.
	 *
	 * This UUID represents the signing session and is already
	 * used in LibreSign signing APIs:
	 *
	 * /sign/uuid/{signUuid}
	 *
	 * Including this allows the backend to:
	 * - validate the signing session
	 * - connect payment status to the signing workflow
	 */
	signUuid: string;

	/**
	 * Document being signed.
	 *
	 * Included to provide context for the payment
	 * and assist backend logging or analytics.
	 */
	document: PaymentDocument;

	/**
	 * Mobile money payment details.
	 *
	 * Only required when paymentMethod = MOBILE_MONEY.
	 *
	 * In the future this field may be replaced with a
	 * union type if multiple payment methods are supported.
	 */
	mobileMoney?: PaymentMobileMoney;
}


export type PaymentStatus =
	| 'PENDING'
	| 'PROCESSING'
	| 'SUCCESS'
	| 'FAILED'
	| 'EXPIRED';

export interface PaymentResponse {
	paymentId: string;
	status: PaymentStatus;
	message?: string;
}

export interface PreComposedPaymentPayload {
	phoneNumber: string;
	provider: MobileProvider;
	signer: PaymentDocumentSigner;
	signRequestId: string;
	document: PaymentDocument;
	signUuid: string;
}

export const PAYMENT_AMOUNT = 80;
export const PAYMENT_CURRENCY = 'KES';

//TODO: Will replace later with real backend
export const BACKEND_URL = 'http://localhost:3000';

/**
 * Initiate payment
 */
export async function initiatePayment(
	payload: PaymentPayload
): Promise<PaymentResponse> {

	const response = await fetch(`${BACKEND_URL}/payments/initiate`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(payload),
	});

	if (!response.ok) {
		throw new Error('Failed to initiate payment');
	}

	return response.json();
}

/**
 * Check payment status
 */
export async function checkPaymentStatus(
	paymentId: string
): Promise<PaymentResponse> {

	const response = await fetch(
		`${BACKEND_URL}/payments/status/${paymentId}`
	);

	if (!response.ok) {
		throw new Error('Failed to fetch payment status');
	}

	return response.json();
}

/**
 * Poll payment status until completion
 */
export async function pollPaymentStatus(
	paymentId: string,
	callback?: (status: PaymentStatus) => void,
	interval = 3000,
	timeout = 120000,
	start = Date.now(),
	isCancelled?: () => boolean
): Promise<PaymentResponse> {

	if (isCancelled?.()) {
		throw new Error('Polling cancelled')
	}

	const result = await checkPaymentStatus(paymentId)

	callback?.(result.status)

	if (result.status === 'SUCCESS') {
		return result
	}

	if (['FAILED','EXPIRED'].includes(result.status)) {
		throw new Error(`Payment ${result.status}`)
	}

	if (Date.now() - start > timeout) {
		throw new Error('Payment polling timeout')
	}

	await delay(interval)

	return pollPaymentStatus(
		paymentId,
		callback,
		interval,
		timeout,
		start,
		isCancelled
	)
}

/**
 * Compose payment payload for mobile money payments
 */
export function composeMobileMoneyPaymentPayload({
	phoneNumber,
	provider,
	signer,
	document,
	signRequestId,
	signUuid
}: PreComposedPaymentPayload): PaymentPayload {

	invariant(signRequestId, 'Payment payload error: Missing signRequestId')
	invariant(signUuid, 'Payment payload error: Missing signUuid')

	invariant(document?.id, 'Payment payload error: Missing document id')

	invariant(phoneNumber, 'Payment payload error: Missing phone number')
	invariant(provider, 'Payment payload error: Missing mobile provider')

	const { email, name } = signer ?? {}
	const { id: documentId, name: documentName = '' } = document

	return {
		paymentMethod: 'MOBILE_MONEY',
		amount: PAYMENT_AMOUNT,
		currency: PAYMENT_CURRENCY,

		// signer initiating the payment
		signer: {
			email,
			name
		},

		// internal signer request identifier
		signRequestId,

		// public signing token used by LibreSign signing API
		signUuid,

		// document context
		document: {
			id: documentId,
			name: documentName
		},

		// mobile money payment details
		mobileMoney: {
			phoneNumber,
			provider
		}
	}
}

export function mapBackendStatus(status: string): PaymentStatus {
	switch (status) {
		case 'pending':
			return 'PENDING';
		case 'paid':
			return 'SUCCESS';
		case 'failed':
			return 'FAILED';
		default:
			throw new Error(`Unknown payment status: ${status}`);
	}
}

/**
 * -----------------------------
 * Demo / Mock payment functions
 * -----------------------------
 */

/**
 * Demo initiate payment
 */
export async function demoInitiatePayment(): Promise<PaymentResponse> {

	await delay(1000);

	return {
		paymentId: 'demo-payment-id',
		status: 'PENDING',
		message: 'Demo payment initiated',
	};
}

/**
 * Demo check payment status
 */
export async function demoCheckPaymentStatus(
	paymentId: string
): Promise<PaymentResponse> {

	await delay(1000);

	return {
		paymentId,
		status: 'SUCCESS',
		message: 'Demo payment successful',
	};
}

/**
 * Demo polling simulation
 */
export async function demoPollPaymentStatus(
	paymentId: string,
	callback?: (status: PaymentStatus) => void
): Promise<PaymentResponse> {

	callback?.('PENDING')
	await delay(1000)

	callback?.('PROCESSING')
	await delay(2000)

	// 20% chance of failure (for testing)
	if (Math.random() < 0.2) {
		callback?.('FAILED')
		throw new Error('Payment failed')
	}

	callback?.('SUCCESS')

	return {
		paymentId,
		status: 'SUCCESS',
		message: 'Demo payment completed',
	}
}

/**
 * Utility delay
 */
function delay(ms: number) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Utility invariant
 */
function invariant(condition: unknown, message: string): asserts condition {
	if (!condition) {
		throw new Error(message)
	}
}
