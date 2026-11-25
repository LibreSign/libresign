<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\External\SessionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalSigningSessionStateTest extends TestCase {
	/** @return array<string, array{string, string, bool}> */
	public static function transitions(): array {
		return [
			'preparing -> interaction' => ['preparing', 'interaction_required', true],
			'preparing -> ready' => ['preparing', 'ready_to_sign', true],
			'authorization async' => ['authorization_pending', 'ready_to_sign', true],
			'ready -> signing' => ['ready_to_sign', 'signing_pending', true],
			'signing -> uncertain' => ['signing_pending', 'uncertain', true],
			'uncertain -> signature' => ['uncertain', 'signature_received', true],
			'uncertain cannot auto retry' => ['uncertain', 'ready_to_sign', false],
			'pending cannot return ready' => ['signing_pending', 'ready_to_sign', false],
			'pending cannot complete without signature' => ['signing_pending', 'completed', false],
			'signature -> completing' => ['signature_received', 'completing', true],
			'completing -> complete' => ['completing', 'completed', true],
			'publication cannot restart through uncertain' => ['completing', 'uncertain', false],
			'expired signature can be purged' => ['signature_received', 'expired', true],
			'completed is terminal' => ['completed', 'signing_pending', false],
			'expired is terminal' => ['expired', 'preparing', false],
			'failure is terminal' => ['failed', 'ready_to_sign', false],
			'rejection is terminal' => ['rejected', 'ready_to_sign', false],
			'no self-transition' => ['ready_to_sign', 'ready_to_sign', false],
		];
	}

	#[DataProvider('transitions')]
	public function testExplicitTransitionMatrix(string $from, string $to, bool $allowed): void {
		$this->assertSame($allowed, SessionState::from($from)->allows(SessionState::from($to)));
	}
}
