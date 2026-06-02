<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use OCA\Libresign\Enum\PaymentFlow;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ProviderExecutionState;

final class MobileMoneyResultDTO
{
	public function __construct(
		public readonly ProviderExecutionState $providerExecutionState,
		public readonly string $providerReference,
		public readonly PaymentFlow $flow,
		public readonly ?PaymentProvider $provider, // executing provider (source of truth)
		public readonly ?string $message = null,
		/**
		 * Structured extension point
		 * NEVER arbitrary — keep keys predictable
		 */
		public readonly ?array $meta = [],
		/**
		 * Optional normalised error code
		 */
		public readonly ?string $errorCode = null,
	) {}

	/**
	 * Domain helpers
	 */
	public function isInitiated(): bool
	{
		return $this->providerExecutionState->isInitiated();
	}

	public function isExecuting(): bool
	{
		return $this->providerExecutionState->isExecuting();
	}

	public function requiresSelection(): bool
	{
		return $this->providerExecutionState->requiresSelection();
	}

	public function isReconciling(): bool
	{
		return $this->providerExecutionState->isReconciling();
	}

	public function isFailed(): bool
	{
		return $this->providerExecutionState->isFailed();
	}

	public function isSuccess(): bool
	{
		return $this->providerExecutionState->isSuccess();
	}

	/**
	 * Serialization
	 */
	public function toArray(): array
	{
		return [
			'providerExecutionState' =>
			$this->providerExecutionState->value,

			'providerReference' =>
			$this->providerReference,

			'flow' =>
			$this->flow->value,

			'provider' =>
			$this->provider?->value,

			'message' =>
			$this->message,

			'meta' =>
			$this->meta,

			'errorCode' =>
			$this->errorCode,
		];
	}

	public function with(
		?ProviderExecutionState $providerExecutionState = null,
		?string $providerReference = null,
		?PaymentFlow $flow = null,
		?PaymentProvider $provider = null,
		?string $message = null,
		?array $meta = null,
		?string $errorCode = null,
	): self {
		return new self(
			providerExecutionState: $providerExecutionState ?? $this->providerExecutionState,
			providerReference: $providerReference ?? $this->providerReference,
			flow: $flow ?? $this->flow,
			provider: $provider ?? $this->provider,
			message: $message ?? $this->message,
			meta: $meta ?? $this->meta,
			errorCode: $errorCode ?? $this->errorCode,
		);
	}

	public function __toString(): string
	{
		return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
	}
}
