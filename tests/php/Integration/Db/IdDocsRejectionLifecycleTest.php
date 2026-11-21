<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDoc\IdDocsService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rejecting an identification document must retain its audit record without
 * blocking a new upload of the same type or exposing the reviewer to signers.
 *
 * @group DB
 */
final class IdDocsRejectionLifecycleTest extends TestCase {
	private FileMapper $files;
	private IdDocsMapper $documents;
	private SignRequestMapper $requests;
	private IdentityDocumentValidator $validator;
	private IdDocsService $service;
	private IDBConnection $db;
	/** @var list<File> */
	private array $createdFiles = [];
	/** @var list<SignRequest> */
	private array $createdRequests = [];

	protected function setUp(): void {
		parent::setUp();
		$this->files = Server::get(FileMapper::class);
		$this->documents = Server::get(IdDocsMapper::class);
		$this->requests = Server::get(SignRequestMapper::class);
		$this->validator = Server::get(IdentityDocumentValidator::class);
		$this->service = Server::get(IdDocsService::class);
		$this->db = Server::get(IDBConnection::class);
		$this->db->beginTransaction();
	}

	protected function tearDown(): void {
		try {
			$this->db->rollBack();
		} finally {
			// These mappers cache insertions before commit. A rolled-back fixture
			// must not leave entries visible to the next test through that cache.
			foreach ($this->createdFiles as $file) {
				$this->files->invalidateCachedEntity($file);
			}
			foreach ($this->createdRequests as $request) {
				$this->requests->invalidateCachedEntity($request);
			}
			parent::tearDown();
		}
	}

	#[DataProvider('signerTypes')]
	public function testRejectionRetainsAuditAndAllowsExactlyOneActiveReplacement(bool $accountSigner): void {
		[$request, $original, $document] = $this->submittedDocument($accountSigner);
		$this->assertReplacementIsBlocked($accountSigner, $request->getId());

		$rejectedAt = new \DateTime('2026-11-11T12:30:00+00:00');
		$reason = 'The photo ID has expired — please upload a current one.';
		$this->assertTrue($this->documents->rejectIfPending($document, 'reviewer-one', $reason, $rejectedAt));

		// A second reviewer cannot replace the original audit reason or reviewer.
		$this->assertFalse($this->documents->rejectIfPending(
			$this->documents->getByFileId($original->getId()),
			'reviewer-two',
			'Unrelated explanation',
			new \DateTime('2026-11-12T12:30:00+00:00'),
		));
		$audited = $this->documents->getByFileId($original->getId());
		$this->assertSame('reviewer-one', $audited->getRejectedBy());
		$this->assertSame($reason, $audited->getRejectionReason());
		$this->assertSame($rejectedAt->format('Y-m-d H:i:s'), $audited->getRejectedAt()->format('Y-m-d H:i:s'));

		// The rejected file is historical, not an active document blocking re-upload.
		$this->assertNull($this->activeDocument($accountSigner, $request->getId()));
		$this->validateReplacement($accountSigner, $request->getId());
		$replacement = $this->newFile(2);
		// Account uploads create their own signing request; external replacements
		// remain attached to the original request the signer is completing.
		$replacementRequest = $accountSigner ? $this->newRequest($replacement, 2) : $request;
		$replacementDocument = $this->documents->save(
			$replacement->getId(),
			$replacementRequest->getId(),
			$accountSigner ? 'synthetic-signer' : null,
			'IDENTIFICATION',
		);
		$this->assertSame($replacementDocument->getId(), $this->activeDocument($accountSigner, $request->getId())?->getId());
		$this->assertReplacementIsBlocked($accountSigner, $request->getId());

		// Only the replacement participates in signing; the rejected upload stays auditable.
		$activeFiles = $accountSigner
			? $this->documents->getFilesOfAccount('synthetic-signer')
			: $this->documents->getFilesOfSignRequest($request->getId());
		$this->assertSame([$replacement->getId()], array_map(
			static fn (File $file): int => $file->getId(),
			$activeFiles,
		));
		$filter = $this->filter($accountSigner, $request->getId());
		$reviewerRows = $this->service->list($filter, page: 1, length: 10, includeReviewer: true)['data'];
		$this->assertCount(2, $reviewerRows);
		$rejectedRows = array_values(array_filter($reviewerRows, static fn (array $row): bool => $row['rejection'] !== null));
		$this->assertCount(1, $rejectedRows);
		$this->assertSame('rejected', $rejectedRows[0]['file']['statusText']);
		$this->assertSame($reason, $rejectedRows[0]['rejection']['reason']);
		$this->assertSame('reviewer-one', $rejectedRows[0]['rejection']['by']);
		$this->assertSame($original->getUuid(), $rejectedRows[0]['file']['uuid']);

		// Rejecting the identification document is not a rejection or cancellation
		// of the underlying signature request.
		$signingRequest = $this->requests->getByIdUncached($request->getId());
		$this->assertSame($request->getStatus(), $signingRequest->getStatus());
		$this->assertNull($signingRequest->getRejectedAt());
		$this->assertNull($signingRequest->getSigned());

		$signerRows = $this->service->list($filter, page: 1, length: 10)['data'];
		$signerRejected = array_values(array_filter($signerRows, static fn (array $row): bool => $row['rejection'] !== null));
		$this->assertCount(1, $signerRejected);
		$this->assertSame($reason, $signerRejected[0]['rejection']['reason']);
		$this->assertArrayNotHasKey('by', $signerRejected[0]['rejection']);
	}

	public static function signerTypes(): array {
		return [
			'identified Nextcloud account' => [true],
			'external signer with a request identifier' => [false],
		];
	}

	public function testRejectedExternalDocumentCannotBeDeletedOrRemovedFromHistory(): void {
		[$request, $file, $document] = $this->submittedDocument(false);
		$this->assertTrue($this->documents->rejectIfPending(
			$document,
			'reviewer',
			'Wrong identification document',
			new \DateTime('2026-11-11T12:30:00+00:00'),
		));
		try {
			$this->service->deleteIdDocBySignRequest($file->getNodeId(), $request);
			$this->fail('Rejected identification documents must remain in the audit history.');
		} catch (LibresignException $exception) {
			$this->assertSame('Rejected documents are retained for audit purposes.', $exception->getMessage());
		}
		$this->assertSame($file->getId(), $this->files->getById($file->getId())->getId());
		$this->assertSame('Wrong identification document', $this->documents->getByFileId($file->getId())->getRejectionReason());
	}

	public function testRejectingOneRequestDoesNotAffectAnotherRequestWithSameDocumentType(): void {
		[$firstRequest, $firstFile, $firstDocument] = $this->submittedDocument(false);
		$secondFile = $this->newFile(2);
		$secondRequest = $this->newRequest($secondFile, 2);
		$secondDocument = $this->documents->save($secondFile->getId(), $secondRequest->getId(), null, 'IDENTIFICATION');

		$this->assertTrue($this->documents->rejectIfPending(
			$firstDocument,
			'reviewer',
			'Expired document',
			new \DateTime('2026-11-11T12:30:00+00:00'),
		));
		$this->assertNull($this->documents->getActiveBySignRequestAndType($firstRequest->getId(), 'IDENTIFICATION'));
		$this->assertSame($secondDocument->getId(), $this->documents->getActiveBySignRequestAndType($secondRequest->getId(), 'IDENTIFICATION')?->getId());
		$this->assertNull($this->documents->getByFileId($secondFile->getId())->getRejectedAt());
		$this->assertSame($firstFile->getId(), $this->documents->getByFileId($firstFile->getId())->getFileId());
	}

	/** @return array{SignRequest, File, IdDocs} */
	private function submittedDocument(bool $accountSigner): array {
		$file = $this->newFile(1);
		$request = $this->newRequest($file);
		$document = $this->documents->save(
			$file->getId(),
			$request->getId(),
			$accountSigner ? 'synthetic-signer' : null,
			'IDENTIFICATION',
		);
		return [$request, $file, $document];
	}

	private function newFile(int $sequence): File {
		$file = new File();
		$file->setNodeId(94000 + $sequence);
		$file->setUserId('synthetic-owner');
		$file->setUuid(sprintf('e000000%d-0000-4000-8000-000000000001', $sequence));
		$file->setCreatedAt(new \DateTime(sprintf('2026-11-%02dT12:00:00+00:00', $sequence)));
		$file->setName($sequence === 1 ? 'Expired identification.pdf' : 'Replacement identification.pdf');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$created = $this->files->insert($file);
		$this->createdFiles[] = $created;
		return $created;
	}

	private function newRequest(File $file, int $sequence = 1): SignRequest {
		$request = new SignRequest();
		$request->setFileId($file->getId());
		$request->setDisplayName('Synthetic Signer');
		$request->setUuid(sprintf('f000000%d-0000-4000-8000-000000000001', $sequence));
		$request->setCreatedAt(new \DateTime('2026-11-01T12:00:00+00:00'));
		$created = $this->requests->insert($request);
		$this->createdRequests[] = $created;
		return $created;
	}

	private function activeDocument(bool $accountSigner, int $requestId): ?IdDocs {
		return $accountSigner
			? $this->documents->getByUserAndType('synthetic-signer', 'IDENTIFICATION')
			: $this->documents->getActiveBySignRequestAndType($requestId, 'IDENTIFICATION');
	}

	private function validateReplacement(bool $accountSigner, int $requestId): void {
		if ($accountSigner) {
			$this->validator->validateUserHasNoFileWithThisType('synthetic-signer', 'IDENTIFICATION');
		} else {
			$this->validator->validateSignRequestHasNoActiveFileWithType($requestId, 'IDENTIFICATION');
		}
	}

	private function assertReplacementIsBlocked(bool $accountSigner, int $requestId): void {
		try {
			$this->validateReplacement($accountSigner, $requestId);
			$this->fail('Only one active identification document of each type is allowed.');
		} catch (LibresignException $exception) {
			$this->assertSame('A file of this type has been associated.', $exception->getMessage());
		}
	}

	private function filter(bool $accountSigner, int $requestId): array {
		return $accountSigner
			? ['userId' => 'synthetic-signer']
			: ['signRequestId' => $requestId];
	}
}
