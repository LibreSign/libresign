<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerRemovalService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\DAV\UUIDUtil;

/**
 * @group DB
 */
final class SignRequestRemovalTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private SignRequestMapper $requests;
	private FileMapper $files;
	private IDBConnection $db;

	public function setUp(): void {
		parent::setUp();
		$this->requests = Server::get(SignRequestMapper::class);
		$this->files = Server::get(FileMapper::class);
		$this->db = Server::get(IDBConnection::class);
	}

	private function createUnsignedSigner(): SignRequest {
		$file = new File();
		$file->setUuid(UUIDUtil::getUUID());
		$file->setName('signer-removal.pdf');
		$file->setUserId('admin');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->files->insert($file);

		$signer = new SignRequest();
		$signer->setUuid(UUIDUtil::getUUID());
		$signer->setFileId($file->getId());
		$signer->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$signer->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$signer->setDisplayName('Pending signer');
		$this->requests->insert($signer);
		return $signer;
	}

	public function testRemovingLastUnsignedSignerUpdatesPersistedFileState(): void {
		$signer = $this->createUnsignedSigner();
		Server::get(SignerRemovalService::class)->remove($signer->getFileId(), $signer->getId(), notify: false);

		$this->assertSame([], $this->requests->getByFileId($signer->getFileId()));
		$file = $this->files->getById($signer->getFileId());
		$this->assertSame(FileStatus::DRAFT->value, $file->getStatus());
		$this->assertSame(FileStatus::DRAFT->value, Server::get(StatusCacheService::class)->getStatus($file->getUuid()));
	}

	public function testRemovalCannotDiscardPersistedSigningEvidence(): void {
		$signer = $this->createUnsignedSigner();
		$signer->setStatusEnum(SignRequestStatus::SIGNED);
		$signer->setSignedHash('completed-hash');
		$this->requests->update($signer);

		try {
			Server::get(SignerRemovalService::class)->remove($signer->getFileId(), $signer->getId(), notify: false);
			$this->fail('Completed signer must not be removable');
		} catch (LibresignException $e) {
			$this->assertSame(422, $e->getCode());
		}

		$this->requests->invalidateCachedEntity($signer);
		$this->assertSame('completed-hash', $this->requests->getById($signer->getId())->getSignedHash());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->files->getById($signer->getFileId())->getStatus());
	}

	public function testUnsignedSignerCanBeDeletedAndIsNoLongerReturned(): void {
		$signer = $this->createUnsignedSigner();
		$this->assertTrue($this->requests->deleteIfUnsigned($signer));

		$this->expectException(DoesNotExistException::class);
		$this->requests->getById($signer->getId());
	}

	#[DataProvider('completedEvidence')]
	public function testCompletedSigningEvidenceIsNotDeleted(string $column, mixed $value): void {
		$signer = $this->createUnsignedSigner();
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set($column, $qb->createNamedParameter($value))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($signer->getId(), IQueryBuilder::PARAM_INT)))
			->executeStatement();

		$this->assertFalse($this->requests->deleteIfUnsigned($signer));
		$this->requests->invalidateCachedEntity($signer);
		$reloaded = $this->requests->getById($signer->getId());
		$this->assertSame($signer->getId(), $reloaded->getId());
	}

	public static function completedEvidence(): array {
		return [
			'signed status' => ['status', SignRequestStatus::SIGNED->value],
			'signed hash' => ['signed_hash', 'signed-hash'],
			'signed timestamp' => ['signed', '2026-01-01 00:00:00'],
		];
	}
}
