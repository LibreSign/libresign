<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Enum\PaymentProvider;
use RuntimeException;
use Throwable;
use Psr\Log\LoggerInterface;

final class VerificationService
{
	public function __construct(
		private DpoProvider $dpo,
		private DarajaProvider $daraja,
		private LoggerInterface $logger,
	) {}

	/**
	 * Verify payment status by provider
	 *
	 * RULES:
	 * - Provider-driven (NOT capability-driven)
	 * - No routing logic here
	 * - No payment method logic here
	 */
	public function verifyStatus(
		PaymentProvider $provider,
		string $providerReference
	): string {

		try {

			return match ($provider) {

				/**
				 * DPO → supports direct verification via API
				 */
				PaymentProvider::DPO =>
				$this->dpo->verifyStatus($providerReference),

				/**
				 * Daraja → async (callback-driven)
				 * DB is source of truth → fallback remains PENDING
				 */
				PaymentProvider::DARAJA =>
				$this->daraja->verifyStatus($providerReference),

				default => throw new RuntimeException(
					sprintf('Unsupported provider: %s', $provider->value)
				),
			};
		} catch (Throwable $e) {

			$this->logger->error('[VerificationService] verifyStatus failed', [
				'provider' => $provider->value,
				'reference' => $providerReference,
				'error' => $e->getMessage(),
				'exception' => get_class($e),
			]);

			return 'PENDING';
		}
	}


	public function query(PaymentProvider $provider, string $reference): array
	{
		try {

			return match ($provider) {

				$this->daraja->getName() =>
				$this->daraja->query($reference),

				$this->dpo->getName() =>
				throw new RuntimeException('DPO does not support query fallback'),

				default => throw new RuntimeException(
					"Unsupported provider: {$provider->value}"
				),
			};
		} catch (Throwable $e) {

			$this->logger->error('[VerificationService] query failed', [
				'provider' => $provider->value,
				'reference' => $reference,
				'error' => $e->getMessage(),
				'exception' => get_class($e),
			]);

			throw $e; // important: don't silently swallow
		}
	}
}
