<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\ResolvedPolicy;
use OCA\Libresign\Service\Policy\SignatureFlowPolicyService;
use OCA\Libresign\Service\Policy\SignatureFlowPolicySource;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureFlowPolicyServiceTest extends TestCase {
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private SignatureFlowPolicySource&MockObject $source;

	protected function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->source = $this->createMock(SignatureFlowPolicySource::class);
	}

	public function testResolveForUserIdBuildsContextWithGroupsAndRequestOverride(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('john')
			->willReturn($user);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['finance']);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new \OCA\Libresign\Service\Policy\PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->willReturn([(new \OCA\Libresign\Service\Policy\PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric'])]);

		$this->source
			->method('loadCirclePolicies')
			->willReturn([]);

		$this->source
			->method('loadUserPreference')
			->willReturn(null);

		$this->source
			->method('loadRequestOverride')
			->willReturn((new \OCA\Libresign\Service\Policy\PolicyLayer())
				->setScope('request')
				->setValue('ordered_numeric'));

		$service = new SignatureFlowPolicyService(
			$this->userManager,
			$this->groupManager,
			$this->source,
		);

		$resolved = $service->resolveForUserId('john', ['signature_flow' => 'ordered_numeric']);

		$this->assertInstanceOf(ResolvedPolicy::class, $resolved);
		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('request', $resolved->getSourceScope());
	}

	public function testResolveForUserIdWithoutUserFallsBackToSystem(): void {
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ghost')
			->willReturn(null);

		$this->groupManager
			->expects($this->never())
			->method('getUserGroupIds');

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new \OCA\Libresign\Service\Policy\PolicyLayer())
				->setScope('system')
				->setValue('parallel')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel']));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new SignatureFlowPolicyService(
			$this->userManager,
			$this->groupManager,
			$this->source,
		);

		$resolved = $service->resolveForUserId('ghost');

		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
	}
}
