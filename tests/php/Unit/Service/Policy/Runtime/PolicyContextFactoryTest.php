<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Runtime\PolicyContextFactory;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyContextFactoryTest extends TestCase {
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private IUserSession&MockObject $userSession;

	protected function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->userSession = $this->createMock(IUserSession::class);
	}

	public function testForCurrentUserUsesSessionUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');

		$this->userSession->expects($this->once())->method('getUser')->willReturn($user);
		$this->groupManager->expects($this->once())->method('getUserGroupIds')->with($user)->willReturn(['finance']);
		$this->groupManager->expects($this->once())->method('isAdmin')->with('john')->willReturn(false);
		$this->subAdmin->expects($this->once())->method('isSubAdmin')->with($user)->willReturn(false);

		$factory = $this->getFactory();
		$context = $factory->forCurrentUser(['signature_flow' => 'parallel'], ['type' => 'group', 'id' => 'finance']);

		$this->assertSame('john', $context->getUserId());
		$this->assertSame(['finance'], $context->getGroups());
		$this->assertSame(['signature_flow' => 'parallel'], $context->getRequestOverrides());
		$this->assertSame(['type' => 'group', 'id' => 'finance'], $context->getActiveContext());
	}

	public function testForUserIdLoadsUserWhenAvailable(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())->method('get')->with('john')->willReturn($user);
		$this->groupManager->expects($this->once())->method('getUserGroupIds')->with($user)->willReturn(['finance']);

		$factory = $this->getFactory();
		$context = $factory->forUserId('john');

		$this->assertSame('john', $context->getUserId());
		$this->assertSame(['finance'], $context->getGroups());
	}

	public function testForUserIdKeepsUserIdWithoutGroupsWhenUserDoesNotExist(): void {
		$this->userManager->expects($this->once())->method('get')->with('ghost')->willReturn(null);
		$this->groupManager->expects($this->never())->method('getUserGroupIds');

		$factory = $this->getFactory();
		$context = $factory->forUserId('ghost');

		$this->assertSame('ghost', $context->getUserId());
		$this->assertSame([], $context->getGroups());
	}

	private function getFactory(): PolicyContextFactory {
		return new PolicyContextFactory($this->userManager, $this->groupManager, $this->subAdmin, $this->userSession);
	}
}
