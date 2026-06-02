<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\DTO;

final class DashboardStatsDTO
{

	public function __construct(
		public readonly int $totalDocuments,
		public readonly int $pendingDocuments,
		public readonly int $completedDocuments,
		public readonly int $draftDocuments,
	) {}

	public function toArray(): array
	{
		return [
			'totalDocuments' => $this->totalDocuments,
			'pendingDocuments' => $this->pendingDocuments,
			'completedDocuments' => $this->completedDocuments,
			'draftDocuments' => $this->draftDocuments,
		];
	}
}
