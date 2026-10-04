<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Listener;

use DateTimeInterface;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Listener\BeforeNodeDeletedListener;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Storage\IStorage;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 */
final class BeforeNodeDeletedListenerTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private const SIGNED_NODE_ID = 808080802;

	private IDBConnection $db;
	private ?int $fileId = null;

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
	}

	public function tearDown(): void {
		if ($this->fileId !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_file')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($this->fileId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		parent::tearDown();
	}

	public function testDeletingTheSignedFileReturnsTheRequestToDraftWithItsPolicySnapshotFrozen(): void {
		$file = new File();
		$file->setNodeId(808080801);
		$file->setSignedNodeId(self::SIGNED_NODE_ID);
		$file->setUserId('owner-user');
		$file->setUuid('c3333333-3333-4333-8333-333333333333');
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file->setName('signed-contract');
		$file->setStatus(FileStatus::SIGNED->value);
		$file->setMetadata(['extension' => 'pdf']);
		$this->fileId = Server::get(FileMapper::class)->insert($file)->getId();

		Server::get(BeforeNodeDeletedListener::class)->handle(
			new CacheEntryRemovedEvent($this->createMock(IStorage::class), 'signed-contract.pdf', self::SIGNED_NODE_ID, 1),
		);

		$qb = $this->db->getQueryBuilder();
		$row = $qb->select('status', 'signed_node_id', 'metadata')
			->from('libresign_file')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($this->fileId, IQueryBuilder::PARAM_INT)))
			->executeQuery()
			->fetch();

		$this->assertSame(FileStatus::DRAFT->value, (int)$row['status']);
		$this->assertNull($row['signed_node_id']);
		$metadata = json_decode((string)$row['metadata'], true);
		$this->assertSame('pdf', $metadata['extension']);
		$this->assertIsString($metadata['policy_snapshot_frozen_at'] ?? null);
		$this->assertNotFalse(\DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $metadata['policy_snapshot_frozen_at']));
	}
}
