<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\Server;

/**
 * @group DB
 */
final class FileMapperStorageUserResolutionTest extends \OCA\Libresign\Tests\Unit\TestCase {
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
