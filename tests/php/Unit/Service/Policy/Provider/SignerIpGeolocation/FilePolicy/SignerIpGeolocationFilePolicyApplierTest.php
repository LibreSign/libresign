<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignerIpGeolocation\FilePolicy;

use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\FilePolicy\SignerIpGeolocationFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerIpGeolocationFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getApplier(): SignerIpGeolocationFilePolicyApplier {
		return new SignerIpGeolocationFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$policyValue = ['mode' => 'enabled'];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->willReturn($this->createResolvedPolicy($policyValue));

		$this->getApplier()->apply($file, []);

		$this->assertSame([
			'policy_snapshot' => [
				SignerIpGeolocationPolicy::KEY => [
					'effectiveValue' => $policyValue,
					'sourceScope' => 'system',
				],
			],
		], $file->getMetadata());
	}

	public function testSyncDoesNotOverwriteExistingSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('admin');
		$file->setMetadata([
			'policy_snapshot' => [
				SignerIpGeolocationPolicy::KEY => [
					'effectiveValue' => ['mode' => 'enabled'],
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, []);

		$this->assertSame('enabled', $file->getMetadata()['policy_snapshot'][SignerIpGeolocationPolicy::KEY]['effectiveValue']['mode']);
	}

	public function testSyncDoesNotWriteCurrentPolicyOnEnvelopeWithoutOwnSnapshot(): void {
		$envelope = new \OCA\Libresign\Db\File();
		$envelope->setUserId('admin');
		$envelope->setNodeType('envelope');
		$envelope->setMetadata([
			'policy_snapshot' => [
				'enable_observer_profile' => [
					'effectiveValue' => true,
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($envelope, []);

		$this->assertArrayNotHasKey(
			SignerIpGeolocationPolicy::KEY,
			$envelope->getMetadata()['policy_snapshot'],
		);
	}

	private function createResolvedPolicy(array $effectiveValue): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignerIpGeolocationPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope('system')
			->setCanUseAsRequestOverride(true);
	}
}
