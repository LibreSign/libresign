<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Signature\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Signature\FilePolicy\SignatureFlowFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\SequentialSigningService;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignatureFlowFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): SignatureFlowFilePolicyApplier {
		return new SignatureFlowFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplySetsFlowAndStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				SignatureFlowPolicy::KEY,
				null,
				[SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
				['type' => 'group', 'id' => 'g1'],
			)
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
			'policyActiveContext' => ['type' => 'group', 'id' => 'g1'],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
		]);
	}

	public function testSyncUpdatesFileWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @param array<string, string> $policyOverrides
	 */
	#[DataProvider('provideFrozenUpdates')]
	public function testAFrozenRequestKeepsItsStoredFlow(int $status, array $metadata, array $policyOverrides): void {
		$file = $this->createStoredRequest($status, $metadata, SignatureFlow::NONE);
		$storedMetadata = $file->getMetadata();

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => $policyOverrides]);

		$this->assertSame(SignatureFlow::NONE, $file->getSignatureFlowEnum());
		$this->assertSame($storedMetadata, $file->getMetadata());
	}

	/** @return iterable<string, array{0: int, 1: array<string, mixed>, 2: array<string, string>}> */
	public static function provideFrozenUpdates(): iterable {
		yield 'nothing submitted' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, []];
		yield 'stored flow sent again' => [FileStatus::ABLE_TO_SIGN->value, self::FROZEN, [SignatureFlowPolicy::KEY => 'none']];
		yield 'returned to draft' => [FileStatus::DRAFT->value, self::FROZEN, []];
		yield 'sent before the freeze was recorded' => [FileStatus::ABLE_TO_SIGN->value, [], []];
	}

	#[DataProvider('provideFrozenChangeAttempts')]
	public function testChangingTheFlowOfAFrozenRequestIsRefused(int $status, SignatureFlow $storedFlow, string $submittedFlow): void {
		$file = $this->createStoredRequest($status, self::FROZEN, $storedFlow);

		$this->fileService->expects($this->never())->method('update');
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signing order cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, ['policyOverrides' => [SignatureFlowPolicy::KEY => $submittedFlow]]);
	}

	/** @return iterable<string, array{0: int, 1: SignatureFlow, 2: string}> */
	public static function provideFrozenChangeAttempts(): iterable {
		yield 'another flow' => [FileStatus::ABLE_TO_SIGN->value, SignatureFlow::PARALLEL, 'ordered_numeric'];
		yield 'parallel is not the same as no flow' => [FileStatus::ABLE_TO_SIGN->value, SignatureFlow::NONE, 'parallel'];
		yield 'after returning to draft' => [FileStatus::DRAFT->value, SignatureFlow::PARALLEL, 'ordered_numeric'];
	}

	public function testASignerAddedAfterTheFreezeFollowsTheStoredFlow(): void {
		$file = $this->createStoredRequest(FileStatus::ABLE_TO_SIGN->value, self::FROZEN, SignatureFlow::ORDERED_NUMERIC);
		$this->getApplier()->sync($file, []);

		$sequentialSigning = new SequentialSigningService(
			$this->createMock(SignRequestMapper::class),
			$this->createMock(IdentifyMethodService::class),
			$this->createMock(LoggerInterface::class),
		);
		$sequentialSigning->setFile($file);
		$this->assertSame([1, 2], [
			$sequentialSigning->determineSigningOrder(null),
			$sequentialSigning->determineSigningOrder(null),
		]);
	}

	/** @param array<string, mixed> $metadata */
	private function createStoredRequest(int $status, array $metadata, SignatureFlow $storedFlow): File {
		$file = new File();
		$file->setUserId('john');
		$file->setStatus($status);
		$file->setSignatureFlowEnum($storedFlow);
		$file->setMetadata($metadata + [
			'policy_snapshot' => [
				SignatureFlowPolicy::KEY => ['effectiveValue' => $storedFlow->value, 'sourceScope' => 'system'],
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
			->setPolicyKey(SignatureFlowPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
