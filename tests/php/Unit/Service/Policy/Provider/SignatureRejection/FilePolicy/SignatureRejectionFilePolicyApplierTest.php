<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy\SignatureRejectionFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;
	private FileMapper&MockObject $fileMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $message, array $parameters = []): string => $parameters === []
				? $message
				: vsprintf($message, $parameters),
		);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileMapper->method('getChildrenFiles')->willReturn([]);
	}

	private function getApplier(): SignatureRejectionFilePolicyApplier {
		return new SignatureRejectionFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
			$this->fileMapper,
		);
	}

	/**
	 * Answer like the policy resolver does: the administrative value of each
	 * setting, replaced by the requested one when the layers above allow that
	 * setting to be chosen per request.
	 *
	 * @param array<string, mixed> $administrative
	 * @param list<string> $choosableKeys
	 */
	private function stubResolution(string $method, array $administrative, array $choosableKeys = SignatureRejectionPolicy::ALL_KEYS): void {
		$defaults = SignatureRejectionPolicyConfig::defaults()->toKeyedValues();

		$this->policyService
			->method($method)
			->willReturnCallback(static function (
				string $policyKey,
				mixed $owner = null,
				array $requestOverrides = [],
				?array $activeContext = null,
			) use ($administrative, $choosableKeys, $defaults): ResolvedPolicy {
				$value = $administrative[$policyKey] ?? $defaults[$policyKey];
				$sourceScope = $activeContext === null ? 'system' : 'group';

				if (array_key_exists($policyKey, $requestOverrides) && in_array($policyKey, $choosableKeys, true)) {
					$value = $requestOverrides[$policyKey];
					$sourceScope = 'request';
				}

				return (new ResolvedPolicy())
					->setPolicyKey($policyKey)
					->setEffectiveValue(SignatureRejectionPolicyConfig::normalizeKeyedValue($policyKey, $value))
					->setSourceScope($sourceScope)
					->setCanUseAsRequestOverride(in_array($policyKey, $choosableKeys, true))
					->setBlockedBy(in_array($policyKey, $choosableKeys, true) ? null : 'system');
			});
	}

	/** @param array<string, mixed> $storedValues */
	private function createFile(int $status = FileStatus::DRAFT->value, ?array $storedValues = null, string $storedSourceScope = 'system'): File {
		$file = new File();
		$file->setUserId('requester');
		$file->setStatus($status);
		if ($storedValues !== null) {
			$policySnapshot = [];
			foreach (SignatureRejectionPolicyConfig::fromKeyedValues($storedValues)->toKeyedValues() as $policyKey => $effectiveValue) {
				$policySnapshot[$policyKey] = [
					'effectiveValue' => $effectiveValue,
					'sourceScope' => $storedSourceScope,
				];
			}
			$file->setMetadata(['policy_snapshot' => $policySnapshot]);
		}
		return $file;
	}

	/** @param array<string, mixed> $storedValues */
	private function createEnvelope(int $status = FileStatus::DRAFT->value, ?array $storedValues = null): File {
		$envelope = $this->createFile($status, $storedValues);
		$envelope->setNodeType('envelope');
		return $envelope;
	}

	/** @return array<string, mixed> */
	private function storedValuesOf(File $file): array {
		$storedValues = [];
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			$storedValues[$policyKey] = $file->getMetadata()['policy_snapshot'][$policyKey]['effectiveValue'];
		}

		return $storedValues;
	}

	private function hasStoredValues(File $file): bool {
		return isset($file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY_ENABLED]);
	}

	public function testADocumentRecordsOneSnapshotEntryPerSetting(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'requester',
		]);

		$this->getApplier()->apply($file, []);

		$this->assertSame([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'requester',
		], $this->storedValuesOf($file));
	}

	public function testADocumentWithoutAnyPolicyKeepsRejectionDisabled(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', []);

		$this->getApplier()->apply($file, ['policyOverrides' => ['signer_geolocation' => ['mode' => 'required']]]);

		$this->assertSame(
			SignatureRejectionPolicyConfig::defaults()->toKeyedValues(),
			$this->storedValuesOf($file),
		);
	}

	public function testTheRequesterChoosesInsideWhatThePolicyAllows(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [
				SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			],
		]);

		$storedValues = $this->storedValuesOf($file);
		$this->assertSame('continue', $storedValues[SignatureRejectionPolicy::KEY_BEHAVIOR]);
		$this->assertSame('required', $storedValues[SignatureRejectionPolicy::KEY_COMMENT_MODE]);
		$this->assertSame(
			'request',
			$file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY_BEHAVIOR]['sourceScope'],
		);
	}

	public function testRequesterCannotEnableRejectionWhenThePolicyDisablesIt(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', [], []);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('Signature rejection is disabled by policy and cannot be enabled for this document.');

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_ENABLED => true],
		]);
	}

	public function testRequesterCannotAskForASettingThePolicyEnforces(): void {
		$file = $this->createFile();
		$this->stubResolution(
			'resolveForUser',
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
			],
			[SignatureRejectionPolicy::KEY_ENABLED],
		);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The rejection setting rejection_behavior cannot be used on this document: it is defined by system.');

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue'],
		]);
	}

	public function testTheCommentAudienceCannotBeWiderThanTheRejectionAudience(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
		]);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The rejection comment cannot be visible to a wider audience than the rejection itself.');

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public'],
		]);
	}

	public function testASettingThatCannotApplyIsFrozenAtItsDefault(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUser', [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'disabled',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'public',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
		]);

		$this->getApplier()->apply($file, []);

		$storedValues = $this->storedValuesOf($file);
		$this->assertSame('public', $storedValues[SignatureRejectionPolicy::KEY_VISIBILITY]);
		$this->assertSame('requester', $storedValues[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY]);
	}

	public function testApplyUsesTheActivePolicyContextWhenGiven(): void {
		$file = $this->createFile();
		$this->policyService
			->expects($this->exactly(count(SignatureRejectionPolicy::ALL_KEYS)))
			->method('resolveForUser')
			->willReturnCallback(function (string $policyKey, mixed $user, array $overrides, array $activeContext): ResolvedPolicy {
				$this->assertSame(['type' => 'group', 'id' => 'legal'], $activeContext);

				return (new ResolvedPolicy())
					->setPolicyKey($policyKey)
					->setEffectiveValue(SignatureRejectionPolicyConfig::defaults()->toKeyedValues()[$policyKey])
					->setSourceScope('group');
			});

		$this->getApplier()->apply($file, [
			'policyActiveContext' => ['type' => 'group', 'id' => 'legal'],
		]);

		$this->assertSame(
			'group',
			$file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY_ENABLED]['sourceScope'],
		);
	}

	public function testADraftKeepsTheChoicesTheRequesterAlreadyMade(): void {
		$file = $this->createFile(
			FileStatus::DRAFT->value,
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			],
			'request',
		);
		$this->stubResolution('resolveForUserId', [SignatureRejectionPolicy::KEY_ENABLED => true]);

		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['name' => 'a new name']);

		$storedValues = $this->storedValuesOf($file);
		$this->assertTrue($storedValues[SignatureRejectionPolicy::KEY_ENABLED]);
		$this->assertSame('required', $storedValues[SignatureRejectionPolicy::KEY_COMMENT_MODE]);
	}

	public function testADraftIsRevalidatedAgainstTheCurrentAdministratorPolicy(): void {
		// The requester asked for a required comment while the draft was created;
		// the administrator has since taken that choice away.
		$file = $this->createFile(
			FileStatus::DRAFT->value,
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			],
			'request',
		);
		$this->stubResolution(
			'resolveForUserId',
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			],
			[SignatureRejectionPolicy::KEY_ENABLED],
		);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);

		$this->assertSame('optional', $this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_COMMENT_MODE]);
	}

	public function testRequesterCanChangeTheirChoiceWhileTheRequestIsADraft(): void {
		$file = $this->createFile(FileStatus::DRAFT->value, [SignatureRejectionPolicy::KEY_ENABLED => false]);
		$this->stubResolution('resolveForUserId', [
			SignatureRejectionPolicy::KEY_ENABLED => false,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, [
			'policyOverrides' => [
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			],
		]);

		$this->assertTrue($this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_ENABLED]);
	}

	public function testRequesterCanOptOutAgainWhileTheRequestIsADraft(): void {
		$file = $this->createFile(
			FileStatus::DRAFT->value,
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			],
			'request',
		);
		$this->stubResolution('resolveForUserId', [SignatureRejectionPolicy::KEY_ENABLED => true]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_ENABLED => false],
		]);

		$this->assertSame(
			SignatureRejectionPolicyConfig::defaults()->toKeyedValues(),
			$this->storedValuesOf($file),
		);
	}

	public function testADraftWithoutASnapshotRecordsTheResolvedConfiguration(): void {
		$file = $this->createFile();
		$this->stubResolution('resolveForUserId', [SignatureRejectionPolicy::KEY_ENABLED => true]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_ENABLED]);
	}

	#[DataProvider('provideStartedFlowStatuses')]
	public function testTheConfigurationIsFrozenOnceTheSigningFlowStarted(int $fileStatus): void {
		$file = $this->createFile($fileStatus, [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_ENABLED]);
		$this->assertSame('optional', $this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_COMMENT_MODE]);
	}

	/**
	 * @return iterable<string, array{0: int}>
	 */
	public static function provideStartedFlowStatuses(): iterable {
		yield 'able to sign' => [FileStatus::ABLE_TO_SIGN->value];
		yield 'partially signed' => [FileStatus::PARTIAL_SIGNED->value];
		yield 'signed' => [FileStatus::SIGNED->value];
	}

	#[DataProvider('provideFrozenChangeAttempts')]
	public function testChangingASettingAfterTheFlowStartedIsRefused(?array $storedValues, array $overrides): void {
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value, $storedValues);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signature rejection settings cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($file, ['policyOverrides' => $overrides]);
	}

	/**
	 * @return iterable<string, array{0: ?array<string, mixed>, 1: array<string, mixed>}>
	 */
	public static function provideFrozenChangeAttempts(): iterable {
		yield 'turning it off' => [
			[SignatureRejectionPolicy::KEY_ENABLED => true, SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional'],
			[SignatureRejectionPolicy::KEY_ENABLED => false],
		];
		yield 'turning it on' => [
			[SignatureRejectionPolicy::KEY_ENABLED => false],
			[SignatureRejectionPolicy::KEY_ENABLED => true],
		];
		yield 'turning it on without any snapshot' => [
			null,
			[SignatureRejectionPolicy::KEY_ENABLED => true],
		];
		yield 'changing the comment mode' => [
			[SignatureRejectionPolicy::KEY_ENABLED => true, SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional'],
			[SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required'],
		];
		yield 'widening the audience' => [
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
				SignatureRejectionPolicy::KEY_VISIBILITY => 'requester',
			],
			[SignatureRejectionPolicy::KEY_VISIBILITY => 'public'],
		];
	}

	public function testResendingTheFrozenConfigurationStaysAnIdempotentUpdate(): void {
		// A client may resend the complete form state in an unrelated update;
		// the unchanged values must not make the whole update fail.
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value, [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
		]);

		$this->fileService->expects($this->never())->method('update');
		$this->policyService->expects($this->never())->method('resolveForUserId');

		$this->getApplier()->sync($file, [
			'policyOverrides' => [
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
				SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
			],
		]);

		$this->assertTrue($this->storedValuesOf($file)[SignatureRejectionPolicy::KEY_ENABLED]);
	}

	public function testAStartedRequestWithoutASnapshotRecordsTheResolvedConfiguration(): void {
		$file = $this->createFile(FileStatus::ABLE_TO_SIGN->value);
		$this->stubResolution('resolveForUserId', [SignatureRejectionPolicy::KEY_ENABLED => true]);

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);

		$this->assertTrue($this->hasStoredValues($file));
	}

	#[DataProvider('provideEnvelopeStatusesWithoutAChoice')]
	public function testAnUnrelatedEnvelopeUpdateNeverTouchesTheStoredConfiguration(int $envelopeStatus): void {
		// The configuration of an envelope lives on the documents it contains, and
		// updating an envelope never re-synchronizes them, so nothing may be
		// written here.
		$envelope = $this->createEnvelope($envelopeStatus);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->policyService->expects($this->never())->method('resolveForUser');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($envelope, ['name' => 'a new name']);

		$this->assertFalse($this->hasStoredValues($envelope));
	}

	/**
	 * @return iterable<string, array{0: int}>
	 */
	public static function provideEnvelopeStatusesWithoutAChoice(): iterable {
		yield 'draft envelope' => [FileStatus::DRAFT->value];
		yield 'envelope whose flow started' => [FileStatus::ABLE_TO_SIGN->value];
		yield 'partially signed envelope' => [FileStatus::PARTIAL_SIGNED->value];
	}

	public function testRequesterCanChangeTheConfigurationOnAnEnvelopeDraft(): void {
		$envelope = $this->createEnvelope();
		$this->stubResolution('resolveForUserId', [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
		]);

		$this->fileService->expects($this->once())->method('update')->with($envelope);

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_ENABLED => true],
		]);

		$storedValues = $this->storedValuesOf($envelope);
		$this->assertTrue($storedValues[SignatureRejectionPolicy::KEY_ENABLED]);
		$this->assertSame('required', $storedValues[SignatureRejectionPolicy::KEY_COMMENT_MODE]);
	}

	public function testChangingTheConfigurationOnAStartedEnvelopeIsRefusedAgainstItsDocuments(): void {
		// The envelope has no snapshot of its own: the effective configuration is
		// read from the documents it contains.
		$envelope = $this->createEnvelope(FileStatus::ABLE_TO_SIGN->value);
		$envelope->setId(1);
		$child = $this->createFile(FileStatus::ABLE_TO_SIGN->value, [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);
		$child->setId(2);

		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$child]);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('The signature rejection settings cannot be changed after the signing flow has started.');

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_ENABLED => false],
		]);
	}

	public function testResendingTheFrozenConfigurationOfAStartedEnvelopeIsAccepted(): void {
		$envelope = $this->createEnvelope(FileStatus::ABLE_TO_SIGN->value);
		$envelope->setId(1);
		$child = $this->createFile(FileStatus::ABLE_TO_SIGN->value, [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);
		$child->setId(2);

		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$child]);
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($envelope, [
			'policyOverrides' => [SignatureRejectionPolicy::KEY_ENABLED => true],
		]);

		$this->assertFalse($this->hasStoredValues($envelope));
	}

	public function testApplierParticipatesInCoreFlowSync(): void {
		$this->assertTrue($this->getApplier()->supportsCoreFlowSync());
	}
}
