<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Reminder;

use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicy;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicyValue;
use PHPUnit\Framework\TestCase;

final class ReminderPolicyTest extends TestCase {
	public function testProviderBuildsReminderDefinition(): void {
		$provider = new ReminderPolicy();
		$this->assertSame([ReminderPolicy::KEY], $provider->keys());

		$definition = $provider->get(ReminderPolicy::KEY);
		$this->assertSame(ReminderPolicy::KEY, $definition->key());
		$this->assertSame(
			ReminderPolicyValue::encode(ReminderPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
	}

	public function testProviderNormalizesReminderPayload(): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);

		$normalized = $definition->normalizeValue([
			'days_before' => '2',
			'days_between' => 3,
			'max' => '4',
			'send_timer' => '09:45',
		]);

		$this->assertSame(
			'{"days_before":2,"days_between":3,"max":4,"send_timer":"09:45"}',
			$normalized,
		);
	}

	public function testProviderNormalizesInvalidReminderPayload(): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);

		$normalized = $definition->normalizeValue([
			'days_before' => -5,
			'days_between' => 'not-number',
			'max' => -1,
			'send_timer' => 'invalid',
		]);

		$this->assertSame(
			'{"days_before":0,"days_between":0,"max":0,"send_timer":"10:00"}',
			$normalized,
		);
	}
}
