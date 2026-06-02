<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Service\Payment\DTO\CardPaymentPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\CardPaymentResultDTO;
use RuntimeException;
use Throwable;
use Psr\Log\LoggerInterface;

final class CardService
{
	public function __construct(
		private DpoProvider $dpo,
		private LoggerInterface $logger,
	) {}

	/**
	 * Initiate card payment (redirect flow)
	 *
	 * RULES:
	 * - Only DPO supports card for now
	 * - Always redirect-based
	 */
	public function initiateCard(
		CardPaymentPayloadDTO $payload
	): CardPaymentResultDTO {

		try {

			/**
			 * Validate redirect URL (critical for FE safety)
			 */
			if (!filter_var($payload->redirectUrl, FILTER_VALIDATE_URL)) {
				throw new RuntimeException('Invalid redirect URL');
			}

			/**
			 * Delegate to provider (PURE DTO)
			 */
			return $this->dpo->initiateCard($payload);


		} catch (Throwable $e) {

			$this->logger->error('[CardService] initiate failed', [
				'error' => $e->getMessage(),
				'exception' => get_class($e),
				'signUuid' => $payload->signUuid,
				'signRequestId' => $payload->signRequestId,
				'currency' => $payload->currency,
			]);

			throw new RuntimeException(
				'Card payment initiation failed',
				previous: $e
			);
		}
	}
}
