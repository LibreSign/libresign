<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCA\Libresign\Service\SignRequest\StatusService;
use OCA\Libresign\Service\SignRequest\StatusUpdatePolicy;
use OCA\Libresign\Service\FileStatusService;
use PHPUnit\Framework\TestCase;

final class StatusServiceObserverTest extends TestCase {
	private StatusService $service;

	#[\Override]
	protected function setUp(): void {
		$this->service = new StatusService(
			$this->createMock(SequentialSigningService::class),
			$this->createMock(FileStatusService::class),
			$this->createMock(StatusCacheService::class),
			$this->createMock(StatusUpdatePolicy::class),
		);
	}

	public function testObserverDraftFileRemainsDraft(): void {
		$status = $this->service->determineInitialStatus(
			signingOrder: 0,
			fileId: 1,
			fileStatus: FileStatus::DRAFT->value,
			participantRole: ParticipantRole::OBSERVER,
		);

		$this->assertSame(SignRequestStatus::DRAFT, $status);
	}

	public function testObserverActiveFileBecomesObserving(): void {
		$status = $this->service->determineInitialStatus(
			signingOrder: 0,
			fileId: 1,
			fileStatus: FileStatus::ABLE_TO_SIGN->value,
			participantRole: ParticipantRole::OBSERVER,
		);

		$this->assertSame(SignRequestStatus::OBSERVING, $status);
	}
}
