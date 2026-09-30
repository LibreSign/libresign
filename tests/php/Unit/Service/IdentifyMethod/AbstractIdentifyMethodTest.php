<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod;

use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use PHPUnit\Framework\TestCase;

final class AbstractIdentifyMethodTest extends TestCase {
	public function testGetDefaultSettingsUsesFriendlyNameGetter(): void {
		$identifyService = $this->createMock(IdentifyService::class);

		$method = new class($identifyService) extends AbstractIdentifyMethod {
			#[\Override]
			public function getFriendlyName(): string {
				return 'Telegram';
			}
		};

		$settings = $method->getDefaultSettings();

		self::assertSame('Telegram', $settings['friendly_name']);
	}
}
