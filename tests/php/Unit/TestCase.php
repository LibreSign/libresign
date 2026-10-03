<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase {
	/**
	 * @param array<int, mixed> $args
	 */
	public static function invokePrivate(object $object, string $methodName, array $args = []): mixed {
		$method = new \ReflectionMethod($object, $methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}
}
