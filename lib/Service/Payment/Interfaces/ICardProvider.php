<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\Interfaces;

use OCA\Libresign\Service\Payment\DTO\CardPaymentPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\CardPaymentResultDTO;

interface ICardProvider extends IProvider
{
	/**
	 * Initiate card payment (redirect flow)
	 */
	public function initiateCard(CardPaymentPayloadDTO $payload): CardPaymentResultDTO;
}
