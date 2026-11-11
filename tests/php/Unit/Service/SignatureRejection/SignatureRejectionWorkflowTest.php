<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionWorkflow;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionWorkflowTest extends TestCase {
	use SignatureRejectionSamples;

	private SignRequestMapper&MockObject $signRequests;
	private FileMapper&MockObject $files;
	private SequentialSigningService&MockObject $sequential;
	private SigningWorkflowStatusResolver&MockObject $statusResolver;
	private FileStatusService&MockObject $statuses;
	private IdentifyMethodService&MockObject $identification;
	private SignatureRejectionWorkflow $workflow;
	/** @var array<int, File> */
	private array $storedFiles = [];

	protected function setUp(): void {
		$this->signRequests = $this->createMock(SignRequestMapper::class);
		$this->files = $this->createMock(FileMapper::class);
		$this->sequential = $this->createMock(SequentialSigningService::class);
		$this->statusResolver = $this->createMock(SigningWorkflowStatusResolver::class);
		$this->statuses = $this->createMock(FileStatusService::class);
		$this->identification = $this->createMock(IdentifyMethodService::class);
		$this->workflow = new SignatureRejectionWorkflow(
			$this->signRequests, $this->files, $this->identification,
			$this->sequential, $this->statusResolver, $this->statuses,
		);
		$this->sequential->method('setFile')->willReturnSelf();
		$this->files->method('getById')
			->willReturnCallback(fn (int $id): File => $this->storedFiles[$id]);
	}

	public function testPlainDocumentTargetsOnlyTheRequestingSigner(): void {
		$signer = $this->signer();
		$this->signRequests->expects($this->never())->method('getByEnvelopeChildrenAndIdentifyMethod');
		$this->signRequests->expects($this->never())->method('getByIdentifyMethodAndFileId');

		$this->assertSame([$signer], $this->workflow->collectSignRequestsToReject($this->file(), $signer));
	}

	public function testEnvelopeIncludesDocumentAndEnvelopeRequestsWithoutDuplicates(): void {
		$original = $this->signer(id: 11);
		$original->setFileId(21);
		$sibling = $this->signer(id: 12);
		$sibling->setFileId(22);
		$envelopeSigner = $this->signer(id: 13);
		$envelopeSigner->setFileId(1);
		$this->signRequests->method('getByEnvelopeChildrenAndIdentifyMethod')->willReturn([$original, $sibling, $sibling]);
		$this->signRequests->method('getByIdentifyMethodAndFileId')->willReturn($envelopeSigner);

		$result = $this->workflow->collectSignRequestsToReject($this->envelope(), $original);

		$this->assertSame([11, 12, 13], array_map(static fn (SignRequest $item): int => $item->getId(), $result));
	}

	public function testAlreadySignedEnvelopeSiblingPreservesItsSignature(): void {
		$original = $this->signer(id: 11);
		$signed = $this->signer(SignRequestStatus::SIGNED->value, 12);
		$signed->setSigned(new \DateTime(self::REJECTED_AT));
		$this->signRequests->method('getByEnvelopeChildrenAndIdentifyMethod')->willReturn([$signed]);
		$this->signRequests->method('getByIdentifyMethodAndFileId')
			->willThrowException(new DoesNotExistException('optional envelope-level signer missing'));

		$this->assertSame([$original], $this->workflow->collectSignRequestsToReject($this->envelope(), $original));
		$this->assertSame(SignRequestStatus::SIGNED, $signed->getStatusEnum());
	}

	public function testDuplicateEnvelopeIdentityFailsBeforeAnyRejectionIsPersisted(): void {
		$signer = $this->signer();
		$this->signRequests->method('getByEnvelopeChildrenAndIdentifyMethod')->willReturn([]);
		$this->signRequests->method('getByIdentifyMethodAndFileId')->willThrowException(
			new \OCP\AppFramework\Db\MultipleObjectsReturnedException('ambiguous signer identity'),
		);
		$this->expectException(\OCP\AppFramework\Db\MultipleObjectsReturnedException::class);

		try {
			$this->workflow->collectSignRequestsToReject($this->envelope(), $signer);
		} finally {
			$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signer->getStatusEnum());
		}
	}

	public function testInvalidEnvelopeIdentificationCannotSilentlySkipRelatedSignatures(): void {
		$signer = $this->signer();
		$this->identification->method('getIdentifiedMethod')->willThrowException(
			new \OCA\Libresign\Exception\LibresignException('Invalid identification method'),
		);
		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);

		$this->workflow->collectSignRequestsToReject($this->envelope(), $signer);
	}

	public function testContinuingWorkflowActivatesNextSignerAndResolvesDocumentStatus(): void {
		$document = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$rejected = $this->signer();
		$next = $this->signer(id: 99);
		$this->sequential->method('activateNextOrder')->willReturn([$next]);
		$this->signRequests->method('getByFileId')->willReturn([$rejected]);
		$this->statusResolver->method('resolve')->willReturn(FileStatus::SIGNED);

		$activated = $this->workflow->continueWorkflow($document, [$rejected]);

		$this->assertSame([$next], $activated);
		$this->assertSame(FileStatus::SIGNED->value, $document->getStatus());
	}

	public function testContinuingWorkflowDoesNotPersistUnchangedStatus(): void {
		$document = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$this->sequential->method('activateNextOrder')->willReturn([]);
		$this->statusResolver->method('resolve')->willReturn(FileStatus::PARTIAL_SIGNED);
		$this->statuses->expects($this->never())->method('update');

		$this->workflow->continueWorkflow($document, [$this->signer()]);
		$this->assertSame(FileStatus::PARTIAL_SIGNED->value, $document->getStatus());
	}

	public function testEnvelopeResolvesChildDocumentsButNotEnvelopeAsAChild(): void {
		$envelope = $this->envelope(FileStatus::PARTIAL_SIGNED->value);
		$first = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$first->setId(21);
		$second = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$second->setId(22);
		$this->storedFiles = [1 => $envelope, 21 => $first, 22 => $second];
		$signers = [];
		foreach ([21, 22, 1] as $id) {
			$signer = $this->signer(id: $id);
			$signer->setFileId($id);
			$signers[] = $signer;
		}
		$resolvedIds = [];
		$this->statusResolver->method('resolve')->willReturnCallback(
			static function (File $file) use (&$resolvedIds): FileStatus {
				$resolvedIds[] = $file->getId();
				return FileStatus::SIGNED;
			},
		);
		$this->sequential->method('activateNextOrder')->willReturn([]);

		$activated = $this->workflow->continueWorkflow($envelope, $signers);

		$this->assertSame([], $activated);
		$this->assertSame([21, 22], $resolvedIds);
		$this->assertSame(FileStatus::SIGNED->value, $first->getStatus());
		$this->assertSame(FileStatus::SIGNED->value, $second->getStatus());
	}

	public function testCancelingAChildClosesItsEnvelopeAndPropagatesToOtherChildren(): void {
		$child = $this->file(parentFileId: 1);
		$envelope = $this->envelope();
		$this->storedFiles[1] = $envelope;
		$this->statuses->expects($this->once())->method('propagateStatusToChildren')
			->with(1, FileStatus::CANCELED->value);

		$this->workflow->cancelWorkflow($child);

		$this->assertSame(FileStatus::CANCELED->value, $child->getStatus());
		$this->assertSame(FileStatus::CANCELED->value, $envelope->getStatus());
	}

	public function testCancelingAnEnvelopePropagatesToItsChildren(): void {
		$envelope = $this->envelope();
		$this->statuses->expects($this->once())->method('propagateStatusToChildren')
			->with(1, FileStatus::CANCELED->value);

		$this->workflow->cancelWorkflow($envelope);
		$this->assertSame(FileStatus::CANCELED->value, $envelope->getStatus());
	}

	public function testFailedSequentialActivationRemainsVisibleToTransactionOwner(): void {
		$this->sequential->method('activateNextOrder')->willThrowException(new \RuntimeException('storage unavailable'));
		$this->expectException(\RuntimeException::class);

		$this->workflow->continueWorkflow($this->file(), [$this->signer()]);
	}

	public function testEnvelopeContinuesSequentialOrderOnEachRejectedDocument(): void {
		$envelope = $this->envelope();
		$documents = [$envelope];
		$signers = [];
		foreach ([21, 22, 1] as $id) {
			$file = $id === 1 ? $envelope : $this->file();
			$file->setId($id);
			$this->storedFiles[$id] = $file;
			$signer = $this->signer(id: $id);
			$signer->setFileId($id);
			$signers[] = $signer;
		}
		$this->statusResolver->method('resolve')->willReturn(null);
		$released = [];
		$this->sequential->method('activateNextOrder')->willReturnCallback(
			static function (int $fileId) use (&$released): array {
				$released[] = $fileId;
				return [];
			},
		);

		$this->assertSame([], $this->workflow->continueWorkflow($envelope, $signers));
		$this->assertSame([21, 22, 1], $released);
	}

	public function testFinalRejectionCanSetWorkflowCanceledWithoutExplicitCancelPolicy(): void {
		$file = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$this->sequential->method('activateNextOrder')->willReturn([]);
		$this->statusResolver->method('resolve')->willReturn(FileStatus::CANCELED);

		$this->workflow->continueWorkflow($file, [$this->signer()]);

		$this->assertSame(FileStatus::CANCELED->value, $file->getStatus());
	}

	public function testAStatusUpdateFailureIsPropagatedForRollback(): void {
		$file = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$this->sequential->method('activateNextOrder')->willReturn([]);
		$this->statusResolver->method('resolve')->willReturn(FileStatus::SIGNED);
		$this->statuses->method('update')->willThrowException(new \RuntimeException('status persistence failed'));
		$this->expectException(\RuntimeException::class);

		$this->workflow->continueWorkflow($file, [$this->signer()]);
	}
}
