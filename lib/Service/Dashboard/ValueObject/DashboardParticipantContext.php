<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\ValueObject;


use OCA\Libresign\Db\SignRequest;

final class DashboardParticipantContext {
	public function __construct(
		public readonly ?SignRequest $signRequest,
		public readonly bool $isSigner,
		public readonly bool $canSignNow,
		public readonly bool $hasSigned,
		public readonly bool $isBlockedBySequence,
		public readonly ?int $signingOrder,
		public readonly ?string $displayName,
	) {}
}
