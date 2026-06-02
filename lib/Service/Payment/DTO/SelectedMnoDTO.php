<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class SelectedMnoDTO
{
	public function __construct(
		public readonly ?string $mno,
		public readonly ?string $country,
	) {}

	public function toArray(): array
	{
		return [
			'mno' => $this->mno,
			'country' => $this->country,
		];
	}

	public static function fromArray(array $data): self
	{
		return new self(
			mno: is_string($data['mno'] ?? null)
				? $data['mno']
				: null,

			country: is_string($data['country'] ?? null)
				? $data['country']
				: null,
		);
	}
}
