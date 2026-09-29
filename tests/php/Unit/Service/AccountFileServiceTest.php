<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private FileMapper&MockObject $fileMapper;
	private FolderService&MockObject $folderService;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
	}

	private function getService(): AccountFileService {
		return new AccountFileService($this->signRequestMapper, $this->fileMapper, $this->folderService);
	}

	#[DataProvider('provideUuidLookupSequences')]
	public function testGetSignRequestByUuidResolvesEachRequestedUuid(array $uuids): void {
		$requests = [];
		foreach (['uuid-a', 'uuid-b'] as $uuid) {
			$requests[$uuid] = new SignRequest();
			$requests[$uuid]->setUuid($uuid);
		}
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $requests[$uuid],
		);

		$service = $this->getService();
		foreach ($uuids as $uuid) {
			$this->assertSame($requests[$uuid], $service->getSignRequestByUuid($uuid));
		}
	}

	public static function provideUuidLookupSequences(): array {
		return [
			'repeated UUID' => [['uuid-a', 'uuid-a']],
			'different UUIDs' => [['uuid-a', 'uuid-b']],
			'return to first UUID' => [['uuid-a', 'uuid-b', 'uuid-a']],
		];
	}

	#[DataProvider('provideUuidLookupSequences')]
	public function testGetFileByUuidResolvesEachRequestedUuid(array $uuids): void {
		$requests = [];
		$files = [];
		$nodes = [];
		foreach (['uuid-a' => 10, 'uuid-b' => 20] as $uuid => $fileId) {
			$requests[$uuid] = new SignRequest();
			$requests[$uuid]->setUuid($uuid);
			$requests[$uuid]->setFileId($fileId);
			$files[$fileId] = new \OCA\Libresign\Db\File();
			$files[$fileId]->setId($fileId);
			$files[$fileId]->setUserId('owner-' . $uuid);
			$files[$fileId]->setNodeId($fileId + 1);
			$nodes[$fileId + 1] = $this->createMock(File::class);
		}
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $requests[$uuid],
		);
		$this->fileMapper->method('getById')->willReturnCallback(
			static fn (int $id): \OCA\Libresign\Db\File => $files[$id],
		);
		$this->folderService->method('getReadableNodeById')->willReturnMap([
			['owner-uuid-a', 11, $nodes[11]],
			['owner-uuid-b', 21, $nodes[21]],
		]);

		$service = $this->getService();
		foreach ($uuids as $uuid) {
			$fileId = $requests[$uuid]->getFileId();
			$this->assertSame([
				'fileData' => $files[$fileId],
				'fileToSign' => $nodes[$fileId + 1],
			], $service->getFileByUuid($uuid));
			$this->assertSame($requests[$uuid], $service->getSignRequestByUuid($uuid));
		}
	}

	#[DataProvider('provideMissingFileNodes')]
	public function testGetFileByUuidDoesNotReusePreviousNode(bool $isFolder): void {
		$request = new SignRequest();
		$request->setFileId(10);
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('owner');
		$file->setNodeId(11);
		$firstNode = $this->createMock(File::class);
		$secondNode = $isFolder ? $this->createMock(\OCP\Files\Folder::class) : null;
		$this->signRequestMapper->method('getByUuid')->willReturn($request);
		$this->fileMapper->method('getById')->with(10)->willReturn($file);
		$this->folderService->method('getReadableNodeById')
			->with('owner', 11)->willReturn($firstNode, $secondNode);

		$service = $this->getService();
		$this->assertSame($firstNode, $service->getFileByUuid('uuid-a')['fileToSign']);
		$this->assertSame(['fileData' => $file, 'fileToSign' => null], $service->getFileByUuid('uuid-b'));
	}

	public static function provideMissingFileNodes(): array {
		return [
			'not found' => [false],
			'folder instead of file' => [true],
		];
	}

	#[DataProvider('provideUuidLookupMethods')]
	public function testUuidLookupDoesNotReturnPreviousRequestWhenUuidIsMissing(string $method): void {
		$request = new SignRequest();
		$error = new DoesNotExistException('Unknown UUID');
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $uuid === 'uuid-a' ? $request : throw $error,
		);
		$this->fileMapper->expects($this->never())->method('getById');
		$service = $this->getService();
		$this->assertSame($request, $service->getSignRequestByUuid('uuid-a'));

		$this->expectExceptionObject($error);
		$service->$method('missing-uuid');
	}

	public static function provideUuidLookupMethods(): array {
		return [
			'sign request' => ['getSignRequestByUuid'],
			'file' => ['getFileByUuid'],
		];
	}

	public function testGetFileByUuidRetriesAfterNodeLookupFails(): void {
		$request = new SignRequest();
		$request->setFileId(10);
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('owner');
		$file->setNodeId(11);
		$node = $this->createMock(File::class);
		$error = new NotFoundException('Storage unavailable');
		$this->signRequestMapper->method('getByUuid')->with('uuid-a')->willReturn($request);
		$this->fileMapper->method('getById')->with(10)->willReturn($file);
		$attempts = 0;
		$this->folderService->method('getReadableNodeById')->with('owner', 11)
			->willReturnCallback(static function () use (&$attempts, $node, $error): File {
				if ($attempts++ === 0) {
					throw $error;
				}
				return $node;
			});

		$service = $this->getService();
		try {
			$service->getFileByUuid('uuid-a');
			$this->fail('The storage error must propagate.');
		} catch (NotFoundException $actual) {
			$this->assertSame($error, $actual);
		}
		$this->assertSame(['fileData' => $file, 'fileToSign' => $node], $service->getFileByUuid('uuid-a'));
	}

	#[DataProvider('provideGetPdfByUuidNodeSelection')]
	public function testGetPdfByUuidSelectsExpectedNode(
		int $status,
		?int $signedNodeId,
		int $nodeId,
		int $expectedNodeId,
	): void {
		$libresignFile = new \OCA\Libresign\Db\File();
		$libresignFile->setSignedNodeId($signedNodeId);
		$libresignFile->setNodeId($nodeId);
		$libresignFile->setStatus($status);

		$this->fileMapper->method('getByUuid')->with('uuid')->willReturn($libresignFile);
		$this->fileMapper->method('getStorageUserIdByUuid')->with('uuid')->willReturn('storage-user');
		$this->folderService->expects($this->once())->method('setUserId')->with('storage-user');

		$node = $this->createMock(File::class);
		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with($expectedNodeId)
			->willReturn($node);

		$this->assertSame($node, $this->getService()->getPdfByUuid('uuid'));
	}

	public static function provideGetPdfByUuidNodeSelection(): array {
		return [
			'signed file uses signed node' => [FileStatus::SIGNED->value, 200, 100, 200],
			'partially signed file uses signed node' => [FileStatus::PARTIAL_SIGNED->value, 201, 101, 201],
			'draft file uses original node' => [FileStatus::DRAFT->value, null, 102, 102],
			'draft ignores signed node' => [FileStatus::DRAFT->value, 203, 103, 103],
			'ready file uses original node' => [FileStatus::ABLE_TO_SIGN->value, null, 104, 104],
		];
	}

	public function testGetPdfByUuidResolvesEachDocumentsStorageAndNode(): void {
		$original = new \OCA\Libresign\Db\File();
		$original->setStatus(FileStatus::DRAFT->value);
		$original->setNodeId(11);
		$signed = new \OCA\Libresign\Db\File();
		$signed->setStatus(FileStatus::SIGNED->value);
		$signed->setNodeId(21);
		$signed->setSignedNodeId(22);
		$this->fileMapper->method('getByUuid')->willReturnMap([
			['document-a', $original], ['document-b', $signed],
		]);
		$this->fileMapper->method('getStorageUserIdByUuid')->willReturnMap([
			['document-a', 'owner-a'], ['document-b', 'owner-b'],
		]);
		$storageUser = null;
		$this->folderService->expects($this->exactly(3))->method('setUserId')
			->willReturnCallback(static function (string $userId) use (&$storageUser): void {
				$storageUser = $userId;
			});
		$nodes = [11 => $this->createMock(File::class), 22 => $this->createMock(File::class)];
		$this->folderService->expects($this->exactly(3))->method('getFileByNodeId')
			->willReturnCallback(static function (int $nodeId) use (&$storageUser, $nodes): File {
				self::assertSame($nodeId === 11 ? 'owner-a' : 'owner-b', $storageUser);
				return $nodes[$nodeId];
			});
		$service = $this->getService();
		foreach (['document-a', 'document-b', 'document-a'] as $uuid) {
			$this->assertSame($nodes[$uuid === 'document-a' ? 11 : 22], $service->getPdfByUuid($uuid));
		}
	}

	public function testGetFileByUuidPropagatesMissingDatabaseFile(): void {
		$request = new SignRequest();
		$request->setFileId(10);
		$this->signRequestMapper->method('getByUuid')->with('request-uuid')->willReturn($request);
		$error = new DoesNotExistException('Missing database file');
		$this->fileMapper->method('getById')->with(10)->willThrowException($error);
		$this->folderService->expects($this->never())->method('getReadableNodeById');
		$this->expectExceptionObject($error);
		$this->getService()->getFileByUuid('request-uuid');
	}

	public function testGetPdfByUuidPropagatesUnexpectedStorageErrors(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setStatus(FileStatus::DRAFT->value);
		$file->setNodeId(42);
		$this->fileMapper->method('getByUuid')->with('document-uuid')->willReturn($file);
		$this->fileMapper->method('getStorageUserIdByUuid')->with('document-uuid')->willReturn('owner');
		$this->folderService->expects($this->once())->method('setUserId')->with('owner');
		$error = new \RuntimeException('Storage unavailable');
		$this->folderService->method('getFileByNodeId')->with(42)->willThrowException($error);
		$this->expectExceptionObject($error);
		$this->getService()->getPdfByUuid('document-uuid');
	}

	public function testGetPdfByUuidThrowsDoesNotExistWhenNodeNotFound(): void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => null,
					'getNodeId' => 123,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::DRAFT->value,
				}
			);

		$this->fileMapper
			->expects($this->once())
			->method('getByUuid')
			->with('uuid')
			->willReturn($libresignFile);

		$this->fileMapper
			->expects($this->once())
			->method('getStorageUserIdByUuid')
			->with('uuid')
			->willReturn('guest-user');

		$this->folderService
			->expects($this->once())
			->method('setUserId')
			->with('guest-user');

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willThrowException(new NotFoundException('Invalid node'));

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not found');

		$this->getService()->getPdfByUuid('uuid');
	}

	#[DataProvider('provideSignedStatuses')]
	public function testSignedPdfWithoutNodeDoesNotFallBackToOriginal(int $status): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setStatus($status);
		$file->setNodeId(42);
		$this->fileMapper->method('getByUuid')->with('document-uuid')->willReturn($file);
		$this->fileMapper->expects($this->never())->method('getStorageUserIdByUuid');
		$this->folderService->expects($this->never())->method('getFileByNodeId');
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not found');
		$this->getService()->getPdfByUuid('document-uuid');
	}

	public static function provideSignedStatuses(): array {
		return [[FileStatus::SIGNED->value], [FileStatus::PARTIAL_SIGNED->value]];
	}

	public function testGetFileByNodeIdReturnsTheRequestedFile(): void {
		$node = $this->createMock(File::class);
		$this->folderService->expects($this->once())->method('getFileByNodeId')->with(42)->willReturn($node);
		$this->assertSame($node, $this->getService()->getFileByNodeId(42));
	}

	public function testGetFileByNodeIdNormalizesMissingNode(): void {
		$this->folderService->method('getFileByNodeId')->with(42)->willThrowException(new NotFoundException('Missing'));
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not found');
		$this->getService()->getFileByNodeId(42);
	}

	public function testGetFileByNodeIdPreservesOtherStorageFailures(): void {
		$error = new \RuntimeException('Storage unavailable');
		$this->folderService->method('getFileByNodeId')->with(42)->willThrowException($error);
		$this->expectExceptionObject($error);
		$this->getService()->getFileByNodeId(42);
	}
}
