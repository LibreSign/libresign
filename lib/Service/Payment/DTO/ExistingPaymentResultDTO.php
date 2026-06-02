<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use OCA\Libresign\Enum\PaymentFlow;
use OCA\Libresign\Enum\PaymentMethod;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ProviderExecutionState;

final class ExistingPaymentResultDTO
{
	public function __construct(
		public readonly ?\DateTimeImmutable $updatedAt,
		public readonly int $paymentId,
		public readonly int $signRequestId,
		public readonly string $signUuid,
		public readonly string $reference,
		public readonly string $status,
		public readonly PaymentProvider $provider,
		public readonly PaymentFlow $flow,
		public readonly PaymentMethod $method,
		public readonly ?string $redirectUrl,
		public readonly ?string $instructions,
		public readonly ?string $mno,
		public readonly ?string $country,
		public readonly bool $alreadyCharged,
		public readonly ProviderExecutionState $providerExecutionState,
		public readonly ?SelectedMnoDTO $selected,
		public readonly ?string $confidence,
		public readonly bool $requiresProviderSelection,
		public readonly ?array $options,
		public readonly ?string $phoneNumber,
		public readonly ?string $phoneNumberRegion,
		public readonly ?string $phoneNumberCountry,
		public readonly ?float $displayAmount,
		public readonly ?string $displayAmountFormatted,
		public readonly ?string $displayCurrency,
	) {}

	public function toArray(): array
	{
		return [
			'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
			'paymentId' => $this->paymentId,
			'signRequestId' => $this->signRequestId,
			'signUuid' => $this->signUuid,
			'reference' => $this->reference,
			'status' => $this->status,
			'provider' => $this->provider->value,
			'flow' => $this->flow->value,
			'method' => $this->method->value,
			'redirectUrl' => $this->redirectUrl,
			'instructions' => $this->instructions,
			'mno' => $this->mno,
			'country' => $this->country,
			'alreadyCharged' => $this->alreadyCharged,
			'providerExecutionState' => $this->providerExecutionState->value,
			'selected' => (
				$this->selected &&
				$this->selected->mno &&
				$this->selected->country
			)
				? $this->selected->toArray()
				: null,
			'confidence' => $this->confidence,
			'requiresProviderSelection' => $this->requiresProviderSelection,
			'options' => $this->options,
			'phoneNumber' => $this->phoneNumber,
			'phoneNumberRegion' => $this->phoneNumberRegion,
			'phoneNumberCountry' => $this->phoneNumberCountry,
			'displayAmount' => $this->displayAmount,
			'displayAmountFormatted' => $this->displayAmountFormatted,
			'displayCurrency' => $this->displayCurrency,
		];
	}
}
