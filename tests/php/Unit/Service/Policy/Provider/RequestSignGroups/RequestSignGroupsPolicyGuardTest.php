<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyGuard;
use OCP\Group\ISubAdmin;
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

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnCallback(static fn (string $text): string => $text);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
	}

	public function testNormalizeManagedValueReturnsInputForOtherPolicies(): void {
		$guard = $this->createGuard();

		$this->assertSame('parallel', $guard->normalizeManagedValue('signature_flow', 'parallel'));
	}

	public function testNormalizeManagedValueAllowsNullToResetSystemDefault(): void {
		$guard = $this->createGuard();

		$this->assertNull($guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, null, true));
	}

	public function testNormalizeManagedValueRejectsGroupsOutsideMembershipScope(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('subadmin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['finance']);

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
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['board', 'company']);

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
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['board', 'company']);

		$guard = $this->createGuard();

		$normalized = $guard->normalizeManagedValue(RequestSignGroupsPolicy::KEY, '["board","company"]', false, 'board');
		$this->assertSame('["board","company"]', $normalized);
	}

	public function testAssertUserScopeSupportedRejectsRequestSignGroupsPolicy(): void {
		$guard = $this->createGuard();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$guard->assertUserScopeSupported(RequestSignGroupsPolicy::KEY);
	}

	private function createGuard(): RequestSignGroupsPolicyGuard {
		return new RequestSignGroupsPolicyGuard(
			$this->l10n,
			$this->userSession,
			$this->groupManager,
			$this->subAdmin,
		);
	}
}
