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
	 * @param object|class-string $object
	 * @param array<int, mixed> $args
	 */
	public static function invokePrivate(object|string $object, string $memberName, array $args = []): mixed {
		$className = is_string($object) ? $object : $object::class;
		$reflection = new \ReflectionClass($className);

		if ($reflection->hasMethod($memberName)) {
			return $reflection->getMethod($memberName)->invokeArgs($object, $args);
		}

		if ($reflection->hasProperty($memberName)) {
			$property = $reflection->getProperty($memberName);
			if ($args !== []) {
				$value = array_pop($args);
				if ($property->isStatic()) {
					$property->setValue(null, $value);
				} else {
					$property->setValue($object, $value);
				}
			}

			return $property->isStatic()
				? $property->getValue()
				: $property->getValue($object);
		}

		if ($reflection->hasConstant($memberName)) {
			return $reflection->getConstant($memberName);
		}

		return false;
	}
}
