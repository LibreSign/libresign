<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\SignRequest\StatusUpdatePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusUpdatePolicyTest extends TestCase {
	private StatusUpdatePolicy $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = new StatusUpdatePolicy();
	}

	#[DataProvider('statusUpdateScenarios')]
	public function testShouldUpdateStatus(
		SignRequestStatus $currentStatus,
		SignRequestStatus $desiredStatus,
		bool $isNewSignRequest,
		bool $isStatusUpgrade,
		bool $isOrderedNumericFlow,
		bool $hasPendingLowerOrderSigners,
		bool $expected,
	): void {
		$result = $this->policy->shouldUpdateStatus(
			$currentStatus,
			$desiredStatus,
			$isNewSignRequest,
			$isStatusUpgrade,
			$isOrderedNumericFlow,
			$hasPendingLowerOrderSigners
		);

		$this->assertSame($expected, $result);
	}

	public static function statusUpdateScenarios(): array {
		$draft = SignRequestStatus::DRAFT;
		$able = SignRequestStatus::ABLE_TO_SIGN;
		$signed = SignRequestStatus::SIGNED;

		return [
			'new sign request always updates (draft->able)' => [$draft, $able, true, false, false, false, true],
			'new sign request always updates (draft->signed)' => [$draft, $signed, true, false, false, false, true],
			'upgrade updates' => [$draft, $able, false, true, false, false, true],
			'upgrade updates even when ordered' => [$draft, $signed, false, true, true, false, true],
			'no upgrade no ordered flow no update' => [$able, $draft, false, false, false, false, false],
			'ordered flow downgrade allowed when pending lower order' => [$able, $draft, false, false, true, true, true],
			'ordered flow downgrade blocked without pending lower order' => [$able, $draft, false, false, true, false, false],
			'downgrade from signed blocked' => [$signed, $draft, false, false, true, true, false],
			'no change blocked' => [$able, $able, false, false, false, false, false],
		];
	}
}
