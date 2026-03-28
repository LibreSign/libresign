<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\PermissionSetBinding;
use PHPUnit\Framework\TestCase;

final class PermissionSetBindingTest extends TestCase {
	public function testDefaultValuesAreEmpty(): void {
		$binding = new PermissionSetBinding();

		$this->assertSame('', $binding->getTargetType());
		$this->assertSame('', $binding->getTargetId());
	}

	public function testSetCreatedAtAcceptsString(): void {
		$binding = new PermissionSetBinding();

		$binding->setCreatedAt('2026-03-17 12:00:00');

		$this->assertInstanceOf(\DateTime::class, $binding->getCreatedAt());
		$this->assertSame('2026-03-17 12:00:00', $binding->getCreatedAt()->format('Y-m-d H:i:s'));
	}

	public function testBindingStoresPermissionSetAndTarget(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(42);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$this->assertSame(42, $binding->getPermissionSetId());
		$this->assertSame('group', $binding->getTargetType());
		$this->assertSame('finance', $binding->getTargetId());
	}
}
