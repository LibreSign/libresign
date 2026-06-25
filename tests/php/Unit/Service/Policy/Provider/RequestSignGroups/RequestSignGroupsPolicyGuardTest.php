<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyGuard;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestSignGroupsPolicyGuardTest extends TestCase {
	private IL10N&MockObject $l10n;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyService&MockObject $policyService;
	private PolicyAuthorizationService $policyAuthorizationService;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnCallback(static fn (string $text): string => $text);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyAuthorizationService = new PolicyAuthorizationService(
			$this->groupManager,
			$this->subAdmin,
			$this->policyService,
		);
	}

	public function testNormalizeManagedValueReturnsInputForOtherPolicies(): void {
		$guard = $this->createGuard();

		$this->assertSame('parallel', $guard->normalizeManagedValue('signature_flow', 'parallel'));
	}

	public function testNormalizeManagedValueAllowsNullToResetSystemDefault(): void {
		$guard = $this->createGuard();

		$this->assertNull($guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, null, true));
	}

	public function testNormalizeManagedValueRejectsGroupsOutsideDelegatedPolicyScope(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('subadmin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->mockManageablePolicyScope($user, ['finance'], ['finance']);

		$guard = $this->createGuard();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('One or more selected groups are not allowed for your administration scope');

		$guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, '["finance","legal"]');
	}

	public function testNormalizeManagedValueRejectsRemovingManagedGroupFromGroupScopedRuleForNonSystemAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ceo');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('ceo')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->mockManageablePolicyScope($user, ['board', 'company'], ['board', 'company']);

		$guard = $this->createGuard();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('You cannot remove your managed group from this rule');

		$guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, '["company"]', false, 'board');
	}

	public function testNormalizeManagedValueAllowsKeepingManagedGroupInGroupScopedRule(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ceo');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('ceo')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->mockManageablePolicyScope($user, ['board', 'company'], ['board', 'company']);

		$guard = $this->createGuard();

		$normalized = $guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, '["board","company"]', false, 'board');
		$this->assertSame('{"allowGroups":["board","company"],"denyGroups":[]}', $normalized);
	}

	public function testNormalizeManagedValueRejectsDeniedGroupsOutsideDelegatedPolicyScope(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ceo');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('ceo')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->mockManageablePolicyScope($user, ['board', 'company'], ['board', 'company']);

		$guard = $this->createGuard();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('One or more selected groups are not allowed for your administration scope');

		$guard->normalizeManagedValue(
			RequestSignGroupsPolicy::KEY,
			'{"allowGroups":["board"],"denyGroups":["legal"]}',
			false,
			'board',
		);
	}

	public function testNormalizeManagedValueAllowsManagedTargetGroupOutsideInheritedSeedScope(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ceo');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('ceo')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->mockManageablePolicyScope($user, ['board', 'company'], ['board']);

		$guard = $this->createGuard();

		$normalized = $guard->normalizeManagedValue(
			RequestSignGroupsPolicy::KEY,
			'{"allowGroups":["company"],"denyGroups":[]}',
			false,
			'company',
		);

		$this->assertSame('{"allowGroups":["company"],"denyGroups":[]}', $normalized);
	}

	public function testAssertUserScopeSupportedRejectsRequestSignGroupsPolicy(): void {
		$guard = $this->createGuard();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$guard->assertUserScopeSupported(RequestSignGroupsPolicy::KEY);
	}

	/**
	 * @param list<string> $managedGroupIds
	 * @param list<string> $allowGroups
	 * @param list<string> $denyGroups
	 */
	private function mockManageablePolicyScope(IUser $user, array $managedGroupIds, array $allowGroups, array $denyGroups = []): void {
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($user)
			->willReturn(array_map(fn (string $groupId): IGroup => $this->createGroup($groupId), $managedGroupIds));

		$this->policyService
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $user)
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue(json_encode([
					'allowGroups' => $allowGroups,
					'denyGroups' => $denyGroups,
				], JSON_THROW_ON_ERROR))
				->setEditableByCurrentActor(true));
	}

	private function createGroup(string $groupId): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($groupId);
		return $group;
	}

	private function createGuard(): RequestSignGroupsPolicyGuard {
		return new RequestSignGroupsPolicyGuard(
			$this->l10n,
			$this->userSession,
			$this->groupManager,
			$this->subAdmin,
			$this->policyAuthorizationService,
		);
	}
}
