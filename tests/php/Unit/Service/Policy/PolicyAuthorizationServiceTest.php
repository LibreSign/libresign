<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyAuthorizationServiceTest extends TestCase {
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyAuthorizationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->service = new PolicyAuthorizationService(
			$this->groupManager,
			$this->subAdmin,
		);
	}

	public function testCanUserManageGroupPoliciesReturnsFalseForNullUser(): void {
		$result = $this->service->canUserManageGroupPolicies(null);

		$this->assertFalse($result);
	}

	public function testCanUserManageGroupPoliciesReturnsTrueForInstanceAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin-user');

		$this->groupManager->method('isAdmin')
			->with('admin-user')
			->willReturn(true);

		$result = $this->service->canUserManageGroupPolicies($user);

		$this->assertTrue($result);
	}

	public function testCanUserManageGroupPoliciesReturnsTrueForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);

		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$result = $this->service->canUserManageGroupPolicies($user);

		$this->assertTrue($result);
	}

	public function testCanUserManageGroupPoliciesReturnsFalseForRegularUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('regular-user');

		$this->groupManager->method('isAdmin')
			->with('regular-user')
			->willReturn(false);

		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(false);

		$result = $this->service->canUserManageGroupPolicies($user);

		$this->assertFalse($result);
	}

	public function testGetManageablePolicyGroupIdsReturnsEmptyForNullUser(): void {
		$result = $this->service->getManageablePolicyGroupIds(null);

		$this->assertSame([], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsEmptyForInstanceAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('instance-admin');

		$this->groupManager->method('isAdmin')
			->with('instance-admin')
			->willReturn(true);

		$this->subAdmin->expects($this->never())
			->method('getSubAdminsGroups');

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame([], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsSubAdminGroupsForNonAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('finance');

		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('legal');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([$group1, $group2]);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame(['finance', 'legal'], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsEmptyForRegularUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('regular-user');

		$this->groupManager->method('isAdmin')
			->with('regular-user')
			->willReturn(false);

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([]);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame([], $result);
	}
}
