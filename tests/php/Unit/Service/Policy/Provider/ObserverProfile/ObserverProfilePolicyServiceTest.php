<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ObserverProfilePolicyServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
	}

	public function testUsesFileSnapshotBeforeLivePolicy(): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				ObserverProfilePolicy::KEY => [
					'effectiveValue' => true,
					'sourceScope' => 'system',
				],
			],
		]);
		$this->policyService->expects($this->never())->method('resolve');

		$this->assertTrue($this->getService()->isEnabled($file));
	}

	public function testFallsBackToLivePolicyWhenNoFileIsProvided(): void {
		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(ObserverProfilePolicy::KEY)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(true));

		$this->assertTrue($this->getService()->isEnabled());
	}

	public function testExistingFileWithoutSnapshotDoesNotUseLivePolicy(): void {
		$this->policyService
			->method('resolve')
			->with(ObserverProfilePolicy::KEY)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(true));

		$this->assertFalse($this->getService()->isEnabled(new File()));
	}

	public function testExistingFileSnapshotWithoutObserverPolicyDoesNotUseLivePolicy(): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => 'parallel',
					'sourceScope' => 'system',
				],
			],
		]);
		$this->policyService
			->method('resolve')
			->with(ObserverProfilePolicy::KEY)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(true));

		$this->assertFalse($this->getService()->isEnabled($file));
	}

	private function getService(): ObserverProfilePolicyService {
		return new ObserverProfilePolicyService($this->policyService);
	}
}
