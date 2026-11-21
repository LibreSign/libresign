<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdDocsRecoveryService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IUserFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IdDocsRecoveryServiceTest extends TestCase {
	private const VALID_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const OTHER_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	private IdDocsMapper&MockObject $idDocsMapper;
	private FileMapper&MockObject $fileMapper;
	private FolderService&MockObject $folderService;
	private IUserManager&MockObject $userManager;
	private IDBConnection&MockObject $db;

	public function setUp(): void {
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->db = $this->createMock(IDBConnection::class);
	}

	private function service(): IdDocsRecoveryService {
		return new IdDocsRecoveryService(
			$this->idDocsMapper,
			$this->fileMapper,
			$this->folderService,
			$this->userManager,
			$this->db,
			$this->createMock(LoggerInterface::class),
		);
	}

	private static function row(array $overrides = []): array {
		return array_merge([
			'id_doc_id' => 1,
			'file_id' => 10,
			'sign_request_id' => 20,
			'file_exists' => true,
			'node_id' => 100,
			'signed_node_id' => null,
			'owner' => 'owner',
			'signed_document_id' => 30,
			'signed_document_owner' => 'owner',
			'signed_document_exists' => true,
		], $overrides);
	}

	private function owner(): IUser&MockObject {
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner');
		$this->userManager->method('get')->willReturnCallback(fn (string $uid) => $uid === 'owner' ? $owner : null);
		$userFolder = $this->createMock(IUserFolder::class);
		$userFolder->method('getPath')->willReturn('/owner/files');
		$this->folderService->method('getUserFolder')->with('owner')->willReturn($userFolder);
		return $owner;
	}

	private function targetFolder(string $path = '/owner/files/LibreSign/recovered-id-doc-10'): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getPath')->willReturn($path);
		return $folder;
	}

	private function source(string|false $hash = self::VALID_HASH): File&MockObject {
		$source = $this->createMock(File::class);
		$source->method('getName')->willReturn('IDENTIFICATION.pdf');
		$source->method('fopen')->willReturnCallback(static fn () => fopen('php://memory', 'r'));
		$source->method('hash')->with('sha256')->willReturn($hash);
		return $source;
	}

	private function copy(int $id, string|false $hash = self::VALID_HASH): File&MockObject {
		$copy = $this->createMock(File::class);
		$copy->method('getId')->willReturn($id);
		$copy->method('hash')->with('sha256')->willReturn($hash);
		return $copy;
	}

	/** Make the mocked connection run the transactional callback. */
	private function lockedFile(int $nodeId, ?int $signedNodeId = null, string $owner = 'owner'): FileEntity {
		$file = new FileEntity();
		$file->setId(10);
		$file->setNodeId($nodeId);
		$file->setSignedNodeId($signedNodeId);
		$file->setUserId($owner);
		$this->fileMapper->method('getByIdForUpdate')->with(10)->willReturn($file);
		return $file;
	}

	public static function provideSkippedRows(): array {
		return [
			'missing file record' => [['file_exists' => false, 'node_id' => null], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'file record missing'],
			'missing file node' => [['node_id' => null], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'file node missing'],
			'signed copy present' => [['signed_node_id' => 101], IdDocsRecoveryService::STATUS_AMBIGUOUS, 'document has a signed copy'],
			'owner not stored' => [['owner' => null], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'owner missing'],
			'owner account deleted' => [['owner' => 'deleted', 'signed_document_owner' => 'deleted'], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'owner account missing'],
			'sign request deleted' => [['sign_request_id' => null], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'sign request missing'],
			'signed document deleted' => [['signed_document_exists' => false, 'signed_document_owner' => null], IdDocsRecoveryService::STATUS_UNRECOVERABLE, 'signed document missing'],
			'owner mismatch' => [['signed_document_owner' => 'someone-else'], IdDocsRecoveryService::STATUS_AMBIGUOUS, 'owner differs from signed document owner'],
		];
	}

	#[DataProvider('provideSkippedRows')]
	public function testSkipsRowsWithoutReliableReferences(array $overrides, string $status, string $reason): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row($overrides)]);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFileByNodeId');
		$this->folderService->expects($this->never())->method('getFolderForUserFile');
		$this->fileMapper->expects($this->never())->method('update');

		$result = $this->service()->run(true)[0];

		$this->assertSame($status, $result['status']);
		$this->assertSame($reason, $result['reason']);
	}

	public function testReportsDocumentReadableByOwnerAsAlreadyCorrect(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->folderService->method('getReadableNodeById')->with('owner', 100)->willReturn($this->createMock(File::class));
		$this->folderService->expects($this->never())->method('getFolderForUserFile');
		$this->fileMapper->expects($this->never())->method('update');

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_ALREADY_CORRECT, $result['status']);
	}

	public function testSkipsNodeSharedBySeveralFileRecords(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->with(100)->willReturn(2);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFileByNodeId');

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_AMBIGUOUS, $result['status']);
		$this->assertSame('node referenced by several file records', $result['reason']);
	}

	public function testReportsMissingSourceAsUnrecoverable(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$this->folderService->method('getUnauthenticatedFileByNodeId')->willThrowException(new NotFoundException());

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_UNRECOVERABLE, $result['status']);
		$this->assertSame('source file missing', $result['reason']);
	}

	public function testDryRunReportsAffectedDocumentWithoutCopying(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$source = $this->source();
		$source->expects($this->never())->method('fopen');
		$this->folderService->method('getUnauthenticatedFileByNodeId')->with(100)->willReturn($source);
		$this->folderService->expects($this->never())->method('getFolderForUserFile');
		$this->fileMapper->expects($this->never())->method('update');

		$result = $this->service()->run(false)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_AFFECTED, $result['status']);
		$this->assertArrayNotHasKey('newNodeId', $result);
	}

	public function testExecuteCopiesIntoOwnerFolderAndPointsTheRecordToTheCopy(): void {
		$owner = $this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$source = $this->source();
		$source->expects($this->never())->method('delete');
		$source->expects($this->never())->method('move');
		$this->folderService->method('getUnauthenticatedFileByNodeId')->willReturn($source);
		$copy = $this->copy(200);
		$folder = $this->targetFolder();
		$folder->expects($this->once())->method('newFile')
			->with('IDENTIFICATION.pdf', $this->isType('resource'))
			->willReturn($copy);
		$folder->expects($this->never())->method('delete');
		$this->folderService->expects($this->once())->method('getFolderForUserFile')
			->with($owner, ['name' => 'recovered-id-doc-10'])
			->willReturn($folder);
		$this->folderService->method('getReadableNodeById')->willReturnMap([
			['owner', 100, null],
			['owner', 200, $copy],
		]);
		$file = $this->lockedFile(100);
		$this->fileMapper->expects($this->once())->method('update')->with($file);

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_RECOVERED, $result['status']);
		$this->assertSame(200, $result['newNodeId']);
		$this->assertSame(200, $file->getNodeId());
	}

	public static function provideFailedRecoveries(): array {
		return [
			'content differs' => [self::OTHER_HASH, true, 100, null, 'content verification failed'],
			'copy hash unavailable' => [false, true, 100, null, 'content verification failed'],
			'both hashes unavailable' => [false, true, 100, null, 'content verification failed', 'owner', false],
			'source hash unavailable' => [self::VALID_HASH, true, 100, null, 'content verification failed', 'owner', false],
			'copy not readable by owner' => [self::VALID_HASH, false, 100, null, 'copy not readable in owner storage'],
			'record changed concurrently' => [self::VALID_HASH, true, 999, null, 'file record changed during recovery'],
			'record signed concurrently' => [self::VALID_HASH, true, 100, 555, 'file record changed during recovery'],
			'owner changed concurrently' => [self::VALID_HASH, true, 100, null, 'file record changed during recovery', 'another-owner'],
		];
	}

	#[DataProvider('provideFailedRecoveries')]
	public function testFailedRecoveryRemovesTheCopyAndKeepsTheRecord(string|false $copyHash, bool $copyReadable, int $lockedNodeId, ?int $lockedSignedNodeId, string $reason, string $lockedOwner = 'owner', string|false $sourceHash = self::VALID_HASH): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$this->folderService->method('getUnauthenticatedFileByNodeId')->willReturn($this->source($sourceHash));
		$copy = $this->copy(200, $copyHash);
		$folder = $this->targetFolder();
		$folder->method('newFile')->willReturn($copy);
		$folder->expects($this->once())->method('delete');
		$this->folderService->method('getFolderForUserFile')->willReturn($folder);
		$this->folderService->method('getReadableNodeById')->willReturnMap([
			['owner', 100, null],
			['owner', 200, $copyReadable ? $copy : null],
		]);
		$this->lockedFile($lockedNodeId, $lockedSignedNodeId, $lockedOwner);
		$this->fileMapper->expects($this->never())->method('update');

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_FAILED, $result['status']);
		$this->assertSame($reason, $result['reason']);
		$this->assertArrayNotHasKey('leftoverNodeId', $result);
	}

	public function testDoesNotCopyWhenOwnerStorageFallsBackToAppData(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$source = $this->source();
		$source->expects($this->never())->method('fopen');
		$this->folderService->method('getUnauthenticatedFileByNodeId')->willReturn($source);
		$folder = $this->targetFolder('/appdata_x/libresign/LibreSign/recovered-id-doc-10');
		$folder->expects($this->never())->method('newFile');
		$folder->expects($this->once())->method('delete');
		$this->folderService->method('getFolderForUserFile')->willReturn($folder);
		$this->fileMapper->expects($this->never())->method('update');

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_FAILED, $result['status']);
		$this->assertSame('owner storage not available', $result['reason']);
	}

	public function testUnexpectedErrorRollsBackAndReportsLeftoverWhenCleanupFails(): void {
		$this->owner();
		$this->idDocsMapper->method('getExternalSignerDocuments')->willReturn([self::row()]);
		$this->fileMapper->method('countByNodeId')->willReturn(1);
		$this->folderService->method('getUnauthenticatedFileByNodeId')->willReturn($this->source());
		$copy = $this->copy(200);
		$folder = $this->targetFolder();
		$folder->method('getId')->willReturn(300);
		$folder->method('newFile')->willReturn($copy);
		$folder->method('delete')->willThrowException(new \RuntimeException('storage unavailable'));
		$this->folderService->method('getFolderForUserFile')->willReturn($folder);
		$this->folderService->method('getReadableNodeById')->willReturnMap([
			['owner', 100, null],
			['owner', 200, $copy],
		]);
		$this->lockedFile(100);
		$this->fileMapper->method('update')->willThrowException(new \RuntimeException('database unavailable'));
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$result = $this->service()->run(true)[0];

		$this->assertSame(IdDocsRecoveryService::STATUS_FAILED, $result['status']);
		$this->assertSame('unexpected error, see the server log', $result['reason']);
		$this->assertSame(300, $result['leftoverNodeId']);
	}
}
