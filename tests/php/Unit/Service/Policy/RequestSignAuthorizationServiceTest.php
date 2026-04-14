<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestSignAuthorizationServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private IGroupManager&MockObject $groupManager;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
	}

	public function testDeniesWhenUserIsNotAuthenticated(): void {
		$service = new RequestSignAuthorizationService($this->policyService, $this->groupManager);
		$this->assertFalse($service->canRequestSign(null));
	}

	public function testAllowsWhenUserBelongsToAuthorizedGroup(): void {
		$user = $this->createMock(IUser::class);
		$resolvedPolicy = (new ResolvedPolicy())->setEffectiveValue('["admin","finance"]');

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $user)
			->willReturn($resolvedPolicy);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['finance']);

		$service = new RequestSignAuthorizationService($this->policyService, $this->groupManager);
		$this->assertTrue($service->canRequestSign($user));
	}

	public function testDeniesWhenUserDoesNotBelongToAuthorizedGroups(): void {
		$user = $this->createMock(IUser::class);
		$resolvedPolicy = (new ResolvedPolicy())->setEffectiveValue('["admin","finance"]');

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $user)
			->willReturn($resolvedPolicy);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['sales']);

		$service = new RequestSignAuthorizationService($this->policyService, $this->groupManager);
		$this->assertFalse($service->canRequestSign($user));
	}
}
