<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use OCA\Libresign\Enum\PaymentFlow;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ProviderExecutionState;

final class CardPaymentResultDTO
{
	public function __construct(
		public readonly ProviderExecutionState $providerExecutionState,
		public readonly string $providerReference,
		public readonly string $redirectUrl,
		public readonly PaymentProvider $provider,
		public readonly PaymentFlow $flow,
		public readonly ?string $message = null,
		public readonly array $meta = [],
	) {}

	public function toArray(): array
	{
		return [
            'state' => $this->providerExecutionState,
			'providerReference' => $this->providerReference,
			'redirectUrl' => $this->redirectUrl,
			'provider' => $this->provider,
			'message' => $this->message,
			'meta' => $this->meta,
		];
	}

	public function with(
		?ProviderExecutionState $providerExecutionState = null,
		?string $providerReference = null,
		?string $redirectUrl = null,
		?PaymentProvider $provider = null,
		?PaymentFlow $flow = null,
		?string $message = null,
		?array $meta = null,
	): self {
		return new self(
			providerExecutionState: $providerExecutionState ?? $this->providerExecutionState,
			providerReference: $providerReference ?? $this->providerReference,
			redirectUrl: $redirectUrl ?? $this->redirectUrl,
			provider: $provider ?? $this->provider,
			flow: $flow ?? $this->flow,
			message: $message ?? $this->message,
			meta: $meta ?? $this->meta,
		);
	}
}
