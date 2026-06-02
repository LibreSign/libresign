<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use InvalidArgumentException;

final class CardPaymentPayloadDTO
{
	public function __construct(
		public readonly float $amount,
		public readonly string $currency,

		// Business context
		public readonly string $signUuid,
		public readonly int $signRequestId,
		public readonly string $userId,
		public readonly string $email,

		// Required for redirect flows
		public readonly string $redirectUrl,
		public readonly ?string $callbackUrl = null,

		// Future-safe metadata
		public readonly array $meta = [],
	) {
		$this->validate();
	}

	private function validate(): void
	{
		if ($this->amount <= 0) {
			throw new InvalidArgumentException('amount must be greater than 0');
		}

		if ($this->currency === '') {
			throw new InvalidArgumentException('currency is required');
		}

		if ($this->signUuid === '') {
			throw new InvalidArgumentException('signUuid is required');
		}

		if ($this->signRequestId <= 0) {
			throw new InvalidArgumentException('signRequestId is invalid');
		}

		if ($this->userId === '') {
			throw new InvalidArgumentException('userId is required');
		}

		if ($this->email === '') {
			throw new InvalidArgumentException('email is required');
		}

		if ($this->redirectUrl === '') {
			throw new InvalidArgumentException('redirectUrl is required for card payments');
		}
	}

	public function toArray(): array
	{
		return [
			'amount' => $this->amount,
			'currency' => $this->currency,
			'signUuid' => $this->signUuid,
			'signRequestId' => $this->signRequestId,
			'userId' => $this->userId,
			'email' => $this->email,
			'redirectUrl' => $this->redirectUrl,
			'callbackUrl' => $this->callbackUrl,
			'meta' => $this->meta,
		];
	}
}
