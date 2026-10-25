<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileStatusService;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 */
final class FileMapperTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private FileMapper $fileMapper;
	private IdDocsMapper $idDocsMapper;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
		$this->idDocsMapper = Server::get(IdDocsMapper::class);
	}

	public function testGetStorageUserIdByUuidReturnsFileOwnerWhenNoIdDocsExists(): void {
		$file = $this->createFileEntity(
			uuid: 'a1111111-1111-4111-8111-111111111111',
			nodeId: 10101,
			userId: 'owner-user'
		);

		$inserted = $this->fileMapper->insert($file);

		$storageUserId = $this->fileMapper->getStorageUserIdByUuid($inserted->getUuid());
		$this->assertSame('owner-user', $storageUserId);
	}

	public function testGetStorageUserIdByUuidPrefersFileOwnerWhenIdDocsExists(): void {
		$file = $this->createFileEntity(
			uuid: 'b2222222-2222-4222-8222-222222222222',
			nodeId: 20202,
			userId: 'owner-user'
		);

		$inserted = $this->fileMapper->insert($file);
		$this->idDocsMapper->save(
			$inserted->getId(),
			null,
			'external-signer',
			'proofOfIdentity'
		);

		$storageUserId = $this->fileMapper->getStorageUserIdByUuid($inserted->getUuid());
		$this->assertSame('owner-user', $storageUserId);
	}

	public function testRecentSigningStatusDoesNotBecomeStaleBecauseFileIsOld(): void {
		$file = $this->createFileEntity(
			uuid: 'synthetic-' . bin2hex(random_bytes(12)),
			nodeId: random_int(200000, 999999),
			userId: 'test-owner',
		);
		$file->setCreatedAt(new \DateTime('-30 days'));
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$file->setMetadata(['status_changed_at' => (new \DateTime('-1 minute'))->format(\DateTime::ATOM)]);
		$inserted = $this->fileMapper->insert($file);
		try {
			$ids = array_map(static fn (File $item): int => $item->getId(),
				$this->fileMapper->findStaleSigningInProgress(new \DateTime('-10 minutes')));
			$this->assertNotContains($inserted->getId(), $ids);
		} finally {
			$this->fileMapper->delete($inserted);
		}
	}

	public function testInvalidStatusTimestampIsNotReplacedByOldCreationDate(): void {
		$file = $this->createFileEntity(
			uuid: 'synthetic-' . bin2hex(random_bytes(12)),
			nodeId: random_int(200000, 999999),
			userId: 'test-owner',
		);
		$file->setCreatedAt(new \DateTime('-30 days'));
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$file->setMetadata(['status_changed_at' => 'not-a-date']);
		$inserted = $this->fileMapper->insert($file);
		try {
			$ids = array_map(static fn (File $item): int => $item->getId(),
				$this->fileMapper->findStaleSigningInProgress(new \DateTime('-10 minutes')));
			$this->assertNotContains($inserted->getId(), $ids);
		} finally {
			$this->fileMapper->delete($inserted);
		}
	}

	public function testRolledBackQueuedStatusIsNotVisibleInTheDistributedCache(): void {
		$file = $this->createFileEntity('rollback-' . bin2hex(random_bytes(10)), random_int(1000000, 1999999), 'test-owner');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file = $this->fileMapper->insert($file);
		$status = Server::get(FileStatusService::class);
		$db = Server::get(IDBConnection::class);
		try {
			$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->fileMapper->getById($file->getId())->getStatus());
			$db->beginTransaction();
			try {
				$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
				$status->updateForQueuedJob($file);
			} finally {
				$db->rollBack();
				$status->discardQueuedJobStatus($file, FileStatus::ABLE_TO_SIGN->value, []);
			}
			$query = $db->getQueryBuilder();
			$persistedStatus = $query->select('status')->from('libresign_file')
				->where($query->expr()->eq('id', $query->createNamedParameter($file->getId())))
				->executeQuery()->fetchOne();
			$this->assertSame(FileStatus::ABLE_TO_SIGN->value, (int)$persistedStatus);
			$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->fileMapper->getById($file->getId())->getStatus());
		} finally {
			$this->fileMapper->delete($file);
		}
	}

	public function testCommittedQueuedStatusBecomesVisibleToReaders(): void {
		$file = $this->createFileEntity('commit-' . bin2hex(random_bytes(10)), random_int(1000000, 1999999), 'test-owner');
		$file = $this->fileMapper->insert($file);
		$status = Server::get(FileStatusService::class);
		$db = Server::get(IDBConnection::class);
		try {
			$db->beginTransaction();
			$committed = false;
			try {
				$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
				$status->updateForQueuedJob($file);
				$db->commit();
				$committed = true;
			} finally {
				if (!$committed) {
					$db->rollBack();
				}
			}
			$status->publishQueuedJobStatus($file);
			$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->fileMapper->getById($file->getId())->getStatus());
		} finally {
			$this->fileMapper->delete($file);
		}
	}

	private function createFileEntity(string $uuid, int $nodeId, ?string $userId): File {
		$file = new File();
		$file->setNodeId($nodeId);
		$file->setUserId($userId);
		$file->setUuid($uuid);
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file->setName('storage-user-resolution.pdf');
		$file->setStatus(FileStatus::DRAFT->value);
		return $file;
	}
}
