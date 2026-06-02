<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service responsible for interacting with Safaricom Daraja API (M-Pesa STK Push).
 *
 * Responsibilities:
 * - Generate OAuth access token
 * - Initiate STK push request
 * - Build callback URL
 * - Format phone numbers for Daraja
 */
class DarajaService {

	private IClientService $clientService;
	private LoggerInterface $logger;
	private IRequest $request;
	private IAppConfig $appConfig;

	/**
	 * Daraja API configuration
	 * TODO: Should be moved to config/env
	 */
	private string $env = 'dev'; // dev | test | prod

	public function __construct(
		IClientService $clientService,
		LoggerInterface $logger,
		IRequest $request,
		IAppConfig $appConfig,
	) {
		$this->clientService = $clientService;
		$this->logger = $logger;
		$this->request = $request;
		$this->appConfig = $appConfig;
	}

	/**
	 * Initiate STK Push
	 *
	 * Expected payload:
	 * - phone (E.164 format, e.g. +2547...)
	 * - amount
	 * - signUuid (used as AccountReference)
	 *
	 * Returns:
	 * - reference (CheckoutRequestID)
	 * @throws \Exception
	 */
	public function initiatePayment(array $payload): array {

		$config = $this->getConfig();

		$shortCode = $config['shortCode'];
		$passKey = $config['passKey'];
		$baseUrl = $config['baseUrl'];

		$amount = $payload['amount'];

		$this->validateAmount($amount);

		/**
		 * DARAJA AMOUNT CONSTRAINT
		 *
		 * Daraja (M-Pesa) requires amounts as INTEGER values in major units (KES).
		 *
		 * Example:
		 * - 80.00 → 80 ✅
		 * - 75.50 → INVALID ❌
		 *
		 * IMPORTANT:
		 * - No decimals allowed
		 * - Amount MUST be validated before sending request
		 *
		 * RULE:
		 * - DO NOT round or mutate the amount
		 * - FAIL FAST if amount is not a whole number
		 *
		 * WHY:
		 * Silent mutation may result in incorrect charges (financial integrity risk)
		 */
		$amountInt = (int)$amount;

		$token = $this->getAccessToken();
		$client = $this->clientService->newClient();

		// Timestamp required by Daraja
		$timestamp = date('YmdHis');

		// Password = base64(shortCode + passKey + timestamp)
		$password = base64_encode(
			$shortCode . $passKey . $timestamp
		);

		// Build callback URL
		$callbackUrl = $this->getCallbackUrl();

		$this->logger->debug('Daraja callback URL generated', [
			'callbackUrl' => $callbackUrl
		]);

		// Convert +254... → 254...
		$formattedPhone = $this->formatPhone($payload['phone']);

		$requestBody = [
			'BusinessShortCode' => $shortCode,
			'Password' => $password,
			'Timestamp' => $timestamp,
			'TransactionType' => 'CustomerPayBillOnline',
			'Amount' => $amountInt,
			'PartyA' => $formattedPhone,
			'PartyB' => $shortCode,
			'PhoneNumber' => $formattedPhone,
			'CallBackURL' => $callbackUrl,

			/**
			 * AccountReference:
			 * - Visible to user
			 * - NOT used for reconciliation
			 */
			'AccountReference' => $payload['signUuid'],

			'TransactionDesc' => 'GoPaperless Signature Payment',
		];

		$this->logger->debug('Sending Daraja STK Push', [
			'phone' => $formattedPhone,
			'amount' => $payload['amount'],
			'sign_uuid' => $payload['signUuid']
		]);

		$response = $client->post(
			$baseUrl . '/mpesa/stkpush/v1/processrequest',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode($requestBody),
				'timeout' => 30
			]
		);

		$data = json_decode($response->getBody(), true);

		if (!is_array($data)) {
			throw new RuntimeException('Invalid JSON response from Daraja');
		}

		$this->logger->info('Daraja STK response', [
			'response' => $data
		]);

		/**
		 * Daraja success response:
		 * ResponseCode === "0"
		 */
		if (!isset($data['ResponseCode']) || $data['ResponseCode'] !== '0') {

			$this->logger->error('Daraja STK push failed', [
				'response' => $data
			]);

			throw new RuntimeException(
				'Daraja STK push failed: ' . ($data['errorMessage'] ?? 'Unknown error')
			);
		}

		/**
		 * IMPORTANT:
		 * CheckoutRequestID is our external reference
		 * Used later in callback to map payment
		 */
		return [
			'reference' => $data['CheckoutRequestID'],
			'raw' => $data,
			'message' => $data['ResponseDescription'] ?? 'STK push initiated',
		];
	}

	public function test(): array {
		return [
			'test' => true,
			'provider' => 'daraja'
		];
	}

	public function queryStkStatus(string $checkoutRequestId): array {
		$config = $this->getConfig();

		// Validate config early
		if (
			empty($config['shortCode'])
			|| empty($config['passKey'])
			|| empty($config['baseUrl'])
		) {
			$this->logger->error('Daraja config missing for query', $config);
			throw new \RuntimeException('Daraja configuration is incomplete.');
		}

		$timestamp = date('YmdHis');

		$password = base64_encode(
			$config['shortCode']
			. $config['passKey']
			. $timestamp
		);

		$token = $this->getAccessToken();
		$client = $this->clientService->newClient();
		$baseUrl = $config['baseUrl'];

		$url = rtrim($baseUrl, '/') . '/mpesa/stkpushquery/v1/query';

		try {
			$response = $client->post($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'json' => [
					'BusinessShortCode' => $config['shortCode'],
					'Password' => $password,
					'Timestamp' => $timestamp,
					'CheckoutRequestID' => $checkoutRequestId,
				],
			]);

			$data = json_decode($response->getBody(), true);

			if (!is_array($data)) {
				throw new RuntimeException('Invalid JSON response from Daraja');
			}

			$this->logger->info('Daraja query response', [
				'checkoutRequestId' => $checkoutRequestId,
				'response' => $data,
			]);

			return $this->normalizeSTKQueryResponse($data);

		} catch (\Throwable $e) {
			$this->logger->error('Daraja query failed', [
				'checkoutRequestId' => $checkoutRequestId,
				'error' => $e->getMessage(),
			]);

			throw $e;
		}
	}

	/**
	 * Generate OAuth Access Token
	 */
	private function getAccessToken(): string {

		$config = $this->getConfig();

		$consumerKey = $config['consumerKey'];
		$consumerSecret = $config['consumerSecret'];
		$baseUrl = $config['baseUrl'];

		$client = $this->clientService->newClient();

		$credentials = base64_encode(
			$consumerKey . ':' . $consumerSecret
		);

		$response = $client->get(
			$baseUrl . '/oauth/v1/generate?grant_type=client_credentials',
			[
				'headers' => [
					'Authorization' => 'Basic ' . $credentials,
				],
				'timeout' => 30
			]
		);

		$data = json_decode($response->getBody(), true);


		if (!is_array($data)) {
			throw new RuntimeException('Invalid JSON response from Daraja');
		}

		if (!isset($data['access_token'])) {

			$this->logger->error('Daraja token generation failed', [
				'response' => $data
			]);

			throw new RuntimeException('Failed to get Daraja access token');
		}

		return $data['access_token'];
	}

	/**
	 * @param float $amount
	 * @return void
	 */
	private function validateAmount(float $amount): void {
		if (floor($amount) !== $amount) {
			throw new RuntimeException(
				'Daraja requires whole number amounts. Received: ' . $amount
			);
		}

		if ($amount <= 0) {
			throw new RuntimeException('Amount must be greater than zero');
		}
	}

	/**
	 * Build callback URL dynamically
	 *
	 * Priority:
	 * 1. Manual override (ngrok/testing)
	 * 2. Proxy headers (nginx/ngrok)
	 * 3. Fallback to server host
	 */
	private function getCallbackUrl(): string {

		$callbackPath = '/ocs/v2.php/apps/libresign/api/v1/payment/callback/daraja';

		$config = $this->getConfig();

		/**
		 * =========================================================
		 * 1. Manual override (HIGHEST PRIORITY - dev/testing only)
		 * =========================================================
		 *
		 * Used for:
		 * - local debugging with external callbacks
		 *
		 * MUST be empty in production.
		 */
		$manualOverrideBaseUrl = ''; // e.g. https://xxxxx.ngrok-free.app

		if (!empty($manualOverrideBaseUrl)) {
			return rtrim($manualOverrideBaseUrl, '/') . $callbackPath;
		}

		/**
		 * =========================================================
		 * 2. Config-driven base URL (production-ready)
		 * =========================================================
		 *
		 * Set via admin UI:
		 * daraja_gopaperless_callback_base_url
		 *
		 */
		$configBaseUrl = trim($config['callbackBaseUrl'] ?? '');

		if (!empty($configBaseUrl)) {
			return rtrim($configBaseUrl, '/') . $callbackPath;
		}

		/**
		 * =========================================================
		 * 3. Proxy-aware detection
		 * =========================================================
		 *
		 * Handles:
		 * - nginx reverse proxy
		 * - cloudflare
		 * - ngrok (if headers are forwarded)
		 */
		$forwardedProto = $this->request->getHeader('X-Forwarded-Proto');
		$forwardedHost = $this->request->getHeader('X-Forwarded-Host');

		if (!empty($forwardedProto) && !empty($forwardedHost)) {
			return $forwardedProto . '://' . $forwardedHost . $callbackPath;
		}

		/**
		 * =========================================================
		 * 4. Fallback (local development)
		 * =========================================================
		 *
		 * Last resort when no config or proxy headers exist.
		 * Usually:
		 * - localhost
		 * - docker internal network
		 */
		$host = $this->request->getServerHost();

		// Basic scheme detection
		$isHttps = (
			$this->request->getHeader('HTTPS') === 'on'
			|| $this->request->getHeader('X-Forwarded-Proto') === 'https'
		);

		$scheme = $isHttps ? 'https' : 'http';

		return $scheme . '://' . $host . $callbackPath;
	}

	/**
	 * Convert E.164 phone number to Daraja format
	 *
	 * +254712345678 → 254712345678
	 */
	private function formatPhone(string $phone): string {

		if (!str_starts_with($phone, '+')) {
			throw new RuntimeException('Phone number must be in E.164 format (+254...)');
		}

		$formatted = substr($phone, 1);

		// Ensure Kenyan number
		if (!str_starts_with($formatted, '254')) {
			throw new RuntimeException('Only Kenyan numbers are supported for M-Pesa');
		}

		return $formatted;
	}

//		private function getConfig(): array {
//			return [
//				'baseUrl' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_base_url'
//				),
//				'consumerKey' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_consumer_key'
//				),
//				'consumerSecret' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_consumer_secret'
//				),
//				'passKey' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_pass_key'
//				),
//				'shortCode' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_shortcode'
//				),
//				'goPaperlessCallbackUrl' => $this->appConfig->getValueString(
//					Application::APP_ID,
//					'daraja_gopaperless_callback_base_url'
//				),
//			];
//		}

	// private function getConfig(): array {
	// 	return $this->getTestConfig();
	// }

	private function normalizeSTKQueryResponse(array $data): array {
		$resultCode = $data['ResultCode'] ?? null;
		$resultDesc = $data['ResultDesc'] ?? '';

		return match ($resultCode) {
			'0' => [
				'status' => 'SUCCESS',
				'raw' => $data,
				'description' => $resultDesc,
			],
			// user cancelled
			'1032' => [
				'status' => 'FAILED',
				'reason' => 'cancelled',
				'raw' => $data,
				'description' => $resultDesc,
			],
			// timeout
			'1037' => [
				'status' => 'FAILED',
				'reason' => 'timeout',
				'raw' => $data,
				'description' => $resultDesc,
			],

			default => [
				'status' => 'PENDING',
				'raw' => $data,
				'description' => $resultDesc,
			],
		};
	}

	private function getConfig(): array {
		return match ($this->env) {
			'dev' => $this->getDevConfig(),
			'test' => $this->getTestConfig(),
			'prod' => $this->getProdConfig(),
			default => throw new \RuntimeException('Invalid environment'),
		};
    }

	private function getTestConfig(): array {
		return [
			'baseUrl' => 'https://sandbox.safaricom.co.ke',
			'consumerKey' => 'QVNGIwcP7vT9m0ZS4SGmnw7x1o8MGuiAY2UiGUrRMAXJH9aY',
			'consumerSecret' => 'LYp6RpUTAK8eGGTM1oA5RwMYhkCNA2qS23WalsX21z6kSe0h4PlzjC1op3gxkXeD',
			'passKey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
			'shortCode' => '174379',
			'callbackBaseUrl' => 'https://portal-acknowledge-territory-med.trycloudflare.com ',
		];
	}

	private function getDevConfig(): array {
		return [
			'baseUrl' => 'https://sandbox.safaricom.co.ke',
			'consumerKey' => 'QVNGIwcP7vT9m0ZS4SGmnw7x1o8MGuiAY2UiGUrRMAXJH9aY',
			'consumerSecret' => 'LYp6RpUTAK8eGGTM1oA5RwMYhkCNA2qS23WalsX21z6kSe0h4PlzjC1op3gxkXeD',
			'passKey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
			'shortCode' => '174379',
			'callbackBaseUrl' => 'https://portal-acknowledge-territory-med.trycloudflare.com ',
		];
    }

	private function getProdConfig(): array {
		return [
			'baseUrl' => 'https://api.safaricom.co.ke',
			'consumerKey' => '4iO9OayobxUQBtvLkYUsvTbdwLyywi8C',
			'consumerSecret' => 'QQVC4CfgWzDjKmCZ',
			'passKey' => 'bd8c94609d24d5c5fad41b0a45dd6dc0cf904f55488e86031c71a85b0962fd59',
			'shortCode' => '4043687',
			'callbackBaseUrl' => 'https://gopaperless.dev.tenda.world',
		];
    }
}
