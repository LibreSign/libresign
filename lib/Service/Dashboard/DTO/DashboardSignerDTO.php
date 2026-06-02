<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\DTO;

final class DashboardSignerDTO {

	public function __construct(
		public readonly ?string $displayName,
		public readonly string $status,
		public readonly bool $canRemind,
		public readonly bool $canRequestSignature,
		public readonly bool $me,
	) {}

	public function toArray(): array {
		return [
			'displayName' => $this->displayName,
			'status' => $this->status,
			'canRemind' => $this->canRemind,
			'canRequestSignature' => $this->canRequestSignature,
			'me' => $this->me,
		];
	}
}
