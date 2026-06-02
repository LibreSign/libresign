<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Enum\PaymentFlow;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ProviderExecutionState;
use OCA\Libresign\Service\Payment\DTO\MnoRoutingResultDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyChargeDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyResultDTO;
use OCA\Libresign\Service\Payment\DTO\SelectionDTO;
use OCA\Libresign\Service\Payment\DTO\SuggestedMnoDTO;
use RuntimeException;
use Throwable;
use Psr\Log\LoggerInterface;

final class MobileMoneyService
{
	public function __construct(
		private DarajaProvider $daraja,
		private DpoProvider $dpo,
		private LoggerInterface $logger,
		private DpoMobileOptionsService $dpoMobileOptionsService,
	) {}

	/**
	 * Initiate mobile payment flow
	 *
	 * RULES:
	 * - Daraja → executes immediately (STK push)
	 * - DPO → ALWAYS requires user confirmation (no auto execute)
	 */
	public function initiate(
		MnoRoutingResultDTO $route,
		MobileMoneyPayloadDTO $payload
	): MobileMoneyResultDTO {

		$route->validateAmount($payload->amount);
		$enrichedPayload = $payload->with(
			mno: $route->dpoMnoKey,
			country: $route->country,
		);

		switch ($route->preferredProvider) {

			/**
			 * DARAJA FLOW (auto charge)
			 */
			case PaymentProvider::DARAJA:

				try {
					return $this->daraja->initiateMobileMoney(
						$enrichedPayload
					);
				} catch (Throwable $e) {
					return $this->fail(PaymentProvider::DARAJA, null, $e);
				}

			/**
			 * DPO FLOW (token only, NO charge)
			 */
			case PaymentProvider::DPO:

				try {
					$result = $this->dpo->initiateMobileMoney(
						$enrichedPayload
					);

					/**
					 * Selection handled at service level
					 */
					$options = null;

					if ($route->requiresUserSelection()) {
						$options = $this->getMobileOptions($result->providerReference, $route->country);
					}

					$selection = new SelectionDTO($route->requiresUserSelection(), $options);
					$suggested = new SuggestedMnoDTO($route->dpoMnoKey, $route->country);

					return $result->with(
						meta: [
							...($result->meta ?? []),
							'suggested' => $suggested,
							'selection' => $selection,
							'confidence' => $route->confidence->value,
							'redirectUrl' => null,
						],
					);
				} catch (Throwable $e) {
					return $this->fail(PaymentProvider::DPO, null, $e);
				}
		}

		throw new RuntimeException('Unsupported provider in MobileMoneyService::initiate');
	}

	public function getMobileOptions(
		string $providerReference,
		string $country
	): array {
		return $this->dpoMobileOptionsService->getOptions(
			$providerReference,
			$country
		);
	}

	/**
	 * Execute mobile payment (SECOND STEP)
	 *
	 * RULES:
	 * - Daraja → NEVER allowed
	 * - DPO → Might require explicit user selection
	 */
	public function charge(
		MobileMoneyChargeDTO $dto
	): MobileMoneyResultDTO {

		/**
		 * HARD GUARD — Check if mno and country are present (required for charge)
		 * If missing → fail immediately with actionable message (no provider call)
		 */
		if (!$dto->mno || !$dto->country || !$dto->phone) {
			return new MobileMoneyResultDTO(
				providerExecutionState: ProviderExecutionState::INVALID_REQUEST,
				providerReference: $dto->providerReference,
				flow: PaymentFlow::MOBILE_DIRECT,
				provider: PaymentProvider::DPO,
				message: 'Payment execution could not continue because required provider details were missing.',
				errorCode: 'INVALID_CHARGE_REQUEST'
			);
		}

		/**
		 * Execute via provider (NO routing logic here)
		 */
		return $this->safeExecute(
			fn() => $this->dpo->charge($dto),
			PaymentProvider::DPO,
			$dto->providerReference
		);
	}


	public function testDpo(): array
	{
		return $this->dpo->test();
	}


	public function testDaraja(): array
	{
		return $this->daraja->test();
	}

	/**
	 * Centralized execution wrapper
	 */
	private function safeExecute(
		callable $fn,
		PaymentProvider $provider,
		string $reference
	): MobileMoneyResultDTO {

		try {
			return $fn();
		} catch (Throwable $e) {
			return $this->fail($provider, $reference, $e);
		}
	}

	/**
	 * Centralized failure mapper
	 */
	private function fail(
		PaymentProvider $provider,
		?string $reference,
		Throwable $e
	): MobileMoneyResultDTO {

		$this->logger->error('[MobileMoney] failure', [
			'provider' => $provider,
			'reference' => $reference,
			'error' => $e->getMessage(),
			'exception' => get_class($e),
		]);

		return new MobileMoneyResultDTO(
			providerExecutionState: ProviderExecutionState::FAILED,
			providerReference: $reference ?? '',
			flow: PaymentFlow::UNKNOWN,
			provider: $provider,
			message: $this->mapErrorMessage($e),
			errorCode: $this->mapErrorCode($e),
			meta: [
				'providerError' => [
					'message' => $e->getMessage(),
					'exception' => get_class($e),
				],
			]
		);
	}

	/**
	 * normalise error messages (safe for FE)
	 */
	private function mapErrorMessage(Throwable $e): string
	{
		$message = strtolower($e->getMessage());

		return match (true) {
			str_contains($message, 'timeout') => 'Payment request timed out',
			str_contains($message, 'network') => 'Network error occurred',
			str_contains($message, 'mno') => 'Mobile operator mismatch',
			default => 'Payment failed, please try again',
		};
	}

	/**
	 * Machine-readable error codes
	 */
	private function mapErrorCode(Throwable $e): string
	{
		$message = strtolower($e->getMessage());

		return match (true) {
			str_contains($message, 'timeout') => 'TIMEOUT',
			str_contains($message, 'network') => 'NETWORK_ERROR',
			str_contains($message, 'mno') => 'MNO_MISMATCH',
			default => 'UNKNOWN_ERROR',
		};
	}
}
