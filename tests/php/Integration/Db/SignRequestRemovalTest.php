<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerRemovalService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\DAV\UUIDUtil;

/**
 * @group DB
 */
final class SignRequestRemovalTest extends \Test\TestCase {
	private SignRequestMapper $requests;
	private FileMapper $files;
	private IdentifyMethodMapper $identifications;
	private IDBConnection $db;

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->db->beginTransaction();
		$this->requests = Server::get(SignRequestMapper::class);
		$this->files = Server::get(FileMapper::class);
		$this->identifications = Server::get(IdentifyMethodMapper::class);
	}

	public function tearDown(): void {
		try {
			if (isset($this->db) && $this->db->inTransaction()) {
				$this->db->rollBack();
			}
		} finally {
			parent::tearDown();
		}
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

	private function createEnvelopeWithChild(bool $childSigned): array {
		$parentSigner = $this->createUnsignedSigner();
		$parentFile = $this->files->getById($parentSigner->getFileId());
		$parentFile->setNodeType('envelope');
		$this->files->update($parentFile);

		$childFile = new File();
		$childFile->setUuid(UUIDUtil::getUUID());
		$childFile->setName('envelope-child.pdf');
		$childFile->setUserId('admin');
		$childFile->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$childFile->setParentFileId($parentFile->getId());
		$childFile->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->files->insert($childFile);

		$childSigner = new SignRequest();
		$childSigner->setUuid(UUIDUtil::getUUID());
		$childSigner->setFileId($childFile->getId());
		$childSigner->setDisplayName('Envelope child signer');
		$childSigner->setStatusEnum($childSigned ? SignRequestStatus::SIGNED : SignRequestStatus::ABLE_TO_SIGN);
		if ($childSigned) {
			$childSigner->setSignedHash('preserved-child-signature');
		}
		$childSigner->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($childSigner);

		foreach ([$parentSigner, $childSigner] as $signer) {
			foreach (['account' => 'admin', 'email' => 'signer@example.test'] as $key => $value) {
				$method = new IdentifyMethod();
				$method->setSignRequestId($signer->getId());
				$method->setIdentifierKey($key);
				$method->setIdentifierValue($value);
				$method->setMandatory(1);
				$this->identifications->insert($method);
			}
		}

		return [$parentSigner, $childSigner];
	}

	public function testEnvelopeRemovalRemovesEquivalentChildSigner(): void {
		[$parentSigner, $childSigner] = $this->createEnvelopeWithChild(false);

		Server::get(SignerRemovalService::class)->remove($parentSigner->getFileId(), $parentSigner->getId(), notify: false);

		$this->assertSame([], $this->requests->getByFileId($parentSigner->getFileId()));
		$this->assertSame([], $this->requests->getByFileId($childSigner->getFileId()));
		$this->assertSame(FileStatus::DRAFT->value, $this->files->getById($parentSigner->getFileId())->getStatus());
		$this->assertSame(FileStatus::DRAFT->value, $this->files->getById($childSigner->getFileId())->getStatus());
	}

	public function testEnvelopeRemovalDoesNotRemoveAnotherAccountSharingEmail(): void {
		[$parentSigner, $childSigner] = $this->createEnvelopeWithChild(false);

		$otherSigner = new SignRequest();
		$otherSigner->setUuid(UUIDUtil::getUUID());
		$otherSigner->setFileId($childSigner->getFileId());
		$otherSigner->setDisplayName('Different account');
		$otherSigner->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$otherSigner->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($otherSigner);

		foreach (['account' => 'other-user', 'email' => 'signer@example.test'] as $key => $value) {
			$method = new IdentifyMethod();
			$method->setSignRequestId($otherSigner->getId());
			$method->setIdentifierKey($key);
			$method->setIdentifierValue($value);
			$method->setMandatory(1);
			$this->identifications->insert($method);
		}

		Server::get(SignerRemovalService::class)->remove($parentSigner->getFileId(), $parentSigner->getId(), notify: false);

		$this->assertSame([], $this->requests->getByFileId($parentSigner->getFileId()));
		$remaining = $this->requests->getByFileId($childSigner->getFileId());
		$this->assertCount(1, $remaining);
		$this->assertSame($otherSigner->getId(), $remaining[0]->getId());
	}

	public function testEnvelopeRemovalPreservesChildWithAdditionalIdentityFactor(): void {
		[$parentSigner, $childSigner] = $this->createEnvelopeWithChild(false);

		// Parent is identified by email only; the child also belongs to a
		// different account. Sharing an email is not full identity equivalence.
		$this->identifications->deleteBySignRequestId($parentSigner->getId());
		$email = new IdentifyMethod();
		$email->setSignRequestId($parentSigner->getId());
		$email->setIdentifierKey('email');
		$email->setIdentifierValue('signer@example.test');
		$email->setMandatory(1);
		$this->identifications->insert($email);

		foreach ($this->identifications->getIdentifyMethodsFromSignRequestId($childSigner->getId()) as $method) {
			if ($method->getIdentifierKey() === 'account') {
				$method->setIdentifierValue('different-account');
				$this->identifications->update($method);
			}
		}

		Server::get(SignerRemovalService::class)->remove($parentSigner->getFileId(), $parentSigner->getId(), notify: false);

		$this->assertSame([], $this->requests->getByFileId($parentSigner->getFileId()));
		$remaining = $this->requests->getByFileId($childSigner->getFileId());
		$this->assertCount(1, $remaining);
		$this->assertSame($childSigner->getId(), $remaining[0]->getId());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->files->getById($childSigner->getFileId())->getStatus());
	}

	public function testEnvelopeRemovalLeavesBothRequestsUnchangedWhenChildSigned(): void {
		[$parentSigner, $childSigner] = $this->createEnvelopeWithChild(true);

		try {
			Server::get(SignerRemovalService::class)->remove($parentSigner->getFileId(), $parentSigner->getId(), notify: false);
			$this->fail('A signed child must prevent changes to the envelope');
		} catch (LibresignException $e) {
			$this->assertSame(422, $e->getCode());
		}

		$this->assertCount(1, $this->requests->getByFileId($parentSigner->getFileId()));
		$this->assertCount(1, $this->requests->getByFileId($childSigner->getFileId()));
		$this->assertSame('preserved-child-signature', $this->requests->getById($childSigner->getId())->getSignedHash());
	}

	public function testRemovingFirstSequentialSignerReleasesNextSigner(): void {
		$first = $this->createUnsignedSigner();
		$file = $this->files->getById($first->getFileId());
		$file->setSignatureFlowEnum(SignatureFlow::ORDERED_NUMERIC);
		$this->files->update($file);
		$first->setSigningOrder(1);
		$this->requests->update($first);

		$second = new SignRequest();
		$second->setUuid(UUIDUtil::getUUID());
		$second->setFileId($first->getFileId());
		$second->setDisplayName('Next signer');
		$second->setStatusEnum(SignRequestStatus::DRAFT);
		$second->setSigningOrder(2);
		$second->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($second);

		Server::get(SignerRemovalService::class)->remove($first->getFileId(), $first->getId(), notify: false);

		$this->assertCount(1, $this->requests->getByFileId($first->getFileId()));
		$this->requests->invalidateCachedEntity($second);
		$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $this->requests->getById($second->getId())->getStatusEnum());
	}

	public function testRemovingLastUnsignedSignerUpdatesPersistedFileState(): void {
		$signer = $this->createUnsignedSigner();
		Server::get(SignerRemovalService::class)->remove($signer->getFileId(), $signer->getId(), notify: false);

		$this->assertSame([], $this->requests->getByFileId($signer->getFileId()));
		$file = $this->files->getById($signer->getFileId());
		$this->assertSame(FileStatus::DRAFT->value, $file->getStatus());
		$this->assertContains(Server::get(StatusCacheService::class)->getStatus($file->getUuid()), [false, null]);
	}

	public function testRemovalRejectsACompletedSiblingEvenWhenFileStatusIsStale(): void {
		$pending = $this->createUnsignedSigner();
		$completed = new SignRequest();
		$completed->setUuid(UUIDUtil::getUUID());
		$completed->setFileId($pending->getFileId());
		$completed->setStatusEnum(SignRequestStatus::SIGNED);
		$completed->setSignedHash('completed-sibling');
		$completed->setDisplayName('Completed signer');
		$completed->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($completed);

		try {
			Server::get(SignerRemovalService::class)->remove($pending->getFileId(), $pending->getId(), notify: false);
			$this->fail('Signer removal must reject an already completed sibling');
		} catch (LibresignException $e) {
			$this->assertSame(422, $e->getCode());
		}

		$this->assertCount(2, $this->requests->getByFileId($pending->getFileId()));
		$this->assertSame('completed-sibling', $this->requests->getById($completed->getId())->getSignedHash());
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

	public function testFailedRemovalPreservesSignersWithoutCancellationIntent(): void {
		$signer = $this->createNotifiableSigner();
		$sibling = new SignRequest();
		$sibling->setUuid(UUIDUtil::getUUID());
		$sibling->setFileId($signer->getFileId());
		$sibling->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$sibling->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($sibling);

		// Simulate a database failure after deletion. Keep notification listeners
		// and transaction processing real.
		$elements = $this->getMockBuilder(FileElementMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getByFileIdAndSignRequestId'])
			->getMock();
		$elements->method('getByFileIdAndSignRequestId')
			->willThrowException(new \RuntimeException('Database read failed'));
		$this->overwriteService(FileElementMapper::class, $elements);
		// Resolve a fresh service so this one test uses the failing persistence
		// adapter even when another test has already cached the shared service.
		$removal = \OC::$server->getAppContainerForService(SignerRemovalService::class)
			->resolve(SignerRemovalService::class);
		try {
			$this->asOwner(function () use ($signer, $removal): void {
				try {
					$removal->remove($signer->getFileId(), $signer->getId());
					$this->fail('A failed removal must not succeed');
				} catch (\RuntimeException $e) {
					$this->assertSame('Database read failed', $e->getMessage());
				}
			});
		} finally {
			$this->restoreService(FileElementMapper::class);
		}

		$this->requests->invalidateCachedEntity($signer);
		$this->requests->invalidateCachedEntity($sibling);
		$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $this->requests->getById($signer->getId())->getStatusEnum());
		$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $this->requests->getById($sibling->getId())->getStatusEnum());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->files->getById($signer->getFileId())->getStatus());
		$this->assertSame(0, $this->countCancellationIntents($signer->getId()));
	}

	public function testSuccessfulRemovalRegistersCancellationIntent(): void {
		$signer = $this->createNotifiableSigner();

		$this->asOwner(fn () => Server::get(SignerRemovalService::class)->remove($signer->getFileId(), $signer->getId()));

		$this->assertSame([], $this->requests->getByFileId($signer->getFileId()));
		$this->assertSame(1, $this->countCancellationIntents($signer->getId(), 'nextcloud'));
	}

	private function createNotifiableSigner(): SignRequest {
		$signer = $this->createUnsignedSigner();
		$method = new IdentifyMethod();
		$method->setSignRequestId($signer->getId());
		$method->setIdentifierKey('account');
		$method->setIdentifierValue('admin');
		$method->setMandatory(1);
		$this->identifications->insert($method);
		return $signer;
	}

	private function countCancellationIntents(int $signRequestId, ?string $channel = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('libresign_notification_delivery')
			->where($qb->expr()->eq('sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('event_type', $qb->createNamedParameter('sign_request_canceled')));
		if ($channel !== null) {
			$qb->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($channel)));
		}
		return (int)$qb->executeQuery()->fetchOne();
	}

	private function asOwner(callable $action): void {
		$session = Server::get(IUserSession::class);
		$previous = $session->getUser();
		$owner = Server::get(IUserManager::class)->get('admin');
		$this->assertNotNull($owner);
		$session->setUser($owner);
		try {
			$action();
		} finally {
			$session->setUser($previous);
		}
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
