<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use DateTime;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SigningWorkflowStatusResolverTest extends TestCase {
	private IdDocsMapper&MockObject $idDocsMapper;
	private SignatureRejectionPolicyService&MockObject $signatureRejectionPolicyService;
	private SignatureRejectionBehavior $rejectionBehavior = SignatureRejectionBehavior::CANCEL;

	public function setUp(): void {
		parent::setUp();
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->signatureRejectionPolicyService = $this->createMock(SignatureRejectionPolicyService::class);
		$this->signatureRejectionPolicyService
			->method('getBehavior')
			->willReturnCallback(fn (): SignatureRejectionBehavior => $this->rejectionBehavior);
	}

	private function getResolver(): SigningWorkflowStatusResolver {
		return new SigningWorkflowStatusResolver(
			$this->idDocsMapper,
			$this->signatureRejectionPolicyService,
		);
	}

	private static function file(int $id = 1): FileEntity {
		$file = new FileEntity();
		$file->setId($id);
		return $file;
	}

	private function withoutIdentificationDocument(): void {
		$this->idDocsMapper
			->method('getByFileId')
			->willThrowException(new DoesNotExistException('no identification document'));
	}

	public function testNoSignersLeavesTheStatusUnchanged(): void {
		$this->withoutIdentificationDocument();

		$this->assertNull($this->getResolver()->resolve(self::file(), []));
	}

	public function testIdDocApprovalReachesSignedWhenUploaderPlaceholderIsUnsigned(): void {
		$uploader = new SignRequest();
		$uploader->setId(10);
		$uploader->setStatus(SignRequestStatus::DRAFT->value);

		$approver = new SignRequest();
		$approver->setId(20);
		$approver->setSigned(new DateTime());
		$approver->setStatus(SignRequestStatus::SIGNED->value);

		$idDocs = new IdDocs();
		$idDocs->setSignRequestId(10);
		$this->idDocsMapper
			->method('getByFileId')
			->with(1)
			->willReturn($idDocs);

		$status = $this->getResolver()->resolve(self::file(1), [$uploader, $approver]);

		$this->assertSame(FileStatus::SIGNED, $status);
	}

	public function testDraftSignersStillCountWhenFileIsNotAnIdentificationDocument(): void {
		$this->withoutIdentificationDocument();

		$signed = new SignRequest();
		$signed->setId(1);
		$signed->setSigned(new DateTime());

		$draft = new SignRequest();
		$draft->setId(2);
		$draft->setStatus(SignRequestStatus::DRAFT->value);

		$status = $this->getResolver()->resolve(self::file(99), [$signed, $draft]);

		$this->assertSame(FileStatus::PARTIAL_SIGNED, $status);
	}

	public function testUnsignedObserversAreIgnored(): void {
		$this->withoutIdentificationDocument();

		$signed = new SignRequest();
		$signed->setId(1);
		$signed->setSigned(new DateTime());

		$observer = new SignRequest();
		$observer->setId(2);
		$observer->setParticipantRole(ParticipantRole::OBSERVER->value);

		$status = $this->getResolver()->resolve(self::file(99), [$signed, $observer]);

		$this->assertSame(FileStatus::SIGNED, $status);
	}

	/**
	 * @param array{signed: int, rejected: int, pending: int} $signers
	 */
	#[DataProvider('providerCompletionAfterARejection')]
	public function testTheWorkflowCompletesOnlyWhenNobodyElseIsExpectedToAct(
		SignatureRejectionBehavior $behavior,
		array $signers,
		?FileStatus $expectedStatus,
	): void {
		$this->withoutIdentificationDocument();
		$this->rejectionBehavior = $behavior;

		$status = $this->getResolver()->resolve(self::file(), self::generateSignersWithRejections(
			$signers['signed'],
			$signers['rejected'],
			$signers['pending'],
		));

		$this->assertSame($expectedStatus, $status);
	}

	public static function providerCompletionAfterARejection(): array {
		return [
			'continue: everybody acted and someone signed' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 2, 'rejected' => 1, 'pending' => 0],
				FileStatus::SIGNED,
			],
			'continue: someone can still sign' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 1, 'rejected' => 1, 'pending' => 1],
				FileStatus::PARTIAL_SIGNED,
			],
			'continue: everybody rejected, so no signature was applied' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 0, 'rejected' => 2, 'pending' => 0],
				null,
			],
			'continue: a single signer who rejected' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 0, 'rejected' => 1, 'pending' => 0],
				null,
			],
			'continue: a rejection before anybody signed keeps the file open' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 0, 'rejected' => 1, 'pending' => 1],
				null,
			],
			'cancel: a rejection closes the workflow instead of completing it' => [
				SignatureRejectionBehavior::CANCEL,
				['signed' => 1, 'rejected' => 1, 'pending' => 0],
				FileStatus::PARTIAL_SIGNED,
			],
			'no rejection at all keeps the plain count' => [
				SignatureRejectionBehavior::CONTINUE,
				['signed' => 2, 'rejected' => 0, 'pending' => 1],
				FileStatus::PARTIAL_SIGNED,
			],
		];
	}

	/** @return list<SignRequest> */
	private static function generateSignersWithRejections(int $signed, int $rejected, int $pending): array {
		$signers = [];
		$id = 0;
		for ($i = 0; $i < $signed; $i++) {
			$signer = new SignRequest();
			$signer->setId(++$id);
			$signer->setSigned(new DateTime());
			$signer->setStatus(SignRequestStatus::SIGNED->value);
			$signers[] = $signer;
		}
		for ($i = 0; $i < $rejected; $i++) {
			$signer = new SignRequest();
			$signer->setId(++$id);
			$signer->setStatus(SignRequestStatus::REJECTED->value);
			$signers[] = $signer;
		}
		for ($i = 0; $i < $pending; $i++) {
			$signer = new SignRequest();
			$signer->setId(++$id);
			$signer->setStatus(SignRequestStatus::ABLE_TO_SIGN->value);
			$signers[] = $signer;
		}

		return $signers;
	}
}
