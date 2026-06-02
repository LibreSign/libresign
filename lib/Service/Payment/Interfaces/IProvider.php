<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\Interfaces;

use OCA\Libresign\Enum\PaymentProvider;

interface IProvider
{
	/**
	 * Unique provider identifier
	 *
	 * Example:
	 * - dpo
	 * - daraja
	 */
	public function getName(): PaymentProvider;
}
