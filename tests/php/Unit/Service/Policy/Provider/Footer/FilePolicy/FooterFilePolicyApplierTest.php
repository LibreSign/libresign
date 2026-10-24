<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FilePolicy\FooterFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class FooterFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private const FROZEN = ['policy_snapshot_frozen_at' => '2026-01-01T00:00:00+00:00'];

	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyService->method('getRequestLifecycle')->willReturn(PolicySpec::LIFECYCLE_REQUEST_SNAPSHOT);
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

	/**
	 * @param array<string, mixed> $metadata
	 * @param array<string, mixed> $policyOverrides
	 */
	#[DataProvider('provideFrozenUpdates')]
	public function testAFrozenRequestKeepsItsStoredFooter(int $status, array $metadata, array $policyOverrides): void {
		$file = $this->createStoredRequest($status, $metadata);
		$storedMetadata = $file->getMetadata();

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => $policyOverrides]);

		$this->assertSame($storedMetadata, $file->getMetadata());
	}

	/** @return iterable<string, array{0: int, 1: array<string, mixed>, 2: array<string, mixed>}> */
	public static function provideFrozenUpdates(): iterable {
		yield 'nothing submitted' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, []];
		yield 'stored footer sent again' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [FooterPolicy::KEY => self::storedFooter()]];
		yield 'stored footer sent again in another shape' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [FooterPolicy::KEY => ['customizeFooterTemplate' => true, 'footerTemplate' => '<p>Stored</p>']]];
		yield 'null submitted' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [FooterPolicy::KEY => null]];
		yield 'returned to draft' => [FileStatus::DRAFT->value, self::FROZEN, []];
		yield 'sent before the freeze was recorded' => [FileStatus::ABLE_TO_SIGN->value, [], []];
	}

	/** @param array<string, mixed> $metadata */
	#[DataProvider('provideFrozenChangeAttempts')]
	public function testChangingTheFooterOfAFrozenRequestIsRefused(int $status, array $metadata, string $submittedFooter): void {
		$file = $this->createStoredRequest($status, $metadata);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The footer cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, ['policyOverrides' => [FooterPolicy::KEY => $submittedFooter]]);
	}

	/** @return iterable<string, array{0: int, 1: array<string, mixed>, 2: string}> */
	public static function provideFrozenChangeAttempts(): iterable {
		$anotherFooter = FooterPolicyValue::encode(['enabled' => false]);
		yield 'another footer' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, $anotherFooter];
		yield 'after returning to draft' => [FileStatus::DRAFT->value, self::FROZEN, $anotherFooter];
		yield 'sent before the footer was stored' => [FileStatus::ABLE_TO_SIGN->value, ['policy_snapshot' => []], self::storedFooter()];
	}

	public function testADraftStillResolvesTheSubmittedFooter(): void {
		$file = $this->createStoredRequest(FileStatus::DRAFT->value, []);
		$submittedFooter = FooterPolicyValue::encode(['enabled' => false]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(FooterPolicy::KEY, 'john', [FooterPolicy::KEY => $submittedFooter])
			->willReturn($this->createResolvedPolicy($submittedFooter, sourceScope: 'request'));
		$this->fileService->expects($this->once())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => [FooterPolicy::KEY => $submittedFooter]]);

		$this->assertSame($submittedFooter, $file->getMetadata()['policy_snapshot'][FooterPolicy::KEY]['effectiveValue']);
	}

	private static function storedFooter(): string {
		return FooterPolicyValue::encode(['customizeFooterTemplate' => true, 'footerTemplate' => '<p>Stored</p>']);
	}

	/** @param array<string, mixed> $metadata */
	private function createStoredRequest(int $status, array $metadata): File {
		$file = new File();
		$file->setUserId('john');
		$file->setStatus($status);
		$file->setMetadata($metadata + [
			'policy_snapshot' => [
				FooterPolicy::KEY => ['effectiveValue' => self::storedFooter(), 'sourceScope' => 'request'],
			],
		]);
		return $file;
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
