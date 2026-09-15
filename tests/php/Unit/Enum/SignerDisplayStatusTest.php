<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Enum;

use OCA\Libresign\Enum\SignerDisplayStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignerDisplayStatusTest extends TestCase {
	#[DataProvider('mapping')]
	public function testEveryWorkflowStateHasAPresentation(SignRequestStatus $status, SignerDisplayStatus $expected): void {
		$this->assertSame($expected, SignerDisplayStatus::fromSignRequestStatus($status));
	}

	public static function mapping(): array {
		return [
			[SignRequestStatus::DRAFT, SignerDisplayStatus::DRAFT],
			[SignRequestStatus::ABLE_TO_SIGN, SignerDisplayStatus::READY_TO_SIGN],
			[SignRequestStatus::SIGNED, SignerDisplayStatus::SIGNED],
			[SignRequestStatus::REJECTED, SignerDisplayStatus::REJECTED],
		];
	}

	#[DataProvider('labels')]
	public function testLabels(SignerDisplayStatus $displayStatus, string $expected): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->assertSame($expected, $displayStatus->getLabel($l10n));
	}

	public static function labels(): array {
		return [
			[SignerDisplayStatus::DRAFT, 'Draft'],
			[SignerDisplayStatus::READY_TO_SIGN, 'Ready to sign'],
			[SignerDisplayStatus::SIGNED, 'Signed'],
			[SignerDisplayStatus::REJECTED, 'Rejected'],
			[SignerDisplayStatus::NOT_SIGNED, 'Not signed'],
		];
	}

	public function testTheApiValuesAreTheOnesOfTheContract(): void {
		$this->assertSame(
			['draft', 'ready_to_sign', 'signed', 'rejected', 'not_signed'],
			array_map(static fn (SignerDisplayStatus $status): string => $status->value, SignerDisplayStatus::cases()),
		);
	}
}
