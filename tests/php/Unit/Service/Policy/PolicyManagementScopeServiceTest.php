<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyManagementScopeService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyManagementScopeServiceTest extends TestCase {
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyService&MockObject $policyService;
	private PolicyAuthorizationService $policyAuthorizationService;
	private PolicyManagementScopeService $service;
	private IUser&MockObject $currentUser;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->currentUser = $this->createMock(IUser::class);
		$this->currentUser->method('getUID')->willReturn('current-user');
		$this->userSession->method('getUser')->willReturn($this->currentUser);

		$this->policyAuthorizationService = new PolicyAuthorizationService(
			$this->groupManager,
			$this->subAdmin,
			$this->policyService,
		);
		$this->service = new PolicyManagementScopeService(
			$this->userSession,
			$this->groupManager,
			$this->userManager,
			$this->subAdmin,
			$this->policyService,
			$this->policyAuthorizationService,
		);
	}

	public function testResolveVisibleRuleCountsForCurrentActorReturnsAllCountsForSystemAdmin(): void {
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('current-user')
			->willReturn(true);

		$this->policyService->expects($this->once())
			->method('getAllRuleCounts')
			->willReturn($counts = [
				'signature_flow' => ['groupCount' => 2, 'userCount' => 1, 'everyoneCount' => 0],
			]);

		$this->assertSame($counts, $this->service->resolveVisibleRuleCountsForCurrentActor());
	}

	public function testResolveVisibleRuleCountsForCurrentActorFiltersHiddenManagedGroupCountsForSubAdmin(): void {
		$this->groupManager->method('isAdmin')->with('current-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($this->currentUser)->willReturn(['board']);

		$managedGroup = $this->createMock(IGroup::class);
		$managedGroup->method('getGID')->willReturn('board');
		$this->subAdmin->method('getSubAdminsGroups')->with($this->currentUser)->willReturn([$managedGroup]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
			->setEditableByCurrentActor(true);
		$this->policyService->method('resolveForUser')->willReturn($resolved);

		$this->policyService->expects($this->once())
			->method('getRuleCounts')
			->with(['board'], [])
			->willReturn([
				RequestSignGroupsPolicy::KEY => ['groupCount' => 3, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_flow' => ['groupCount' => 1, 'userCount' => 2, 'everyoneCount' => 0],
			]);

		$this->policyService->expects($this->exactly(2))
			->method('shouldFilterVisibleGroupCountsForCurrentActor')
			->willReturnCallback(static fn (string $policyKey): bool => $policyKey === RequestSignGroupsPolicy::KEY);

		$this->policyService->expects($this->once())
			->method('countVisibleGroupPoliciesForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['board'])
			->willReturn(1);

		$result = $this->service->resolveVisibleRuleCountsForCurrentActor();

		$this->assertSame(1, $result[RequestSignGroupsPolicy::KEY]['groupCount']);
		$this->assertSame(2, $result['signature_flow']['userCount']);
	}

	public function testCanCurrentActorManageGroupPolicyUsesDelegatedManageableGroupsForRequestSignPolicy(): void {
		$this->groupManager->method('isAdmin')->with('current-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);

		$managedGroup = $this->createMock(IGroup::class);
		$managedGroup->method('getGID')->willReturn('board');
		$this->subAdmin->method('getSubAdminsGroups')->with($this->currentUser)->willReturn([$managedGroup]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
			->setEditableByCurrentActor(true);
		$this->policyService->method('resolveForUser')->willReturn($resolved);

		$this->assertTrue(
			$this->service->canCurrentActorManageGroupPolicy('board', RequestSignGroupsPolicy::KEY),
		);
	}

	public function testCanCurrentActorManageScopedUserPolicyRequiresSharedManagedGroupAndPolicyDelegation(): void {
		$this->groupManager->method('isAdmin')->with('current-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);

		$managedGroup = $this->createMock(IGroup::class);
		$managedGroup->method('getGID')->willReturn('board');
		$this->subAdmin->method('getSubAdminsGroups')->with($this->currentUser)->willReturn([$managedGroup]);

		$targetUser = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('target-user')->willReturn($targetUser);
		$this->groupManager->method('getUserGroupIds')->with($targetUser)->willReturn(['board']);

		$this->policyService->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('confetti', 'target-user')
			->willReturn(true);

		$this->assertTrue(
			$this->service->canCurrentActorManageScopedUserPolicy('target-user', 'confetti'),
		);
	}

	#[DataProvider('manageableGroupScopeScenarios')]
	public function testResolveCurrentActorManageableGroupIdsForPolicyForCommonActorScopes(bool $hasActor, ?bool $isAdmin, bool $isSubAdmin, ?array $userGroupIds, ?array $expected): void {
		$actor = $hasActor ? $this->createConfiguredMock(IUser::class, ['getUID' => 'current-user']) : null;
		$this->userSession->method('getUser')->willReturn($actor);

		if ($actor instanceof IUser) {
			$this->groupManager->method('isAdmin')->with('current-user')->willReturn($isAdmin ?? false);
			$this->subAdmin->method('isSubAdmin')->with($actor)->willReturn($isSubAdmin);
			if ($userGroupIds !== null) {
				$this->groupManager->method('getUserGroupIds')->with($actor)->willReturn($userGroupIds);
			}
		}

		$this->assertSame($expected, $this->service->resolveCurrentActorManageableGroupIdsForPolicy('signature_flow'));
	}

	public function testResolveCurrentActorManageableGroupIdsForRequestSignPolicyUsesDelegatedGroups(): void {
		$this->groupManager->method('isAdmin')->with('current-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);

		$managedGroup = $this->createMock(IGroup::class);
		$managedGroup->method('getGID')->willReturn('board');
		$this->subAdmin->method('getSubAdminsGroups')->with($this->currentUser)->willReturn([$managedGroup]);

		$resolved = (new ResolvedPolicy())
			->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
			->setEditableByCurrentActor(true);
		$this->policyService->method('resolveForUser')->willReturn($resolved);

		$this->assertSame(
			['board'],
			$this->service->resolveCurrentActorManageableGroupIdsForPolicy(RequestSignGroupsPolicy::KEY),
		);
	}

	public static function manageableGroupScopeScenarios(): array {
		return [
			'no actor' => [
				'hasActor' => false,
				'isAdmin' => null,
				'isSubAdmin' => false,
				'userGroupIds' => null,
				'expected' => [],
			],
			'system admin manages all groups' => [
				'hasActor' => true,
				'isAdmin' => true,
				'isSubAdmin' => false,
				'userGroupIds' => null,
				'expected' => null,
			],
			'regular user manages none' => [
				'hasActor' => true,
				'isAdmin' => false,
				'isSubAdmin' => false,
				'userGroupIds' => null,
				'expected' => [],
			],
			'subadmin manages own groups for standard policies' => [
				'hasActor' => true,
				'isAdmin' => false,
				'isSubAdmin' => true,
				'userGroupIds' => ['board', 'finance'],
				'expected' => ['board', 'finance'],
			],
		];
	}
}
