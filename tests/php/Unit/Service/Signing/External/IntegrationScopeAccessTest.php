<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Signing\External\IntegrationScopeAccess;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class IntegrationScopeAccessTest extends TestCase {
	private PolicyService $policies;
	private IGroupManager $groups;
	private IUserManager $users;
	private IUserSession $session;
	private ISubAdmin $subAdmin;
	private IntegrationScopeAccess $access;

	protected function setUp(): void {
		$this->policies = $this->createMock(PolicyService::class);
		$this->groups = $this->createMock(IGroupManager::class);
		$this->users = $this->createMock(IUserManager::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->access = new IntegrationScopeAccess(
			$this->policies, $this->groups, $this->users, $this->session, $this->subAdmin,
		);
	}

	private function actor(string $id): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($id);
		$this->session->method('getUser')->willReturn($user);
		return $user;
	}

	private function rule(bool $enabled, bool $delegated = false): PolicyLayer {
		return (new PolicyLayer())->setValue($enabled)->setScope('group')
			->setVisibleToChild(true)->setAllowChildOverride($delegated);
	}

	private function integration(string $type, string $id, bool $enabled = true): ExternalIntegrationRecord {
		return new ExternalIntegrationRecord(
			str_repeat('a', 32), 'csc-v1', 'Client CSC', [], $enabled, true, 1,
			'2026-11-24T00:00:00+00:00', '2026-11-24T00:00:00+00:00', $type, $id,
		);
	}

	public function testAnonymousAndRegularUserCannotManageSystem(): void {
		self::assertFalse($this->access->canManage('system', ''));
		$this->actor('regular');
		$this->groups->method('isAdmin')->willReturn(false);
		self::assertFalse($this->access->canManage('system', ''));
	}

	public function testSystemAdminCanManageAllScopes(): void {
		$this->actor('root');
		$this->groups->method('isAdmin')->willReturn(true);
		self::assertTrue($this->access->canManage('system', ''));
		self::assertTrue($this->access->canManage('group', 'client-a'));
		self::assertTrue($this->access->canManage('user', 'person-b'));
	}

	public function testDelegatedGroupAdminCanManageOnlyTheirAllowedGroup(): void {
		$actor = $this->actor('group-admin-a');
		$this->groups->method('isAdmin')->willReturn(false);
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('client-a');
		$this->subAdmin->method('isSubAdmin')->with($actor)->willReturn(true);
		$this->subAdmin->method('getSubAdminsGroups')->with($actor)->willReturn([$group]);
		$this->policies->method('getGroupPolicy')->willReturnCallback(
			fn (string $key, string $id): ?PolicyLayer => $id === 'client-a' ? $this->rule(true, true) : null,
		);
		self::assertTrue($this->access->canManage('group', 'client-a'));
		self::assertFalse($this->access->canManage('group', 'client-b'));
		self::assertFalse($this->access->canManage('system', ''));
		self::assertFalse($this->access->canManage('user', 'client-a-user'));
		self::assertTrue($this->access->canManageAny());
	}

	public function testNonDelegatedGroupRuleDoesNotGrantConfigurationRights(): void {
		$actor = $this->actor('group-admin');
		$this->groups->method('isAdmin')->willReturn(false);
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('client-a');
		$this->subAdmin->method('isSubAdmin')->willReturn(true);
		$this->subAdmin->method('getSubAdminsGroups')->willReturn([$group]);
		$this->policies->method('getGroupPolicy')->willReturn($this->rule(true, false));
		self::assertFalse($this->access->canManage('group', 'client-a'));
	}

	public function testDelegatedIndividualCanOnlyConfigureOwnIntegration(): void {
		$this->actor('alice');
		$this->groups->method('isAdmin')->willReturn(false);
		$this->policies->method('getUserPolicyForUserId')->willReturn($this->rule(true, true));
		$this->policies->method('resolveForUserId')->willReturn((new ResolvedPolicy())->setEffectiveValue(true));
		self::assertTrue($this->access->canManage('user', 'alice'));
		self::assertFalse($this->access->canManage('user', 'bob'));
		self::assertFalse($this->access->canManage('group', 'client-b'));
	}

	public function testGuestMayUseClientGroupConnectionOnlyForOwnersInThatGroup(): void {
		$owner = $this->createMock(IUser::class);
		$this->users->method('get')->willReturn($owner);
		$this->groups->method('getUserGroupIds')->with($owner)->willReturn(['client-a']);
		$this->policies->method('getGroupPolicy')->willReturnCallback(
			fn (string $key, string $id): ?PolicyLayer => $this->rule($id === 'client-a'),
		);
		$this->policies->method('resolveForUserId')->willReturn(
			(new ResolvedPolicy())->setEffectiveValue(true),
		);
		self::assertTrue($this->access->canUse($this->integration('group', 'client-a'), 'owner-a', null));
		self::assertFalse($this->access->canUse($this->integration('group', 'client-b'), 'owner-a', null));
		self::assertFalse($this->access->canUse($this->integration('user', 'alice'), 'owner-a', null));
	}

	public function testIndividualConnectionRequiresMatchingSignerAccount(): void {
		$this->policies->method('getUserPolicyForUserId')->willReturn($this->rule(true, true));
		$this->policies->method('resolveForUserId')->willReturn(
			(new ResolvedPolicy())->setEffectiveValue(true),
		);
		self::assertTrue($this->access->canUse($this->integration('user', 'alice'), 'owner-b', 'alice'));
		self::assertFalse($this->access->canUse($this->integration('user', 'alice'), 'owner-b', 'bob'));
		self::assertFalse($this->access->canUse($this->integration('user', 'alice'), 'owner-b', null));
	}

	public function testDisabledIntegrationAndMissingPolicyDefaultToDenied(): void {
		self::assertFalse($this->access->canUse($this->integration('system', '', false), 'owner', null));
		$this->policies->method('resolveForUserId')->willThrowException(new \InvalidArgumentException('Policy missing'));
		self::assertFalse($this->access->canUse($this->integration('system', ''), 'owner', null));
	}

	public function testInvalidScopeIsRejectedBeforePermissionEvaluation(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->access->canManage('group', '');
	}
}
