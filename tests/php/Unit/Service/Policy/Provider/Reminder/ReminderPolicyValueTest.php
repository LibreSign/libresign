<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Reminder;

use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReminderPolicyValueTest extends TestCase {
	#[DataProvider('normalizeCases')]
	public function testNormalize(mixed $rawValue, array $expected): void {
		$this->assertSame($expected, ReminderPolicyValue::normalize($rawValue));
	}

	public static function normalizeCases(): array {
		return [
			'disabled payload is canonicalized to empty schedule' => [
				[
					'days_before' => 1,
					'days_between' => 0,
					'max' => 4,
					'send_timer' => '09:30',
				],
				[
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'send_timer' => '',
				],
			],
			'enabled payload with empty send time falls back to default send time' => [
				[
					'days_before' => 2,
					'days_between' => 3,
					'max' => 4,
					'send_timer' => '',
				],
				[
					'days_before' => 2,
					'days_between' => 3,
					'max' => 4,
					'send_timer' => '10:00',
				],
			],
			'enabled payload with invalid send time falls back to default send time' => [
				[
					'days_before' => 2,
					'days_between' => 3,
					'max' => 4,
					'send_timer' => 'invalid',
				],
				[
					'days_before' => 2,
					'days_between' => 3,
					'max' => 4,
					'send_timer' => '10:00',
				],
			],
		];
	}
}
