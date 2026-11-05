<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DocMdp\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy\DocMdpFilePolicyApplier;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class DocMdpFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): DocMdpFilePolicyApplier {
		return new DocMdpFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplySetsDocMdpLevelAndStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				DocMdpPolicy::KEY,
				null,
				[DocMdpPolicy::KEY => 3],
				['type' => 'user', 'id' => 'john'],
			)
			->willReturn($this->createResolvedPolicy(3, sourceScope: 'user'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [DocMdpPolicy::KEY => 3],
			'policyActiveContext' => ['type' => 'user', 'id' => 'john'],
		]);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS, $file->getDocmdpLevelEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 3,
					'sourceScope' => 'user',
				],
			],
		], $file->getMetadata());
	}

	public function testSyncUpdatesWhenResolvedValueChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(2));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
	}

	public function testSyncDoesNotPersistWhenNothingChangedAndSnapshotExists(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$file->setMetadata([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 2,
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(2));

		$this->fileService
			->expects($this->never())
			->method('update');

		$this->getApplier()->sync($file, []);
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @param array<string, mixed> $policyOverrides
	 */
	#[DataProvider('provideFrozenUpdates')]
	public function testAFrozenRequestKeepsItsStoredLevel(int $status, array $metadata, array $policyOverrides): void {
		$file = $this->createStoredRequest($status, $metadata);
		$storedMetadata = $file->getMetadata();

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => $policyOverrides]);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, $file->getDocmdpLevelEnum());
		$this->assertSame($storedMetadata, $file->getMetadata());
	}

	/** @return iterable<string, array{0: int, 1: array<string, mixed>, 2: array<string, mixed>}> */
	public static function provideFrozenUpdates(): iterable {
		yield 'nothing submitted' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, []];
		yield 'stored level sent again' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [DocMdpPolicy::KEY => DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value]];
		yield 'stored level sent again as a string' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [DocMdpPolicy::KEY => '1']];
		yield 'null submitted' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [DocMdpPolicy::KEY => null]];
		yield 'returned to draft' => [FileStatus::DRAFT->value, self::FROZEN, []];
		yield 'sent before the freeze was recorded' => [FileStatus::ABLE_TO_SIGN->value, [], []];
	}

	/** @param array<string, mixed> $metadata */
	#[DataProvider('provideFrozenChangeAttempts')]
	public function testChangingTheLevelOfAFrozenRequestIsRefused(int $status, array $metadata, mixed $submittedLevel): void {
		$file = $this->createStoredRequest($status, $metadata);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The PDF certification cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, ['policyOverrides' => [DocMdpPolicy::KEY => $submittedLevel]]);
	}

	/** @return iterable<string, array{0: int, 1: array<string, mixed>, 2: mixed}> */
	public static function provideFrozenChangeAttempts(): iterable {
		yield 'another level' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, DocMdpLevel::CERTIFIED_FORM_FILLING->value];
		yield 'not certified' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, DocMdpLevel::NOT_CERTIFIED->value];
		yield 'after returning to draft' => [FileStatus::DRAFT->value, self::FROZEN, DocMdpLevel::CERTIFIED_FORM_FILLING->value];
		yield 'not a level' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, 'one'];
	}

	public function testADraftStillResolvesTheSubmittedLevel(): void {
		$file = $this->createStoredRequest(FileStatus::DRAFT->value, []);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [DocMdpPolicy::KEY => 2])
			->willReturn($this->createResolvedPolicy(2, sourceScope: 'request'));
		$this->fileService->expects($this->once())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => [DocMdpPolicy::KEY => 2]]);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
	}

	/** @param array<string, mixed> $metadata */
	private function createStoredRequest(int $status, array $metadata): File {
		$file = new File();
		$file->setUserId('john');
		$file->setStatus($status);
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$file->setMetadata($metadata + [
			'policy_snapshot' => [
				DocMdpPolicy::KEY => ['effectiveValue' => DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, 'sourceScope' => 'system'],
			],
		]);
		return $file;
	}

	private function createResolvedPolicy(int $effectiveValue, string $sourceScope = 'system'): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(DocMdpPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride(true)
			->setBlockedBy(null);
	}
}
