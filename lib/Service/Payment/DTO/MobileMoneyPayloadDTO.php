<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use InvalidArgumentException;

final class MobileMoneyPayloadDTO
{
	public function __construct(
		public readonly string $phone, // MUST be e164_digits
		public readonly float $amount,
		public readonly string $currency,

		// Core business context
		public readonly string $signUuid,
		public readonly int $signRequestId,
		public readonly string $userId,
		public readonly string $email,

		// URLs
		public readonly ?string $callbackUrl = null,
		public readonly ?string $redirectUrl = null,

		// Optional hints (from FE or resolution)
		public readonly ?string $mno = null,
		public readonly ?string $country = null,

		// Future-safe metadata
		public readonly array $meta = [],
	) {
		$this->validate();
	}

	/**
	 * Immutable enrichment helper.
	 *
	 * Allows provider/service-specific overrides
	 * without reconstructing DTOs manually.
	 */
	public function with(
	?string $phone = null,
	?string $callbackUrl = null,
	?string $redirectUrl = null,
	?string $mno = null,
	?string $country = null,
	?array $meta = null,
	): self {
		return new self(
			phone: $phone ?? $this->phone,

			// immutable financial values
			amount: $this->amount,
			currency: $this->currency,

			signUuid: $this->signUuid,
			signRequestId: $this->signRequestId,
			userId: $this->userId,
			email: $this->email,

			callbackUrl: $callbackUrl ?? $this->callbackUrl,
			redirectUrl: $redirectUrl ?? $this->redirectUrl,

			mno: $mno ?? $this->mno,
			country: $country ?? $this->country,

			meta: $meta ?? $this->meta,
		);
	}

	/**
	 * Validation (STRICT)
	 */
	private function validate(): void
	{
		if ($this->phone === '') {
			throw new InvalidArgumentException('phone is required');
		}

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
	}

	/**
	 * Domain helpers
	 */

	public function hasMno(): bool
	{
		return $this->mno !== null && $this->mno !== '';
	}

	public function hasCountry(): bool
	{
		return $this->country !== null && $this->country !== '';
	}

	/**
	 * Provider adapters can safely extract base payload
	 */
	public function toArray(): array
	{
		return [
			'phone' => $this->phone,
			'amount' => $this->amount,
			'currency' => $this->currency,
			'signUuid' => $this->signUuid,
			'signRequestId' => $this->signRequestId,
			'userId' => $this->userId,
			'email' => $this->email,
			'callbackUrl' => $this->callbackUrl,
			'redirectUrl' => $this->redirectUrl,
			'mno' => $this->mno,
			'country' => $this->country,
			'meta' => $this->meta,
		];
	}

	public function __toString(): string
	{
		return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
	}
}
