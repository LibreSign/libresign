<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use OCA\Libresign\Enum\ProviderExecutionState;

final class PaymentMetadataDTO
{
	public function __construct(
		public readonly ?\DateTimeImmutable $updatedAt,
		public readonly string $preferredProvider,
		public readonly string $executedProvider,
		public readonly string $flow,
		public readonly string $method,
		public readonly ?string $redirectUrl,
		public readonly SelectedMnoDTO $selected,
		public readonly SuggestedMnoDTO $suggested,
		public readonly SelectionDTO $selection,
		public readonly ?string $confidence,

		/**
		 * Indicates whether provider-side charge initiation
		 * has already occurred.
		 *
		 * IMPORTANT:
		 * - This does NOT mean payment succeeded
		 * - This does NOT mean settlement completed
		 * - Used primarily for deferred mobile flows (e.g. DPO)
		 *
		 * Examples:
		 * - Daraja STK push → true immediately after initiation
		 * - DPO mobile_direct → false until chargeMobile()
		 */
		public readonly bool $alreadyCharged,
		public readonly ?string $instructions,
		public readonly ?ProviderExecutionState $providerExecutionState,
		public readonly ProviderPayloadDTO $providerPayload,
		public readonly array $context = [], // for extra data like region, carrier
		public readonly ?array $providerError = null,
	) {}

	public function toArray(): array
	{
		return [
			'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
			'preferredProvider' => $this->preferredProvider,
			'executedProvider' => $this->executedProvider,
			'flow' => $this->flow,
			'method' => $this->method,
			'redirectUrl' => $this->redirectUrl,
			'selected' => $this->selected->toArray(),
			'suggested' => $this->suggested->toArray(),
			'selection' => $this->selection->toArray(),
			'confidence' => $this->confidence,
			'alreadyCharged' => $this->alreadyCharged,
			'instructions' => $this->instructions,
			'providerExecutionState' => $this->providerExecutionState,
			'providerPayload' => $this->providerPayload->toArray(),
			'context' => $this->context,
			'providerError' => $this->providerError,
		];
	}

	public static function fromArray(array $data): self
	{
		return new self(
			updatedAt: self::parseDate(
				$data['updatedAt'] ?? null
			),

			preferredProvider: is_string(
				$data['preferredProvider'] ?? null
			)
				? $data['preferredProvider']
				: '',

			executedProvider: is_string(
				$data['executedProvider'] ?? null
			)
				? $data['executedProvider']
				: '',

			flow: is_string(
				$data['flow'] ?? null
			)
				? $data['flow']
				: '',

			method: is_string(
				$data['method'] ?? null
			)
				? $data['method']
				: '',

			redirectUrl: is_string(
				$data['redirectUrl'] ?? null
			)
				? $data['redirectUrl']
				: null,

			selected: SelectedMnoDTO::fromArray(
				is_array($data['selected'] ?? null)
					? $data['selected']
					: []
			),

			suggested: SuggestedMnoDTO::fromArray(
				is_array($data['suggested'] ?? null)
					? $data['suggested']
					: []
			),

			selection: SelectionDTO::fromArray(
				is_array($data['selection'] ?? null)
					? $data['selection']
					: []
			),

			confidence: is_string(
				$data['confidence'] ?? null
			)
				? $data['confidence']
				: null,

			alreadyCharged: is_bool(
				$data['alreadyCharged'] ?? null
			)
				? $data['alreadyCharged']
				: false,

			instructions: is_string(
				$data['instructions'] ?? null
			)
				? $data['instructions']
				: null,

			providerExecutionState: is_string(
               $data['providerExecutionState'] ?? null
			)
			    ? ProviderExecutionState::from($data['providerExecutionState'])
				: null,

			providerPayload: ProviderPayloadDTO::fromArray(
				is_array($data['providerPayload'] ?? null)
					? $data['providerPayload']
					: [],
			),

			context: is_array(
				$data['context'] ?? null
			)
				? $data['context']
				: [],

			providerError: is_array(
				$data['providerError'] ?? null
			)
				? $data['providerError']
				: null,
		);
	}


	public function with(
		?\DateTimeImmutable $updatedAt = null,
		?string $preferredProvider = null,
		?string $executedProvider = null,
		?string $flow = null,
		?string $method = null,
		?string $redirectUrl = null,
		?SelectedMnoDTO $selected = null,
		?SuggestedMnoDTO $suggested = null,
		?SelectionDTO $selection = null,
		?string $confidence = null,
		?bool $alreadyCharged = null,
		?string $instructions = null,
		?ProviderExecutionState $providerExecutionState = null,
		?ProviderPayloadDTO $providerPayload = null,
		?array $context = null,
		?array $providerError = null,
	): self {
		return new self(
			updatedAt: $updatedAt ?? $this->updatedAt,
			preferredProvider: $preferredProvider ?? $this->preferredProvider,
			executedProvider: $executedProvider ?? $this->executedProvider,
			flow: $flow ?? $this->flow,
			method: $method ?? $this->method,
			redirectUrl: $redirectUrl ?? $this->redirectUrl,
			selected: $selected ?? $this->selected,
			suggested: $suggested ?? $this->suggested,
			selection: $selection ?? $this->selection,
			confidence: $confidence ?? $this->confidence,
			alreadyCharged: $alreadyCharged ?? $this->alreadyCharged,
			instructions: $instructions ?? $this->instructions,
			providerExecutionState: $providerExecutionStatus ?? $this->providerExecutionState,
			providerPayload: $providerPayload ?? $this->providerPayload,
			context: $context ?? $this->context,
			providerError: $providerError ?? $this->providerError,
		);
	}


	public static function parseDate(
		mixed $value,
	): ?\DateTimeImmutable {

		if (!is_string($value) || $value === '') {
			return null;
		}

		try {
			return new \DateTimeImmutable($value);
		} catch (\Throwable) {
			return null;
		}
	}
}
