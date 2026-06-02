<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\Interfaces;

use OCA\Libresign\Service\Payment\DTO\MobileMoneyChargeDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyPayloadDTO;
use OCA\Libresign\Service\Payment\DTO\MobileMoneyResultDTO;

interface IMobileMoneyProvider extends IProvider
{
	/**
	 * Initiate mobile payment flow
	 *
	 * - Daraja → triggers STK immediately
	 * - DPO → creates token only
	 */
	public function initiateMobileMoney(MobileMoneyPayloadDTO $payload): MobileMoneyResultDTO;

	/**
	 * Execute mobile payment (SECOND STEP)
	 *
	 * - Only applicable to providers that support deferred execution (DPO)
	 * - Must be deterministic (no internal detection)
	 */
	public function charge(MobileMoneyChargeDTO $payload): MobileMoneyResultDTO;
}
