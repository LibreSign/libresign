<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use OCP\IAppConfig;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase {
	private static ?InMemoryAppConfig $appConfig = null;

	public static function getMockAppConfig(): IAppConfig {
		return self::$appConfig ??= new InMemoryAppConfig();
	}

	public static function getMockAppConfigWithReset(): IAppConfig {
		$appConfig = self::getMockAppConfig();
		if ($appConfig instanceof InMemoryAppConfig) {
			$appConfig->reset();
		}
		return $appConfig;
	}

	/**
	 * @param array<int, mixed> $args
	 */
	public static function invokePrivate(object $object, string $methodName, array $args = []): mixed {
		$method = new \ReflectionMethod($object, $methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}
}
