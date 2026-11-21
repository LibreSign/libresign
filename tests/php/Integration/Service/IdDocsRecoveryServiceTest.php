<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdDocsRecoveryService;
use OCA\Libresign\Tests\Integration\AppDataTestCase;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use Sabre\DAV\UUIDUtil;

/**
 * @group DB
 */
final class IdDocsRecoveryServiceTest extends AppDataTestCase {
	private const CONTENT = "%PDF-1.4\nsynthetic identification document\n%%EOF\n";

	private FileMapper $fileMapper;
	private IdDocsMapper $idDocsMapper;
	private SignRequestMapper $signRequestMapper;
	private FolderService $folderService;
	private IRootFolder $rootFolder;
	private IUser $owner;
	private IUser $otherUser;
	private Folder $legacyFolder;
	private int $signRequestId;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
		$this->idDocsMapper = Server::get(IdDocsMapper::class);
		$this->signRequestMapper = Server::get(SignRequestMapper::class);
		$this->folderService = Server::get(FolderService::class);
		$this->rootFolder = Server::get(IRootFolder::class);

		$suffix = bin2hex(random_bytes(6));
		$this->owner = $this->createAccount('id-docs-owner-' . $suffix, 'test-password');
		$this->otherUser = $this->createAccount('id-docs-other-' . $suffix, 'test-password');
		// Same place the upload wrote to before #8368: a folder under unauthenticated appdata.
		$this->legacyFolder = $this->folderService->getUnauthenticatedFolder()->newFolder('id-docs-recovery-test-' . $suffix);

		$signedDocument = $this->insertFile(1, $this->owner->getUID());
		$signRequest = new SignRequest();
		$signRequest->setFileId($signedDocument->getId());
		$signRequest->setDisplayName('External signer');
		$signRequest->setUuid(UUIDUtil::getUUID());
		$signRequest->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$this->signRequestId = $this->signRequestMapper->insert($signRequest)->getId();
	}

	public function tearDown(): void {
		try {
			if (isset($this->legacyFolder)) {
				$this->legacyFolder->delete();
			}
		} finally {
			parent::tearDown();
		}
	}

	private function insertFile(int $nodeId, ?string $owner): FileEntity {
		$file = new FileEntity();
		$file->setNodeId($nodeId);
		$file->setUserId($owner);
		$file->setUuid(UUIDUtil::getUUID());
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file->setName('IDENTIFICATION');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		return $this->fileMapper->insert($file);
	}

	private function insertIdDoc(int $nodeId, ?string $owner = null, ?int $signRequestId = null): FileEntity {
		$file = $this->insertFile($nodeId, $owner ?? $this->owner->getUID());
		$this->idDocsMapper->save($file->getId(), $signRequestId ?? $this->signRequestId, null, 'IDENTIFICATION');
		return $file;
	}

	private function legacyNode(string $name = 'IDENTIFICATION.pdf'): int {
		return $this->legacyFolder->newFile($name, self::CONTENT)->getId();
	}

	private function recoveredFolders(): array {
		$service = clone $this->folderService;
		$service->setUserId($this->owner->getUID());
		return array_values(array_filter(
			$service->getFolder()->getDirectoryListing(),
			static fn ($node): bool => str_contains($node->getName(), 'recovered-id-doc-'),
		));
	}

	/** @return array<int, array{status: string, reason: string, newNodeId?: int}> */
	private function runRecovery(bool $execute): array {
		$results = [];
		foreach (Server::get(IdDocsRecoveryService::class)->run($execute) as $result) {
			$results[$result['fileId']] = $result;
		}
		return $results;
	}

	public function testDryRunClassifiesDocumentsWithoutChangingFilesOrRecords(): void {
		$orphan = $this->insertIdDoc($this->legacyNode());
		$correctNode = $this->rootFolder->getUserFolder($this->owner->getUID())->newFile('already-correct.pdf', self::CONTENT);
		$correct = $this->insertIdDoc($correctNode->getId());
		$missingSource = $this->insertIdDoc(2147483000);
		$missingOwner = $this->insertIdDoc($this->legacyNode('missing-owner.pdf'), 'deleted-account');
		$brokenSignRequest = $this->insertIdDoc($this->legacyNode('broken.pdf'), null, 2147483001);

		$results = $this->runRecovery(false);

		$this->assertSame(IdDocsRecoveryService::STATUS_AFFECTED, $results[$orphan->getId()]['status']);
		$this->assertSame(IdDocsRecoveryService::STATUS_ALREADY_CORRECT, $results[$correct->getId()]['status']);
		$this->assertSame(['unrecoverable', 'source file missing'], [$results[$missingSource->getId()]['status'], $results[$missingSource->getId()]['reason']]);
		$this->assertSame(['unrecoverable', 'owner account missing'], [$results[$missingOwner->getId()]['status'], $results[$missingOwner->getId()]['reason']]);
		$this->assertSame(['unrecoverable', 'sign request missing'], [$results[$brokenSignRequest->getId()]['status'], $results[$brokenSignRequest->getId()]['reason']]);
		$this->assertSame($orphan->getNodeId(), $this->fileMapper->getByIdForUpdate($orphan->getId())->getNodeId());
		$this->assertSame([], $this->recoveredFolders());
	}

	public function testExecuteMakesTheDocumentReadableOnlyByItsOwnerAndIsIdempotent(): void {
		$legacyNodeId = $this->legacyNode();
		$orphan = $this->insertIdDoc($legacyNodeId);
		$correctNode = $this->rootFolder->getUserFolder($this->owner->getUID())->newFile('already-correct.pdf', self::CONTENT);
		$correct = $this->insertIdDoc($correctNode->getId());
		$accountFileService = Server::get(AccountFileService::class);
		try {
			$accountFileService->getPdfByUuid($orphan->getUuid());
			$this->fail('The orphaned document should not be readable before recovery.');
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
		}

		$results = $this->runRecovery(true);

		$this->assertSame(IdDocsRecoveryService::STATUS_RECOVERED, $results[$orphan->getId()]['status']);
		$this->assertSame(IdDocsRecoveryService::STATUS_ALREADY_CORRECT, $results[$correct->getId()]['status']);
		$newNodeId = $results[$orphan->getId()]['newNodeId'];
		$record = $this->fileMapper->getByIdForUpdate($orphan->getId());
		$this->assertSame($newNodeId, $record->getNodeId());
		$this->assertSame($orphan->getUuid(), $record->getUuid());
		$this->assertSame($this->owner->getUID(), $record->getUserId());
		$this->assertSame($this->signRequestId, $this->idDocsMapper->getByFileId($orphan->getId())->getSignRequestId());
		$this->assertSame($correctNode->getId(), $this->fileMapper->getByIdForUpdate($correct->getId())->getNodeId());
		$this->assertSame(self::CONTENT, $accountFileService->getPdfByUuid($orphan->getUuid())->getContent());
		$this->assertSame(self::CONTENT, $this->folderService->getUnauthenticatedFileByNodeId($legacyNodeId)->getContent());
		$this->assertSame([], $this->rootFolder->getUserFolder($this->otherUser->getUID())->getById($newNodeId));

		$rerun = $this->runRecovery(true);

		$this->assertSame(IdDocsRecoveryService::STATUS_ALREADY_CORRECT, $rerun[$orphan->getId()]['status']);
		$this->assertSame($newNodeId, $this->fileMapper->getByIdForUpdate($orphan->getId())->getNodeId());
		$this->assertCount(1, $this->recoveredFolders());
	}
}
