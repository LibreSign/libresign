<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Service\Payment\DTO\DPOVerifyTokenResultDTO;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * DPO Payment Service
 *
 * Responsibilities:
 * - createToken (checkout session creation)
 * - chargeTokenMobile (STK / mobile push)
 * - verifyToken (status polling fallback)
 * - getMobilePaymentOptions (optional UX support)
 *
 * IMPORTANT DESIGN NOTES:
 * - DPO is XML-based (not JSON)
 * - Callback (pushPayments) is SUCCESS-only
 * - verifyToken is REQUIRED fallback
 * - Amounts are passed in MAJOR units (e.g. 80.00)
 */
class DpoPaymentService
{
	private const DPO_WEBHOOK_PATH = '/api/v1/payment/webhook/dpo';
	private string $defaultCurrency = 'KES';
	private IClientService $clientService;
	private LoggerInterface $logger;
	private string $env = 'dev'; // dev | test | prod

	protected IAppConfig $appConfig;

	public function __construct(
		IClientService $clientService,
		LoggerInterface $logger,
		IAppConfig $appConfig,
	) {
		$this->clientService = $clientService;
		$this->logger = $logger;
		$this->appConfig = $appConfig;
	}

	/**
	 * Creates a payment token with DPO.
	 *
	 * This token is later used to redirect the user to the
	 * DPO hosted payment page.
	 * NOTE: DPO expects amounts in major units as FLOAT values.
	 * @param string $userEmail
	 * @param string $signUuid
	 * @param float $amount
	 * @param string $redirectUrl
	 * @param string|null $currency
	 * @return array
	 * @throws Throwable
	 */
	public function createToken(
		string $userEmail,
		string $signUuid,
		float $amount,
		string $redirectUrl,
		string $currency,
		?string $method = null, // card | mobile
		?string $defaultPayment = null,
		?string $defaultPaymentCountry = null
	): array {

		$config = $this->getConfig();

		$companyToken = $config['companyToken'];
		$serviceId = $config['serviceId'];
		$paymentUrl = $config['paymentUrl'];
		$callbackBaseUrl = $config['callbackBaseUrl'];
		$callbackUrl = null;

		if (!$callbackBaseUrl) {
			throw new RuntimeException('DPO callbackBaseUrl is not configured');
		}

		/**
		 * Block tabs not relevant to user on DPO payment page
		 */
		$blockPaymentXml = $this->buildBlockPaymentXml($method ?? 'card');

		/**
		 * Build Redirect URL (user-facing)
		 */
		$redirectUrlWithContext = $redirectUrl
			. (str_contains($redirectUrl, '?') ? '&' : '?')
			. http_build_query(['signUuid' => $signUuid]);

		/**
		 * Apply callback base URL (important for DPO routing)
		 */
		if ($callbackBaseUrl) {
			$path = parse_url($redirectUrlWithContext, PHP_URL_PATH);
			$query = parse_url($redirectUrlWithContext, PHP_URL_QUERY);

			$redirectUrlWithContext = rtrim($callbackBaseUrl, '/') . $path;
			$callbackUrl = rtrim($callbackBaseUrl ?? '', '/') . self::DPO_WEBHOOK_PATH;

			if ($query) {
				$redirectUrlWithContext .= '?' . $query;
			}
		}

		/**
		 * Currency fallback
		 */
		$currency = $currency ?: $this->defaultCurrency;

		/**
		 * Optional UX hints for DPO UI
		 */
		$defaultPaymentXml = $defaultPayment
			? "<DefaultPayment>{$defaultPayment}</DefaultPayment>"
			: '';

		$defaultPaymentCountryXml = $defaultPaymentCountry
			? "<DefaultPaymentCountry>{$defaultPaymentCountry}</DefaultPaymentCountry>"
			: '';

		/**
		 * Escape user-controlled fields
		 */
		$escapedEmail = htmlspecialchars($userEmail, ENT_XML1, 'UTF-8');
		$escapedRedirectUrl = htmlspecialchars($redirectUrlWithContext, ENT_XML1, 'UTF-8');
		$callbackUrl = rtrim($callbackBaseUrl ?? '', '/') . self::DPO_WEBHOOK_PATH;
		$serviceDate = date('Y/m/d H:i');

		/**
		 * XML REQUEST BODY
		 *
		 * NOTE:
		 * - BackURL = server callback
		 * - RedirectURL = user redirect
		 */
		$xml =  "
		<API3G>
			<CompanyToken>{$companyToken}</CompanyToken>
			<Request>createToken</Request>

			<Transaction>
				<PaymentAmount>{$amount}</PaymentAmount>
				<PaymentCurrency>{$currency}</PaymentCurrency>
				<CompanyRef>{$signUuid}</CompanyRef>

				<customerEmail>{$escapedEmail}</customerEmail>

				<RedirectURL>{$escapedRedirectUrl}</RedirectURL>
				<BackURL>{$callbackUrl}</BackURL>

				{$defaultPaymentXml}
				{$defaultPaymentCountryXml}
			</Transaction>

			<Services>
				<Service>
					<ServiceType>{$serviceId}</ServiceType>
					<ServiceDescription>GoPaperless</ServiceDescription>
					<ServiceDate>{$serviceDate}</ServiceDate>
				</Service>
			</Services>

			{$blockPaymentXml}
		</API3G>";

		$response = $this->sendRequest($xml);

		$raw = $this->normaliseXmlResponse($response);

		$result = $raw['Result'] ?? null;

		if ($result !== '000') {
			throw new RuntimeException(
				"DPO Token Creation Failed: {$response->ResultExplanation} (Code: {$result})"
			);
		}

		$token = (string)$response->TransToken;

		return [
			'reference' => $token,
			'explanation' => (string)$response->ResultExplanation,
			'paymentUrl' => $paymentUrl . '?ID=' . $token,
			'raw' => $raw,
		];
	}


	/**
	 * Initiates a mobile payment request using DPO's ChargeTokenMobile API.
	 *
	 * This triggers a payment request (e.g. M-Pesa STK push) to the user's phone.
	 *
	 * Payment status MUST be verified later via `verifyToken()`.
	 *
	 * Flow:
	 * 1. FE calls initiate → gets transactionToken
	 * 2. FE calls charge-mobile → this method
	 * 3. User receives payment prompt on phone
	 * 4. FE polls BE → verifyToken() determines final status
	 *
	 *  Important:
	 * - StatusCode 130 = request accepted (NOT payment success)
	 * - Always return PENDING on success here
	 * - Instructions may contain HTML → FE must render safely
	 * - MNO must match DPO terminal config (e.g. "mpesa", "airtel")
	 *
	 * @param string $transactionToken Token from createToken()
	 * @param string $phone Phone number in international format (e.g. 2547XXXXXXXX)
	 * @param string $mno Mobile Network Operator (lowercase, e.g. "mpesa")
	 * @param string $mnoCountry Country name (default: "kenya")
	 *
	 * @return array{
	 *     status: 'PENDING'|'FAILED',
	 *     instructions?: string,
	 *     redirect?: bool,
	 *     error?: string,
	 *     code?: string
	 * }
	 *
	 * @throws Throwable On network / XML parsing errors
	 */
	public function chargeTokenMobile(
		string $transactionToken,
		string $phone,
		string $mno,
		string $mnoCountry = 'kenya'
	): array {

        $formattedPhone = $this->formatPhone($phone);

		$config = $this->getConfig();

		$xml = "
		<API3G>
			<CompanyToken>{$config['companyToken']}</CompanyToken>
			<Request>ChargeTokenMobile</Request>
			<TransactionToken>{$transactionToken}</TransactionToken>
			<PhoneNumber>{$formattedPhone}</PhoneNumber>
			<MNO>{$mno}</MNO>
			<MNOcountry>{$mnoCountry}</MNOcountry>
		</API3G>";

		$response = $this->sendRequest($xml);

		$statusCode = (string)($response->StatusCode ?? '');
		$explanation = (string)($response->ResultExplanation ?? '');
		$instructions = (string)($response->instructions ?? '');
		$redirect = (string)($response->RedirectOption ?? '0');

		$this->logger->info('DPO chargeTokenMobile response', [
			'token' => $transactionToken,
			'statusCode' => $statusCode,
			'explanation' => $explanation,
			'redirect' => $redirect,
		]);

		$raw = $this->normaliseXmlResponse($response);

		/**
		 * SUCCESS CASE (REQUEST ACCEPTED, NOT PAYMENT COMPLETE)
		 *
		 * 130 = New invoice → user should receive prompt
		 */
		if ($statusCode === '130') {
			return [
				'status' => 'ACCEPTED',
				'instructions' => $instructions,
				'redirect' => $redirect === '1',
				'raw' => $raw,
			];
		}

		/**
		 * HARD FAILS (VALIDATION / CONFIG ERRORS)
		 */
		$errorMap = [
			'950' => 'Missing mandatory fields',
			'951' => 'Invalid transaction token',
			'952' => 'Missing MNO',
			'953' => 'Missing MNO country',
			'954' => 'Missing phone number',
			'955' => 'Invalid phone number',
			'956' => 'Terminal not configured',
		];

		$message = $errorMap[$statusCode]
			?? ($explanation !== '' ? $explanation : 'Unknown error');

		$this->logger->error('DPO chargeTokenMobile failed', [
			'statusCode' => $statusCode,
			'message' => $message,
		]);

		return [
			'status' => 'FAILED',
			'error' => $message,
			'code' => $statusCode,
			'raw' => $raw,
		];
	}

	/**
	 * Verify DPO transaction status.
	 *
	 * IMPORTANT:
	 * - verifyToken is BOTH:
	 *   1. Payment reconciliation
	 *   2. Merchant acknowledgement
	 *
	 * - DPO may return transient states for mobile flows
	 * - NOT all non-000 responses are failures
	 * - Background verification should continue retrying
	 *   while status remains retryable
	 *
	 * @throws Throwable
	 */
	public function verifyToken(
		string $token,
		bool $acknowledge = true
	): DPOVerifyTokenResultDTO {

		$config = $this->getConfig();

		$companyToken = $config['companyToken'];

		$verifyTransaction = $acknowledge ? '1' : '0';

		$xml = "
		<API3G>
			<CompanyToken>{$companyToken}</CompanyToken>
			<Request>verifyToken</Request>
			<TransactionToken>{$token}</TransactionToken>
			<VerifyTransaction>{$verifyTransaction}</VerifyTransaction>
		</API3G>";

		$response = $this->sendRequest($xml);

		$parsed = $this->normaliseXmlResponse($response);

		$this->logger->info('DPO verifyToken response', [
			'token' => $token,
			'parsed' => $parsed,
		]);

		if (!isset($response->Result)) {
			throw new RuntimeException(
				'DPO verifyToken response missing Result code'
			);
		}

		$resultCode = (string) $response->Result;

		/**
		 * IMPORTANT:
		 * These states are NOT hard failures.
		 *
		 * DPO mobile payments may remain:
		 * - queued
		 * - pending bank
		 * - awaiting authorization
		 * - not yet paid
		 *
		 * Verification layer MUST remain retry-safe.
		 */
		$status = match ($resultCode) {

			// Final success
			'000' => 'SUCCESS',

			// Retryable / transient / async states
			'001', // Authorized
			'002', // Underpaid / overpaid
			'003', // Pending bank
			'005', // Queued authorization
			'007', // Pending split payment
			'900' => 'PENDING',

			// Final failure states
			'901', // Declined
			'902', // Data mismatch
			'903', // Payment timeout
			'904', // Cancelled
			'950' => 'FAILED',

			// Unknown states treated defensively
			default => 'FAILED',
		};

		return new DPOVerifyTokenResultDTO(
			resultCode: $resultCode,
			status: $status,

			explanation: (string)($response->ResultExplanation ?? null),

			transactionCurrency: (string)($response->TransactionCurrency ?? null),

			transactionAmount: isset($response->TransactionAmount)
				? (float)$response->TransactionAmount
				: null,

			transactionFinalCurrency: (string)($response->TransactionFinalCurrency ?? null),

			transactionFinalAmount: isset($response->TransactionFinalAmount)
				? (float)$response->TransactionFinalAmount
				: null,

			approvalCode: (string)($response->TransactionApproval ?? null),

			mobilePaymentRequest: (string)($response->MobilePaymentRequest ?? null),

			fraudCode: (string)($response->FraudAlert ?? null),

			fraudExplanation: (string)($response->FraudExplnation ?? null),

			customerPhone: (string)($response->CustomerPhone ?? null),

			customerCountry: (string)($response->CustomerCountry ?? null),

			settlementDate: (string)($response->TransactionSettlementDate ?? null),

			netAmount: isset($response->TransactionNetAmount)
				? (float)$response->TransactionNetAmount
				: null,

			raw: $parsed,
		);
	}


	/**
	 * Retrieve available mobile payment options for a given transaction.
	 *
	 * This method calls DPO's `GetMobilePaymentOptions` API and returns
	 * a normalized list of supported mobile providers (e.g. mpesa, airtel)
	 * for the provided transaction token.
	 *
	 * Notes:
	 * - This is OPTIONAL in current flow (we auto-detect MNO).
	 * - Useful for future dynamic provider selection on FE.
	 * - Response may vary depending on DPO terminal configuration.
	 *
	 * @param string $transactionToken Token returned from createToken()
	 *
	 * @return array<int, array{
	 *     provider: string,
	 *     country: string,
	 *     countryCode: string,
	 *     prefix: string,
	 *     currency: string,
	 *     amount: float,
	 *     instructions: string,
	 *     logo: string
	 * }>
	 *
	 * @throws RuntimeException If no mobile options are available or response is invalid
	 * @throws Throwable On network / XML parsing errors
	 */
	public function getMobilePaymentOptions(string $transactionToken): array
	{
		$config = $this->getConfig();
		$companyToken = $config['companyToken'];

		$xml = "
		<API3G>
			<CompanyToken>{$companyToken}</CompanyToken>
			<Request>GetMobilePaymentOptions</Request>
			<TransactionToken>{$transactionToken}</TransactionToken>
		</API3G>";

		$response = $this->sendRequest($xml);

		$this->logger->info('DPO GetMobilePaymentOptions response', [
			'token' => $transactionToken,
			'raw' => $response->asXML(),
		]);

		if (
			!isset($response->paymentoptions) ||
			!isset($response->paymentoptions->mobileoption)
		) {
			throw new RuntimeException('Invalid response from DPO (mobile options)');
		}

		$options = [];

		foreach ($response->paymentoptions->mobileoption as $option) {
			$options[] = [
				'provider' => strtolower((string)$option->paymentname),
				'country' => (string)$option->country,
				'countryCode' => (string)$option->countryCode,
				'prefix' => (string)$option->celluarprefix,
				'currency' => (string)$option->currency,
				'amount' => (float)$option->amount,
				'instructions' => (string)$option->instructions,
				'logo' => (string)($option->logo ?? ''),
			];
		}

		return $options;
	}

	/**
	 * Map DPO verifyToken response codes to internal payment statuses.
	 *
	 * DPO Codes:
	 * - 000 → Payment successful
	 * - 001 → Authorized (pending completion)
	 * - 002 → Pending / underpaid / overpaid
	 *
	 * @param string $result Raw DPO Result code
	 *
	 * @return 'SUCCESS'|'PENDING'|'FAILED'
	 */
	public function mapVerifyStatus(string $result): string
	{
		return match ($result) {
			'000' => 'SUCCESS',
			'001', '002' => 'PENDING',
			default => 'FAILED',
		};
	}

	public function testDpo(): array
	{
		return [
			'test' => true,
			'provider' => 'dpo'
		];
	}

	/**
	 * Convert E.164 phone number to Daraja format
	 *
	 * +254712345678 → 254712345678 / +255712345678 → 255712345678
	 */
	private function formatPhone(string $phone): string {

		if (!str_starts_with($phone, '+')) {
			throw new RuntimeException('[DPOPaymentService] - Phone number must be in E.164 format (+254...)');
		}

		$formatted = substr($phone, 1);

		return $formatted;
	}


	private function normaliseXmlResponse(
		SimpleXMLElement $response
	): array {
		return json_decode(
			json_encode($response),
			true
		) ?? [];
	}

	/**
	 * Sends an XML request to the DPO API and returns the parsed XML response.
	 * Throws exceptions on network errors or invalid responses.
	 */
	private function sendRequest(string $xml): SimpleXMLElement
	{
		$config = $this->getConfig();
		$client = $this->clientService->newClient();
		$endpoint = $config['endpoint'];

		// testing xml
		$this->logger->debug('DPO request XML', [
			'endpoint' => $endpoint,
			'xml' => $xml,
			'config' => $config,
		]);

		try {

			$this->logger->debug('Sending request to DPO API', [
				'endpoint' => $endpoint
			]);

			$response = $client->post($endpoint, [
				'headers' => [
					'Content-Type' => 'text/xml'
				],
				'body' => $xml,
				'timeout' => 30
			]);

			$statusCode = $response->getStatusCode();
			$body = $response->getBody();

			$this->logger->debug('Received response from DPO API', [
				'status_code' => $statusCode,
				'body' => $body
			]);

			// Validate XML response
			$parsed = simplexml_load_string($body);

			if ($parsed === false) {

				$this->logger->error('Failed to parse DPO XML response', [
					'raw_response' => $body
				]);

				throw new RuntimeException('Invalid XML response from DPO API');
			}

			return $parsed;
		} catch (Throwable $e) {

			$this->logger->error('DPO API request failed', [
				'endpoint' => $endpoint,
				'error' => $e->getMessage()
			]);

			throw $e;
		}
	}

	private function buildBlockPaymentXml(?string $method): string
	{
		if (!$method) {
			return '';
		}

		$blockMap = [
			'mobile' => ['CC', 'PP', 'BT', 'XP', 'SE'],
			'card'   => ['MO', 'PP', 'BT', 'XP', 'SE'],
		];

		if (!isset($blockMap[$method])) {
			return '';
		}

		$blocks = array_map(
			fn($code) => "<BlockPayment>{$code}</BlockPayment>",
			$blockMap[$method]
		);

		return '<Additional>' . implode('', $blocks) . '</Additional>';
	}

	//	private function getConfig(): array {
	//		return [
	//			'endpoint' => $this->appConfig->getValueString(
	//				Application::APP_ID,
	//				'dpo_endpoint'
	//			),
	//			'companyToken' => $this->appConfig->getValueString(
	//				Application::APP_ID,
	//				'dpo_company_token'
	//			),
	//			'serviceId' => $this->appConfig->getValueString(
	//				Application::APP_ID,
	//				'dpo_service_id'
	//			),
	//			'paymentUrl' => $this->appConfig->getValueString(
	//				Application::APP_ID,
	//				'dpo_payment_url'
	//			),
	//			'goPaperlessCallbackBaseUrl' => $this->appConfig->getValueString(
	//				Application::APP_ID,
	//				'daraja_gopaperless_callback_base_url'
	//			)
	//		];
	//	}

	// private function getConfig(): array
	// {
	// 	return $this->getTestConfig();
	// }

	private function getConfig(): array
	{
		return match ($this->env) {
			'dev' => $this->getDevConfig(),
			'test' => $this->getTestConfig(),
			'prod' => $this->getProdConfig(),
			default => throw new \RuntimeException('Invalid environment'),
		};
	}

	private function getTestConfig(): array
	{
		return [
			'endpoint' => 'https://secure.3gdirectpay.com/API/v6/',
			'companyToken' => '8D3DA73D-9D7F-4E09-96D4-3D44E7A83EA3',
			'serviceId' => '3978',
			'paymentUrl' => 'https://secure.3gdirectpay.com/payv3.php',
			'callbackBaseUrl' => 'https://portal-acknowledge-territory-med.trycloudflare.com ',
		];
	}

	private function getDevConfig(): array
	{
		return [
			'endpoint' => 'https://secure.3gdirectpay.com/API/v6/',
			'companyToken' => '8D3DA73D-9D7F-4E09-96D4-3D44E7A83EA3',
			'serviceId' => '3978',
			'paymentUrl' => 'https://secure.3gdirectpay.com/payv3.php',
			'callbackBaseUrl' => 'https://portal-acknowledge-territory-med.trycloudflare.com ',
		];
	}

	private function getProdConfig(): array
	{
		return [
			'endpoint' => 'https://secure.3gdirectpay.com/API/v6/',
			'companyToken' => '8D3DA73D-9D7F-4E09-96D4-3D44E7A83EA3',
			'serviceId' => '69836',
			'paymentUrl' => 'https://secure.3gdirectpay.com/payv3.php',
			'callbackBaseUrl' => 'https://gopaperless.dev.tenda.world',
		];
	}
}
