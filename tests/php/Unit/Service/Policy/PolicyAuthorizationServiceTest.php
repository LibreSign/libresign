<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyAuthorizationServiceTest extends TestCase {
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyService&MockObject $policyService;
	private PolicyAuthorizationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->service = new PolicyAuthorizationService(
			$this->groupManager,
			$this->subAdmin,
			$this->policyService,
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

		// Instance admins don't need a restricted group list, so getUserGroupIds should never be called
		$this->groupManager->expects($this->never())
			->method('getUserGroupIds');

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame([], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsManagedGroupsForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);
		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$financeGroup = $this->createMock(IGroup::class);
		$financeGroup->method('getGID')->willReturn('finance');
		$legalGroup = $this->createMock(IGroup::class);
		$legalGroup->method('getGID')->willReturn('legal');

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([$financeGroup, $legalGroup]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["finance","board"],"denyGroups":["legal"]}')
			->setEditableByCurrentActor(true);
		$this->policyService->method('resolveForUser')
			->willReturn($resolved);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame(['finance', 'legal'], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsEmptyForRegularUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('regular-user');

		$this->groupManager->method('isAdmin')
			->with('regular-user')
			->willReturn(false);
		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(false);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame([], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsAllManagedGroupsWhenDelegationIsEditable(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);
		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$board = $this->createMock(IGroup::class);
		$board->method('getGID')->willReturn('board');
		$company = $this->createMock(IGroup::class);
		$company->method('getGID')->willReturn('company');

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([$board, $company]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":["ops"]}')
			->setEditableByCurrentActor(true);
		$this->policyService->method('resolveForUser')
			->willReturn($resolved);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame(['board', 'company'], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsEmptyWhenDelegationIsNotEditable(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);
		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$board = $this->createMock(IGroup::class);
		$board->method('getGID')->willReturn('board');
		$company = $this->createMock(IGroup::class);
		$company->method('getGID')->willReturn('company');

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([$board, $company]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
			->setEditableByCurrentActor(false);
		$this->policyService->method('resolveForUser')
			->willReturn($resolved);
		$this->policyService->expects($this->once())
			->method('listGroupPoliciesForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['board', 'company'])
			->willReturn([]);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame([], $result);
	}

	public function testGetManageablePolicyGroupIdsReturnsManagedGroupsWhenDelegationIsNotEditableButManagedOverrideExists(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')
			->with('subadmin-user')
			->willReturn(false);
		$this->subAdmin->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$board = $this->createMock(IGroup::class);
		$board->method('getGID')->willReturn('board');
		$company = $this->createMock(IGroup::class);
		$company->method('getGID')->willReturn('company');

		$this->subAdmin->method('getSubAdminsGroups')
			->with($user)
			->willReturn([$board, $company]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":["board"]}')
			->setEditableByCurrentActor(false);
		$this->policyService->method('resolveForUser')
			->willReturn($resolved);
		$this->policyService->expects($this->once())
			->method('listGroupPoliciesForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['board', 'company'])
			->willReturn([
				[
					'targetId' => 'board',
					'policy' => (new \OCA\Libresign\Service\Policy\Model\PolicyLayer())
						->setScope('group')
						->setAllowChildOverride(false)
						->setCreatedBySystemAdmin(false)
				],
			]);

		$result = $this->service->getManageablePolicyGroupIds($user);

		$this->assertSame(['board', 'company'], $result);
	}
}
