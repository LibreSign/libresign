<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Enum\PaymentFlow;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ProviderExecutionState;
use OCA\Libresign\Enum\ResolutionConfidence;
use OCA\Libresign\Service\Payment\DTO\CardPaymentPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\CardPaymentResultDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyChargeDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyResultDTO;
use OCA\Libresign\Service\Payment\Interfaces\ICardProvider;
use OCA\Libresign\Service\Payment\Interfaces\IMobileMoneyProvider;
use OCA\Libresign\Service\Payment\Interfaces\IVerifiableProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class DpoProvider implements IMobileMoneyProvider, ICardProvider, IVerifiableProvider
{
	private DpoPaymentService $dpo;
	private LoggerInterface $logger;

	public function __construct(
		DpoPaymentService $dpo,
		LoggerInterface $logger,
	) {
		$this->dpo = $dpo;
		$this->logger = $logger;
	}


	public function getName(): PaymentProvider
	{
		return PaymentProvider::DPO;
	}

	/**
	 * MOBILE MONEY INITIATE
	 *
	 * PURE ADAPTER
	 * - No detection
	 * - No fallback
	 * - No intelligence
	 */
	public function initiateMobileMoney(MobileMoneyPayloadDTO $payload): MobileMoneyResultDTO
	{
		try {
			$result = $this->dpo->createToken(
				$payload->email,
				$payload->signUuid,
				$payload->amount,
				$payload->redirectUrl ?? '',
				$payload->currency,
				'mobile',
				'MO',
				$payload->country // already resolved upstream
			);

			if (!isset($result['reference'])) {
				throw new RuntimeException('DPO initiate missing reference');
			}

			return new MobileMoneyResultDTO(
				providerExecutionState: ProviderExecutionState::REQUIRES_SELECTION,
				providerReference: $result['reference'],
				flow: PaymentFlow::MOBILE_DIRECT,
				provider: $this->getName(),
				message: 'Awaiting user confirmation',
				meta: [
					// keep structure to avoid FE regressions
					'selection' => [
						'options' => null,
						'suggestedMno' => $payload->mno,
						'confidence' => ResolutionConfidence::UNKNOWN->value,
						'country' => $payload->country,
					],
					'redirectUrl' => null,
					'providerPayload' => [
						'initiation' => is_array($result['raw'] ?? null)
							? $result['raw']
							: [],
					],
				]
			);
		} catch (Throwable $e) {
			$this->logger->error('[DPO] initiate failed', [
				'error' => $e->getMessage()
			]);

			throw $e;
		}
	}

	/**
	 * MOBILE MONEY CHARGE
	 */
	public function charge(MobileMoneyChargeDTO $payload): MobileMoneyResultDTO
	{
		try {
			$response = $this->dpo->chargeTokenMobile(
				$payload->providerReference,
				$payload->phone,
				strtolower($payload->mno),
				strtolower($payload->country)
			);

			$providerExecutionState = ($response['status'] ?? 'FAILED') === 'ACCEPTED'
				? ProviderExecutionState::EXECUTING
				: ProviderExecutionState::FAILED;

			return new MobileMoneyResultDTO(
				providerExecutionState: $providerExecutionState,
				providerReference: $payload->providerReference,
				flow: PaymentFlow::MOBILE_DIRECT,
				provider: $this->getName(),
				message: $providerExecutionState === ProviderExecutionState::EXECUTING
					? 'Payment prompt sent'
					: ($response['error'] ?? 'Payment failed'),
				errorCode: $providerExecutionState === ProviderExecutionState::FAILED
					? ($response['code'] ?? 'CHARGE_FAILED')
					: null,
				meta: [
					'instructions' => $response['instructions'] ?? null,
					'providerPayload' => [
						'charge' => is_array($response['raw'] ?? null)
							? $response['raw']
							: [],
					],
				],
			);
		} catch (Throwable $e) {
			$this->logger->error('[DPO] charge failed', [
				'reference' => $payload->providerReference,
				'error' => $e->getMessage()
			]);

			return new MobileMoneyResultDTO(
				providerExecutionState: ProviderExecutionState::FAILED,
				providerReference: $payload->providerReference,
				flow: PaymentFlow::MOBILE_DIRECT,
				provider: $this->getName(),
				message: 'Payment failed',
				errorCode: 'CHARGE_FAILED'
			);
		}
	}

	/**
	 * CARD INITIATE
	 */
	public function initiateCard(CardPaymentPayloadDTO $payload): CardPaymentResultDTO
	{
		$result = $this->dpo->createToken(
			$payload->email,
			$payload->signUuid,
			$payload->amount,
			$payload->redirectUrl,
			$payload->currency,
			'card',
			'CC',
			null
		);

		return new CardPaymentResultDTO(
			providerExecutionState: ProviderExecutionState::EXECUTING,
			providerReference: $result['reference'],
			redirectUrl: $result['paymentUrl'],
			provider: $this->getName(),
			flow: PaymentFlow::REDIRECT,
			message: 'Redirect user to payment page',
			meta: [
				'providerPayload' => [
					'initiation' => is_array($result['raw'] ?? null)
						? $result['raw']
						: [],
				],
			]
		);
	}

	/**
	 * Transitional:
	 * Used by VerificationService / MobileMoneyService
	 * Will be removed once fully abstracted into service layer
	 */
	public function verifyStatus(string $reference): string
	{
		try {

			$result = $this->dpo->verifyToken($reference);

			return $result->status;
		} catch (Throwable $e) {

			$this->logger->warning('[DPO] verifyStatus failed', [
				'reference' => $reference,
				'error' => $e->getMessage(),
			]);

			/**
			 * IMPORTANT:
			 * Transport/provider verification failures
			 * are NOT terminal payment failures.
			 *
			 * Returning PENDING allows:
			 * - background retries
			 * - webhook reconciliation
			 * - eventual consistency recovery
			 */
			return 'PENDING';
		}
	}

	public function getMobileOptions(string $reference): array
	{
		return $this->dpo->getMobilePaymentOptions($reference);
	}

	/**
	 * TEMP DEBUG
	 */
	public function test(): array
	{
		return $this->dpo->testDpo();
	}

	public function query(string $reference): array
	{
		throw new RuntimeException($this->getName()->value . ' does not support query fallback');
	}
}
