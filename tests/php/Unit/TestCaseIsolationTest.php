<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use PHPUnit\Framework\Attributes\Depends;

final class TestCaseIsolationTest extends TestCase {
	public function testInMemoryAppConfigCanBeWrittenWithinTest(): string {
		$appConfig = self::getMockAppConfig();
		$appConfig->setValueString('libresign-test', 'key', 'value');

		self::assertSame('value', $appConfig->getValueString('libresign-test', 'key'));

		return 'written';
	}

	#[Depends('testInMemoryAppConfigCanBeWrittenWithinTest')]
	public function testInMemoryAppConfigDoesNotLeakFromPreviousTest(string $state): void {
		self::assertSame('written', $state);
		self::assertFalse(self::getMockAppConfig()->hasKey('libresign-test', 'key'));
	}
}
