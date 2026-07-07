<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use PHPUnit\Framework\TestCase;

final class ActorRoleTest extends TestCase {
	public function testSystemAdminHasAllPermissions(): void {
		$role = ActorRole::systemAdmin();

		$this->assertTrue($role->canManageSystemPolicies);
		$this->assertTrue($role->canManageGroupPolicies);
		$this->assertSame(PHP_INT_MAX, $role->manageableGroupCount);
	}

	public function testGroupAdminHasGroupManagementOnly(): void {
		$role = ActorRole::groupAdmin(3);

		$this->assertFalse($role->canManageSystemPolicies);
		$this->assertTrue($role->canManageGroupPolicies);
		$this->assertSame(3, $role->manageableGroupCount);
	}

	public function testRegularUserHasNoPermissions(): void {
		$role = ActorRole::regularUser();

		$this->assertFalse($role->canManageSystemPolicies);
		$this->assertFalse($role->canManageGroupPolicies);
		$this->assertSame(0, $role->manageableGroupCount);
	}

	public function testConstructorStoresAllFields(): void {
		$role = new ActorRole(true, false, 5);

		$this->assertTrue($role->canManageSystemPolicies);
		$this->assertFalse($role->canManageGroupPolicies);
		$this->assertSame(5, $role->manageableGroupCount);
	}

	public function testFieldsAreReadonly(): void {
		$role = ActorRole::groupAdmin(2);
		$this->assertSame(2, $role->manageableGroupCount);

		// Verify that the readonly promotion is enforced at construction time
		$reflection = new \ReflectionClass($role);
		foreach ($reflection->getProperties() as $prop) {
			$this->assertTrue($prop->isReadOnly(), "Property {$prop->getName()} should be readonly");
		}
	}
}
