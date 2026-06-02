<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class ProviderPayloadDTO
{
	public function __construct(
		public readonly ?array $initiation = null,
		public readonly ?array $charge = null,
		public readonly ?array $callback = null,
		public readonly ?array $query = null,
		public readonly ?array $verification = null,
	) {}

	public function toArray(): array
	{
		return [
			'initiation' => $this->initiation,
			'charge' => $this->charge,
			'callback' => $this->callback,
			'query' => $this->query,
			'verification' => $this->verification,
		];
	}

	public static function fromArray(array $data): self
	{
		return new self(
			initiation: is_array($data['initiation'] ?? null)
				? $data['initiation']
				: null,

			charge: is_array($data['charge'] ?? null)
				? $data['charge']
				: null,

			callback: is_array($data['callback'] ?? null)
				? $data['callback']
				: null,

			query: is_array($data['query'] ?? null)
				? $data['query']
				: null,

			verification: is_array($data['verification'] ?? null)
				? $data['verification']
				: null,
		);
	}

	public function with(
		?array $initiation = null,
		?array $charge = null,
		?array $callback = null,
		?array $query = null,
		?array $verification = null,
	): self {
		return new self(
			initiation: $initiation ?? $this->initiation,
			charge: $charge ?? $this->charge,
			callback: $callback ?? $this->callback,
			query: $query ?? $this->query,
			verification: $verification ?? $this->verification,
		);
	}

	public function withInitiation(array $payload): self
	{
		return $this->with(
			initiation: $payload
		);
	}

	public function withCharge(array $payload): self
	{
		return $this->with(
			charge: $payload
		);
	}

	public function withCallback(array $payload): self
	{
		return $this->with(
			callback: $payload
		);
	}

	public function withQuery(array $payload): self
	{
		return $this->with(
			query: $payload
		);
	}

	public function withVerification(array $payload): self
	{
		return $this->with(
			verification: $payload
		);
	}
}
