<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer\FilePolicy;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FilePolicy\FooterFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class FooterFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): FooterFilePolicyApplier {
		return new FooterFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$footerValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(FooterPolicy::KEY, null, [FooterPolicy::KEY => $footerValue])
			->willReturn($this->createResolvedPolicy($footerValue, sourceScope: 'group'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [FooterPolicy::KEY => $footerValue],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'add_footer' => [
					'effectiveValue' => $footerValue,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$footerValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Blocked</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(FooterPolicy::KEY, null, [FooterPolicy::KEY => $footerValue])
			->willReturn($this->createResolvedPolicy(
				$footerValue,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [FooterPolicy::KEY => $footerValue],
		]);
	}

	public function testSyncPersistsWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$footerValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Request</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(FooterPolicy::KEY, 'john', [FooterPolicy::KEY => $footerValue])
			->willReturn($this->createResolvedPolicy($footerValue, sourceScope: 'request'));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, [
			'policyOverrides' => [FooterPolicy::KEY => $footerValue],
		]);
	}

	private function createResolvedPolicy(
		string $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(FooterPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
