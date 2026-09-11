<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\DocMdp\Validator as DocMdpValidator;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\RuntimeRequirementValidator;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicyService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignRequestMapper $signRequestMapper;
	private FileMapper $fileMapper;
	private IdDocsMapper $idDocsMapper;
	private IdentifyMethodService $identifyMethodService;
	private SequentialSigningService $sequentialSigningService;
	private DocMdpValidator $docMdpValidator;
	private PolicyService&MockObject $policyService;
	private ObserverProfilePolicyService $observerProfilePolicyService;
	private SignerValidator $validator;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$this->docMdpValidator = $this->createMock(DocMdpValidator::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$resolvedPolicy = (new ResolvedPolicy())
			->setEffectiveValue(true);
		$this->policyService->method('resolve')->with(ObserverProfilePolicy::KEY)->willReturn($resolvedPolicy);
		$this->observerProfilePolicyService = new ObserverProfilePolicyService($this->policyService);
		$this->validator = new SignerValidator(
			$l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->idDocsMapper,
			$this->identifyMethodService,
			$this->sequentialSigningService,
			$this->docMdpValidator,
			$this->createMock(RuntimeRequirementValidator::class),
			$this->observerProfilePolicyService,
		);
	}

	#[DataProvider('uuidCases')]
	public function testValidatesUuidFormat(string $uuid, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateUuidFormat($uuid);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function uuidCases(): array {
		return [
			'valid' => ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', true],
			'uppercase valid' => ['AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA', true],
			'empty' => ['', false],
			'malformed' => ['not-a-uuid', false],
		];
	}

	public function testNormalizesIdentifyMethodsWithoutChangingPublicShape(): void {
		$signers = [[
			'displayName' => 'Alice',
			'identifyMethods' => [['method' => 'email', 'value' => 'alice@example.com']],
		]];
		$this->assertSame($signers, $this->validator->normalizeRequestSigners($signers));
	}

	#[DataProvider('invalidSignerCases')]
	public function testRejectsInvalidSignerPayload(array $signer, string $message): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($message);
		$this->validator->normalizeRequestSigners([$signer]);
	}

	public static function invalidSignerCases(): array {
		return [
			'no identify methods' => [['displayName' => 'Alice'], 'No identify methods'],
			'invalid identify structure' => [['identifyMethods' => [['method' => 'email']]], 'Invalid identify method structure'],
		];
	}

	public function testRejectsNonArraySignersPayload(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No signers');
		$this->validator->validateIdentifySigners(['signers' => 'invalid']);
	}

	public function testRejectsDisplayNameLongerThan64Characters(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Display name must not be longer than 64 characters');
		$this->validator->validateIdentifySigners([
			'signers' => [[
				'displayName' => str_repeat('A', 65),
				'identifyMethods' => [['method' => 'email', 'value' => 'alice@example.com']],
			]],
		]);
	}

	public function testIdentifySignerChecksDocMdpAndIdentifyMethod(): void {
		$file = new File();
		$this->fileMapper->method('getByUuid')->with('file-uuid')->willReturn($file);
		$this->docMdpValidator->expects($this->once())->method('validateSignersCount');
		$this->docMdpValidator->expects($this->once())->method('validatePdfRestrictions')->with($file);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->expects($this->once())->method('validateToRequest');
		$identifyMethod->method('getSignatureMethods')->willReturn([$this->createMock(ISignatureMethod::class)]);
		$this->identifyMethodService->method('getInstanceOfIdentifyMethod')->with('email', 'alice@example.com')->willReturn($identifyMethod);

		$this->validator->validateIdentifySigners([
			'uuid' => 'file-uuid',
			'signers' => [['identifyMethods' => [['method' => 'email', 'value' => 'alice@example.com']]]],
		]);
	}

	#[DataProvider('signingOrderCases')]
	public function testSignerRespectsSequentialSigning(bool $ordered, bool $pending, bool $blocked): void {
		$uuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
		$signRequest = $this->signRequest(10, 20, SignRequestStatus::ABLE_TO_SIGN, 3);
		$file = new File();
		$this->signRequestMapper->method('getByUuid')->with($uuid)->willReturn($signRequest);
		$this->fileMapper->method('getById')->with(20)->willReturn($file);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);
		$this->sequentialSigningService->method('isOrderedNumericFlow')->willReturn($ordered);
		$this->sequentialSigningService->method('hasPendingLowerOrderSigners')->with(20, 3)->willReturn($pending);

		if ($blocked) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateSigner($uuid);
		if (!$blocked) {
			$this->addToAssertionCount(1);
		}
	}

	public static function signingOrderCases(): array {
		return [
			'ordered pending' => [true, true, true],
			'ordered ready' => [true, false, false],
			'parallel ignores order' => [false, true, false],
		];
	}

	public function testDraftSignerIsAllowedWhenIdentityDocumentExists(): void {
		$uuid = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
		$signRequest = $this->signRequest(11, 21, SignRequestStatus::DRAFT);
		$this->signRequestMapper->method('getByUuid')->with($uuid)->willReturn($signRequest);
		$this->fileMapper->method('getById')->with(21)->willReturn(new File());
		$this->idDocsMapper->method('getByFileId')->with(21)->willReturn(new IdDocs());

		$this->validator->validateSigner($uuid);
		$this->addToAssertionCount(1);
	}

	public function testDraftSignerIsBlockedWithoutIdentityDocument(): void {
		$uuid = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
		$signRequest = $this->signRequest(12, 22, SignRequestStatus::DRAFT);
		$this->signRequestMapper->method('getByUuid')->with($uuid)->willReturn($signRequest);
		$this->fileMapper->method('getById')->with(22)->willReturn(new File());
		$this->idDocsMapper->method('getByFileId')->with(22)->willThrowException(new DoesNotExistException('not found'));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('not allowed to sign this document yet');
		$this->validator->validateSigner($uuid);
	}

	public function testSignedSignerIsBlocked(): void {
		$uuid = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
		$signRequest = $this->signRequest(13, 23, SignRequestStatus::SIGNED);
		$this->signRequestMapper->method('getByUuid')->with($uuid)->willReturn($signRequest);
		$this->fileMapper->method('getById')->with(23)->willReturn(new File());

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Document already signed');
		$this->validator->validateSigner($uuid);
	}

	public function testValidateSignerBlocksObserverParticipants(): void {
		$uuid = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
		$signRequest = $this->signRequest(12, 22, SignRequestStatus::OBSERVING);
		$signRequest->setParticipantRole(ParticipantRole::OBSERVER->value);
		$this->signRequestMapper->method('getByUuid')->with($uuid)->willReturn($signRequest);
		$this->fileMapper->method('getById')->with(22)->willReturn(new File());

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Observers cannot sign this document');

		$this->validator->validateSigner($uuid);
	}

	public function testValidateIdentifySignersRejectsObserverWhenPolicyDisabled(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');
		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$resolvedPolicy = (new ResolvedPolicy())->setEffectiveValue(false);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyService->method('resolve')->with(ObserverProfilePolicy::KEY)->willReturn($resolvedPolicy);
		$this->observerProfilePolicyService = new ObserverProfilePolicyService($this->policyService);
		$this->rebuildValidator();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Observer participants are not enabled');

		$this->validator->validateIdentifySigners([
			'status' => FileStatus::DRAFT->value,
			'signers' => [[
				'participantRole' => 'observer',
				'identifyMethods' => [
					['method' => 'email', 'value' => 'witness@example.com'],
				],
			]],
		]);
	}

	public function testValidateIdentifySignersUsesObserverPolicySnapshotForExistingRequest(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');
		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				ObserverProfilePolicy::KEY => [
					'effectiveValue' => true,
					'sourceScope' => 'system',
				],
			],
		]);
		$this->fileMapper
			->method('getByUuid')
			->with('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee')
			->willReturn($file);
		$this->policyService->expects($this->never())->method('resolve');

		$this->validator->validateIdentifySigners([
			'uuid' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
			'status' => FileStatus::DRAFT->value,
			'signers' => [[
				'participantRole' => 'observer',
				'identifyMethods' => [
					['method' => 'email', 'value' => 'witness@example.com'],
				],
			]],
		]);

		$this->addToAssertionCount(1);
	}

	public function testValidateIdentifySignersAllowsObserverWhenDisabledSnapshotAndLivePolicyEnabled(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');
		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				ObserverProfilePolicy::KEY => [
					'effectiveValue' => false,
					'sourceScope' => 'system',
				],
			],
		]);
		$this->fileMapper
			->method('getByUuid')
			->with('ffffffff-ffff-ffff-ffff-ffffffffffff')
			->willReturn($file);
		$this->policyService
			->method('resolve')
			->with(ObserverProfilePolicy::KEY)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(true));

		$this->validator->validateIdentifySigners([
			'uuid' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
			'status' => FileStatus::DRAFT->value,
			'signers' => [[
				'participantRole' => 'observer',
				'identifyMethods' => [
					['method' => 'email', 'value' => 'witness@example.com'],
				],
			]],
		]);

		$this->addToAssertionCount(1);
	}

	public function testValidateIdentifySignersRequiresSigningParticipantWhenRequesting(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');
		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('At least one signer is required');

		$this->validator->validateIdentifySigners([
			'status' => FileStatus::ABLE_TO_SIGN->value,
			'signers' => [[
				'participantRole' => 'observer',
				'identifyMethods' => [
					['method' => 'email', 'value' => 'witness@example.com'],
				],
			]],
		]);
	}

	public function testValidateIdentifySignersAllowsObserverOnlyDraft(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');
		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$this->validator->validateIdentifySigners([
			'status' => FileStatus::DRAFT->value,
			'signers' => [[
				'participantRole' => 'observer',
				'identifyMethods' => [
					['method' => 'email', 'value' => 'witness@example.com'],
				],
			]],
		]);

		$this->addToAssertionCount(1);
	}

	private function rebuildValidator(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->validator = new SignerValidator(
			$l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->idDocsMapper,
			$this->identifyMethodService,
			$this->sequentialSigningService,
			$this->docMdpValidator,
			$this->createMock(RuntimeRequirementValidator::class),
			$this->observerProfilePolicyService,
		);
	}

	private function signRequest(int $id, int $fileId, SignRequestStatus $status, ?int $order = null): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId($id);
		$signRequest->setFileId($fileId);
		$signRequest->setStatus($status->value);
		if ($order !== null) {
			$signRequest->setSigningOrder($order);
		}
		return $signRequest;
	}
}
