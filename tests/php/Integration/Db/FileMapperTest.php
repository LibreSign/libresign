<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 */
final class FileMapperTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private FileMapper $fileMapper;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
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
			$status->onQueuedJobCommitted($file);
			$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->fileMapper->getById($file->getId())->getStatus());
		} finally {
			$this->fileMapper->delete($file);
		}
	}

	/**
	 * The worker can finish before the HTTP request publishes its enqueue
	 * status. That late publication must not hide the signed result.
	 */
	public function testLateQueuePublicationCannotRevertCompletedSignature(): void {
		$file = $this->createFileEntity('late-publish-' . bin2hex(random_bytes(10)), random_int(1000000, 1999999), 'test-owner');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file = $this->fileMapper->insert($file);
		$status = Server::get(FileStatusService::class);
		$cache = Server::get(StatusCacheService::class);
		$db = Server::get(IDBConnection::class);
		try {
			$db->beginTransaction();
			$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
			$status->updateForQueuedJob($file);
			$db->commit();

			$completed = clone $file;
			$completed->setStatusEnum(FileStatus::SIGNED);
			$status->update($completed);

			// The original HTTP request now resumes after the worker finished.
			$status->onQueuedJobCommitted($file);
			$this->assertSame(FileStatus::SIGNED->value, $this->fileMapper->getById($file->getId())->getStatus());
			$this->assertNotSame(FileStatus::SIGNING_IN_PROGRESS->value, $cache->getStatus($file->getUuid()));
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
