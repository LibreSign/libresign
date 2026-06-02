<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use OCA\Libresign\Enum\PaymentMethod;
use OCA\Libresign\Enum\PaymentProvider;

class StartPaymentDTO {
	public function __construct(
		public string $userEmail,
		public string $signUuid,
		public int $signRequestId,
		// Only necessary for card payments
		public ?string $redirectUrl,
		public string $userId,
		public ?PaymentProvider $provider,
		public string $productCode,
		// Payment Method can either be 'card' | 'mobile'
		public PaymentMethod $paymentMethod,
		public ?string $callbackUrl = null,
		public ?string $paymentAttemptId = null,
		public ?string $phoneNumber = null,
	) {
	}
}
