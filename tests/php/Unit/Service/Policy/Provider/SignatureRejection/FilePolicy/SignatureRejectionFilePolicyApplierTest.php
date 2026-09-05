<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy\SignatureRejectionFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getApplier(): SignatureRejectionFilePolicyApplier {
		return new SignatureRejectionFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	private function createResolvedPolicy(
		mixed $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignatureRejectionPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}

	/**
	 * Answer the administrative resolution; the requester choice is layered on top
	 * of it by the applier itself, not by the policy resolver.
	 *
	 * @param array<string, mixed> $administrative
	 */
	private function stubResolution(string $method, array $administrative): void {
		$this->policyService
			->method($method)
			->willReturnCallback(fn (): ResolvedPolicy => $this->createResolvedPolicy($administrative));
	}

	private function createFile(int $status = FileStatus::DRAFT->value, ?array $storedValue = null): File {
		$file = new File();
		$file->setUserId('requester');
		$file->setStatus($status);
		if ($storedValue !== null) {
			$file->setMetadata([
				'policy_snapshot' => [
					SignatureRejectionPolicy::KEY => [
						'effectiveValue' => SignatureRejectionPolicyValue::normalize($storedValue),
						'sourceScope' => 'system',
					],
				],
			]);
		}
		return $file;
	}

	private function createEnvelope(int $status = FileStatus::DRAFT->value, ?array $storedValue = null): File {
		$envelope = $this->createFile($status, $storedValue);
		$envelope->setNodeType('envelope');
		return $envelope;
	}

	/** @return array<string, mixed> */
	private function storedValueOf(File $file): array {
		return $file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY]['effectiveValue'];
	}

	private function hasStoredValue(File $file): bool {
		return isset($file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY]);
	}

	#[DataProvider('provideRequestsWithoutAChoice')]
	public function testAnEnabledPolicyDoesNotEnableRejectionByItself(array $data): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', ['enabled' => true, 'comment_mode' => 'required', 'cancel_workflow' => true]);

		$this->getApplier()->apply($file, $data);

		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->storedValueOf($file));
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>}>
	 */
	public static function provideRequestsWithoutAChoice(): iterable {
		yield 'no policy payload at all' => [[]];
		yield 'policy payload without overrides' => [['policyOverrides' => []]];
		yield 'overrides for another policy' => [['policyOverrides' => ['signer_geolocation' => ['mode' => 'required']]]];
		yield 'override without the enabled flag' => [['policyOverrides' => [SignatureRejectionPolicy::KEY => ['comment_mode' => 'required']]]];
		yield 'explicitly declined' => [['policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => false]]]];
	}

	public function testApplyUsesTheActivePolicyContextWhenGiven(): void {
		$file = $this->createFile();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureRejectionPolicy::KEY, null, [], ['type' => 'group', 'id' => 'legal'])
			->willReturn($this->createResolvedPolicy(['enabled' => true], 'group'));

		$this->getApplier()->apply($file, [
			'policyActiveContext' => ['type' => 'group', 'id' => 'legal'],
		]);

		$this->assertSame(
			'group',
			$file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY]['sourceScope'],
		);
	}

	public function testRequesterOptingInKeepsTheAdministrativeRules(): void {
		$file = $this->createFile();
		$administrative = ['enabled' => true, 'comment_mode' => 'required', 'cancel_workflow' => true];
		$this->stubResolution('resolveForUser', $administrative);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => true]],
		]);

		$this->assertSame(
			SignatureRejectionPolicyValue::normalize($administrative),
			$this->storedValueOf($file),
		);
	}

	public function testRequesterCanOptInWithABareBoolean(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', ['enabled' => true, 'comment_mode' => 'optional']);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => true],
		]);

		$this->assertTrue($this->storedValueOf($file)['enabled']);
	}

	public function testRequesterCannotEnableRejectionWhenThePolicyDisablesIt(): void {
		$file = $this->createFile();

		$this->policyService
			->method('resolveForUser')
			->willReturn($this->createResolvedPolicy(SignatureRejectionPolicyValue::defaults()));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('Signature rejection is disabled by policy and cannot be enabled for this document.');

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => true]],
		]);
	}

	public function testAnUnrelatedDraftUpdateKeepsTheStoredValue(): void {
		$file = $this->createFile(FileStatus::DRAFT->value, SignatureRejectionPolicyValue::defaults());

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['name' => 'a new name']);

		$this->assertFalse($this->storedValueOf($file)['enabled']);
	}

	public function testAnUnrelatedDraftUpdateKeepsAnEnabledStoredValue(): void {
		$file = $this->createFile(FileStatus::DRAFT->value, ['enabled' => true, 'comment_mode' => 'required']);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->storedValueOf($file)['enabled']);
		$this->assertSame('required', $this->storedValueOf($file)['comment_mode']);
	}

	public function testRequesterCanOptInWhileTheRequestIsADraft(): void {
		$file = $this->createFile(FileStatus::DRAFT->value, SignatureRejectionPolicyValue::defaults());
		$administrative = ['enabled' => true, 'comment_mode' => 'optional'];
		$this->stubResolution('resolveForUserId', $administrative);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => true]],
		]);

		$this->assertSame(
			SignatureRejectionPolicyValue::normalize($administrative),
			$this->storedValueOf($file),
		);
	}

	public function testRequesterCanOptOutAgainWhileTheRequestIsADraft(): void {
		$file = $this->createFile(FileStatus::DRAFT->value, ['enabled' => true, 'comment_mode' => 'optional']);
		$this->stubResolution('resolveForUserId', ['enabled' => true, 'comment_mode' => 'optional']);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => false]],
		]);

		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->storedValueOf($file));
	}

	public function testADraftWithoutASnapshotRecordsTheDisabledDefault(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUserId', ['enabled' => true]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);

		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->storedValueOf($file));
	}

	#[DataProvider('provideStartedFlowStatuses')]
	public function testStoredValueIsFrozenOnceTheSigningFlowStarted(int $fileStatus): void {
		$file = $this->createFile($fileStatus, ['enabled' => true, 'comment_mode' => 'optional']);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->storedValueOf($file)['enabled']);
	}

	/**
	 * @return iterable<string, array{0: int}>
	 */
	public static function provideStartedFlowStatuses(): iterable {
		yield 'able to sign' => [FileStatus::ABLE_TO_SIGN->value];
		yield 'partially signed' => [FileStatus::PARTIAL_SIGNED->value];
		yield 'signed' => [FileStatus::SIGNED->value];
	}

	public function testALaterPolicyChangeDoesNotAlterAStartedRequest(): void {
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value, ['enabled' => true, 'comment_mode' => 'required']);

		$this->policyService->expects($this->never())->method('resolveForUserId');

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->storedValueOf($file)['enabled']);
		$this->assertSame('required', $this->storedValueOf($file)['comment_mode']);
	}

	#[DataProvider('provideFrozenChangeAttempts')]
	public function testSendingTheValueAfterTheFlowStartedIsRefused(?array $storedValue, bool $requestedChoice): void {
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value, $storedValue);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signature rejection setting cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => $requestedChoice]],
		]);
	}

	/**
	 * @return iterable<string, array{0: ?array<string, mixed>, 1: bool}>
	 */
	public static function provideFrozenChangeAttempts(): iterable {
		yield 'turning it off' => [['enabled' => true, 'comment_mode' => 'optional'], false];
		yield 'turning it on' => [SignatureRejectionPolicyValue::defaults(), true];
		yield 'turning it on without any snapshot' => [null, true];
		yield 'resending the value it already has' => [['enabled' => true, 'comment_mode' => 'optional'], true];
	}

	public function testTheSettingMayNotBeSentAtAllAfterTheFlowStarted(): void {
		// Refusing any value, and not only a different one, keeps a single document
		// and an envelope behaving the same way.
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value, ['enabled' => true, 'comment_mode' => 'optional']);

		$this->fileService->expects($this->never())->method('update');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signature rejection setting cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => true]],
		]);
	}

	public function testAStartedRequestWithoutASnapshotRecordsTheDisabledDefault(): void {
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value);
		$this->stubResolution('resolveForUserId', ['enabled' => true]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);

		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->storedValueOf($file));
	}

	#[DataProvider('provideEnvelopeStatusesWithoutAChoice')]
	public function testAnUnrelatedEnvelopeUpdateNeverTouchesTheStoredValue(int $envelopeStatus): void {
		// The value of an envelope lives on the documents it contains, and updating
		// an envelope never re-synchronizes them, so nothing may be written here.
		$envelope = $this->createEnvelope($envelopeStatus);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->policyService->expects($this->never())->method('resolveForUser');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($envelope, ['name' => 'a new name']);

		$this->assertFalse($this->hasStoredValue($envelope));
	}

	/**
	 * @return iterable<string, array{0: int}>
	 */
	public static function provideEnvelopeStatusesWithoutAChoice(): iterable {
		yield 'draft envelope' => [FileStatus::DRAFT->value];
		yield 'envelope whose flow started' => [FileStatus::ABLE_TO_SIGN->value];
		yield 'partially signed envelope' => [FileStatus::PARTIAL_SIGNED->value];
	}

	public function testRequesterCanChangeTheChoiceOnAnEnvelopeDraft(): void {
		$envelope = $this->createEnvelope();
		$administrative = ['enabled' => true, 'comment_mode' => 'required'];
		$this->stubResolution('resolveForUserId', $administrative);

		$this->fileService->expects($this->once())->method('update')->with($envelope);

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => true]],
		]);

		$this->assertSame(
			SignatureRejectionPolicyValue::normalize($administrative),
			$this->storedValueOf($envelope),
		);
	}

	public function testRequesterCanTurnTheChoiceOffOnAnEnvelopeDraft(): void {
		$envelope = $this->createEnvelope();
		$this->stubResolution('resolveForUserId', ['enabled' => true, 'comment_mode' => 'optional']);

		$this->fileService->expects($this->once())->method('update')->with($envelope);

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => false]],
		]);

		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->storedValueOf($envelope));
	}

	#[DataProvider('provideEnvelopeChangeAttempts')]
	public function testChangingTheChoiceOnAStartedEnvelopeIsRefused(int $envelopeStatus, bool $requestedChoice): void {
		$envelope = $this->createEnvelope($envelopeStatus);

		$this->fileService->expects($this->never())->method('update');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signature rejection setting cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY => ['enabled' => $requestedChoice]],
		]);
	}

	/**
	 * @return iterable<string, array{0: int, 1: bool}>
	 */
	public static function provideEnvelopeChangeAttempts(): iterable {
		yield 'turning it on once the flow started' => [FileStatus::ABLE_TO_SIGN->value, true];
		yield 'turning it off once the flow started' => [FileStatus::ABLE_TO_SIGN->value, false];
		yield 'turning it on once partially signed' => [FileStatus::PARTIAL_SIGNED->value, true];
	}

	public function testApplierParticipatesInCoreFlowSync(): void {
		$this->assertTrue($this->getApplier()->supportsCoreFlowSync());
	}
}
