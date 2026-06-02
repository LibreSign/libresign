<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class SelectionDTO
{
	public function __construct(
		public readonly bool $required,
		public readonly array $options, // array of normalised option arrays
		public readonly ?int $refreshedAt = null,
	) {}

	public function toArray(): array
	{
		return [
			'required' => $this->required,
			'options' => $this->options,
			'refreshedAt' => $this->refreshedAt,
		];
	}

	public static function fromArray(array $data): self
	{
		return new self(
			required: is_bool($data['required'] ?? null)
				? $data['required']
				: false,

			options: is_array($data['options'] ?? null)
				? $data['options']
				: [],

			refreshedAt: is_int($data['refreshedAt'] ?? null)
				? $data['refreshedAt']
				: null,
		);
	}
}
