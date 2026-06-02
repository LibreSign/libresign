<?php

declare(strict_types=1);

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\PaymentMethod;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\PaymentStatus;
use OCA\Libresign\Service\Payment\DTO\StartPaymentDTO;
use OCA\Libresign\Service\Payment\PaymentService;
use OCA\Libresign\Service\SMSService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;

use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PaymentController extends AEnvironmentAwareController
{

	private PaymentService $paymentService;
	protected LoggerInterface $logger;
	protected SMSService $smsService;

	public function __construct(
		IRequest $request,
		PaymentService $paymentService,
		LoggerInterface $logger,
		SMSService $smsService,
		protected IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->paymentService = $paymentService;
		$this->logger = $logger;
		$this->smsService = $smsService;
	}

	/**
	 * Start payment and return DPO token.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/payment/start',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function start(
		string $userEmail,
		string $signUuid,
		int $signRequestId,
		string $userId,
		?string $redirectUrl,
		string $productCode,
		?string $paymentAttemptId,
		?string $provider,
		?string $phoneNumber,
		?string $callbackUrl,
		?string $paymentMethod,
	): DataResponse {

		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse([
					'success' => false,
					'error' => 'Unauthorized',
				], Http::STATUS_UNAUTHORIZED);
			}

			$uid = $user->getUID();

			$this->logger->info('[PaymentStart]', [
				'userId' => $userId,
				'uid' => $uid,
			]);

			if (!$userId || $uid !== $userId) {
				return new DataResponse([
					'success' => false,
					'error' => 'Invalid user id',
				], Http::STATUS_BAD_REQUEST);
			}

			$methodEnum = PaymentMethod::tryFrom($paymentMethod);

			if (!$methodEnum) {
				return new DataResponse([
					'success' => false,
					'error' => 'Please select valid payment method',
				], Http::STATUS_BAD_REQUEST);
			}

			$providerEnum = PaymentProvider::tryFrom($provider);

			$dto = new StartPaymentDTO(
				userEmail: $userEmail,
				signUuid: $signUuid,
				signRequestId: $signRequestId,
				redirectUrl: $redirectUrl,
				userId: $userId,
				provider: $providerEnum,
				productCode: $productCode,
				paymentMethod: $methodEnum,
				callbackUrl: $callbackUrl,
				paymentAttemptId: $paymentAttemptId,
				phoneNumber: $phoneNumber
			);

			$result = $this->paymentService->startPayment($dto);

			return new DataResponse([
				'success' => true,
				'result' => $result->toArray()
			], Http::STATUS_OK);
		} catch (\Throwable $e) {

			$this->logger->error('Payment creation failed', [
				'exception' => $e
			]);

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Verify payment after redirect.
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/verify',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function verify(string $providerReference): DataResponse
	{

		$status = $this->paymentService->verifyPayment($providerReference);

		return new DataResponse([
			'status' => match ($status) {
				PaymentStatus::PAID => 'SUCCESS',
				PaymentStatus::FAILED,
				PaymentStatus::INITIATION_FAILED => 'FAILED',
				default => 'PENDING',
			},
			'reason' => $status->value
		], Http::STATUS_OK);
	}

	/**
	 * Check if payment is complete.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/status',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function status(string $providerReference): DataResponse
	{

		$status = $this->paymentService->getPaymentStatus($providerReference);

		return new DataResponse([
			'status' => match ($status) {
				PaymentStatus::PAID => 'SUCCESS',
				PaymentStatus::FAILED,
				PaymentStatus::INITIATION_FAILED => 'FAILED',
				default => 'PENDING',
			}
		], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/payment/webhook/daraja',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function darajaCallback(): DataResponse
	{

		$rawBody = file_get_contents('php://input');
		$data = json_decode($rawBody, true);

		if (!$data || !isset($data['Body']['stkCallback'])) {
			$this->logger->error('[Payment] Invalid Daraja callback payload', [
				'raw' => $rawBody
			]);

			return new DataResponse(['status' => 'invalid'], Http::STATUS_BAD_REQUEST);
		}

		$payload = $data['Body']['stkCallback'];

		$checkoutRequestId = $payload['CheckoutRequestID'] ?? null;

		if (!$checkoutRequestId) {
			$this->logger->error('[Payment] Missing CheckoutRequestID in callback', [
				'payload' => $payload
			]);

			return new DataResponse(['status' => 'Missing CheckoutRequestID'], HTTP::STATUS_BAD_REQUEST);
		}

		try {

			$this->paymentService->handleDarajaCallback($payload);
		} catch (\Throwable $e) {

			$this->logger->error('[Payment] Failed processing Daraja callback', [
				'error' => $e->getMessage(),
				'callback' => $payload
			]);

			return new DataResponse(['status' => 'error'], Http::STATUS_OK);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}


	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/payment/webhook/dpo',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function dpoCallback(): DataResponse
	{
		$responseXml = '<?xml version="1.0" encoding="utf-8"?>
		<API3G>
			<Response>OK</Response>
		</API3G>';
		$rawBody = file_get_contents('php://input');

		$data = simplexml_load_string($rawBody);

		if ($data === false) {
			$this->logger->error('[DPO Callback] Invalid XML', [
				'raw' => $rawBody
			]);

			return new DataResponse($responseXml, Http::STATUS_OK);
		}

		$payload = json_decode(json_encode($data), true);
		try {
			$this->paymentService->handleDpoCallback($payload);
		} catch (\Throwable $e) {
			$this->logger->error('[Payment] Failed processing DPO callback', [
				'error' => $e->getMessage(),
				'payload' => $payload
			]);
			return new DataResponse(
				$responseXml,
				Http::STATUS_OK,
				[
					'Content-Type' => 'application/xml'
				]
			);
		}

		return new DataResponse(
			$responseXml,
			Http::STATUS_OK,
			[
				'Content-Type' => 'application/xml'
			]
		);
	}

	/**
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/payment/daraja/query',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function queryDaraja(string $reference): DataResponse
	{

		$status = $this->paymentService->queryPayment($reference);

		return new DataResponse([
			'status' => match ($status) {
				PaymentStatus::PAID => 'SUCCESS',
				PaymentStatus::FAILED => 'FAILED',
				default => 'PENDING',
			}
		], Http::STATUS_OK);
	}

	/**
	 * Check if payment can be resumed.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/resume',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function resume(
		int $signRequestId,
		string $signUuid,
	): DataResponse {

		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse([
					'success' => false,
					'error' => 'Unauthorized',
				], Http::STATUS_UNAUTHORIZED);
			}

			$uid = $user->getUID();

			$payment = $this->paymentService->resumePayment(
				$signRequestId,
				$signUuid,
				$uid,
			);

			return new DataResponse([
				'success' => true,
				'result' => $payment ? $payment->toArray() : null,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to resume payment', [
				'exception' => $e
			]);

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/payment/charge-mobile',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function chargeMobile(
		string $reference,
		string $phone,
		?string $mno = null,
		?string $country = null
	): DataResponse {

		try {

			if (trim($reference) === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Missing payment reference',
				], Http::STATUS_BAD_REQUEST);
			}

			if (trim($phone) === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Missing phone number',
				], Http::STATUS_BAD_REQUEST);
			}

			if ($mno === null || trim($mno) === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Missing mobile provider',
				], Http::STATUS_BAD_REQUEST);
			}

			if ($country === null || trim($country) === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Missing mobile provider country',
				], Http::STATUS_BAD_REQUEST);
			}

			$payment = $this->paymentService->chargeMobile(
				$reference,
				$phone,
				$mno,
				$country,
			);

			return new DataResponse([
				'success' => true,
				'result' => $payment
					? $payment->toArray()
					: null,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {

			$this->logger->error(
				'Failed to charge mobile payment',
				[
					'reference' => $reference,
					'exception' => $e,
				]
			);

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/mobile-options',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function getMobileOptions(string $reference, string $country): DataResponse
	{

		try {
			$options = $this->paymentService->getMobileOptions($reference, $country);

			return new DataResponse([
				'success' => true,
				'options' => $options
			], Http::STATUS_OK);
		} catch (\Throwable $e) {

			$this->logger->error('Failed to fetch mobile options', [
				'error' => $e->getMessage(),
				'reference' => $reference
			]);

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/test',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function test(): DataResponse
	{

		return new DataResponse([
			'test' => true
		], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/test-daraja',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function testDarajaService(): DataResponse
	{

		$result = $this->paymentService->health();
		return new DataResponse([
			'result' => $result
		], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/test-dpo',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function testDpoService(): DataResponse
	{

		$result = $this->paymentService->health();

		return new DataResponse([
			'result' => $result
		], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/test-sms',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function testSMSService(): DataResponse
	{

		$result = $this->smsService->testSendSMS();

		return new DataResponse([
			'result' => $result
		], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CORS]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/payment/test-verify',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function testQueryParam(string $providerReference): DataResponse
	{

		return new DataResponse([
			'result' => 'ok',
			'reference' => $providerReference
		], Http::STATUS_OK);
	}


	private function parseDpoXml(string $xml): array
	{
		$data = simplexml_load_string($xml);

		return [
			'reference' => (string)$data->TransToken,
			'status' => ((string)$data->Result === '000') ? 'SUCCESS' : 'FAILED',
			'raw' => json_decode(json_encode($data), true),
		];
	}
}
