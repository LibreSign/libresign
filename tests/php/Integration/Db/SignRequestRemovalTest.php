<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignerRemovalService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
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

	public function testFailedPersistenceRollsBackSignerRemovalAndCancellationIntent(): void {
		$signer = $this->createUnsignedSigner();
		$fileId = $signer->getFileId();
		$sibling = new SignRequest();
		$sibling->setUuid(UUIDUtil::getUUID());
		$sibling->setFileId($fileId);
		$sibling->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$sibling->setDisplayName('Unaffected signer');
		$sibling->setCreatedAt(new \DateTime('2026-01-01T00:00:00Z'));
		$this->requests->insert($sibling);

		$identity = new IdentifyMethod();
		$identity->setSignRequestId($signer->getId());
		$identity->setIdentifierKey('account');
		$identity->setIdentifierValue('synthetic-user');
		$identity->setMandatory(1);
		$this->identifications->insert($identity);

		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($identity);
		$identifications = $this->createMock(IdentifyMethodService::class);
		$identifications->method('getIdentifyMethodsFromSignRequestId')
			->with($signer->getId())->willReturn(['account' => [$method]]);
		$identifications->method('deleteBySignRequestId')
			->willThrowException(new \RuntimeException('Controlled persistence failure'));

		$deliveryService = Server::get(NotificationDeliveryService::class);
		$deliveries = Server::get(NotificationDeliveryMapper::class);
		$jobs = Server::get(IJobList::class);
		$transportCalls = 0;
		$deliveryId = null;
		$channel = new class($transportCalls) implements NotificationChannel {
			public function __construct(
				private int &$calls,
			) {
			}
			public function supports(string $channel): bool {
				return $channel === 'mail';
			}
			public function deliver(NotificationMessage $message): void {
				$this->calls++;
			}
		};
		$events = $this->createMock(IEventDispatcher::class);
		$events->method('dispatchTyped')->willReturnCallback(
			function ($event) use ($deliveryService, $deliveries, $signer, $channel, &$deliveryId): void {
				$this->assertInstanceOf(SignRequestCanceledEvent::class, $event);
				$message = new NotificationMessage(
					$event->getDeliveryEventId(),
					'sign_request_canceled',
					'mail',
					$event->getLibreSignFile(),
					$event->getSignRequest(),
					'synthetic@example.invalid',
					new MailPayload(),
				);
				$this->assertTrue($deliveryService->dispatch($message, $channel));
				$query = $this->db->getQueryBuilder();
				$query->select('id')->from('libresign_notification_delivery')
					->where($query->expr()->eq('sign_request_id', $query->createNamedParameter($signer->getId(), IQueryBuilder::PARAM_INT)))
					->andWhere($query->expr()->eq('event_type', $query->createNamedParameter('sign_request_canceled')));
				$deliveryId = (int)$query->executeQuery()->fetchOne();
				$this->assertGreaterThan(0, $deliveryId, 'Cancellation intent must be recorded before the deletion fails');
				$this->assertTrue(Server::get(IJobList::class)->has(DeliverNotification::class, ['id' => $deliveryId]));
			},
		);

		$service = new SignerRemovalService(
			$this->files,
			$this->requests,
			$identifications,
			Server::get(FileElementMapper::class),
			Server::get(SequentialSigningService::class),
			Server::get(FileStatusService::class),
			$events,
			$this->createMock(IL10N::class),
			$this->db,
			Server::get(LoggerInterface::class),
			$deliveryService,
			$deliveries,
		);

		try {
			$service->remove($fileId, $signer->getId());
			$this->fail('Persistence failure must propagate');
		} catch (\RuntimeException $e) {
			$this->assertSame('Controlled persistence failure', $e->getMessage());
		}

		$this->requests->invalidateCachedEntity($signer);
		$this->requests->invalidateCachedEntity($sibling);
		$this->assertSame($signer->getId(), $this->requests->getById($signer->getId())->getId());
		$this->assertSame($sibling->getId(), $this->requests->getById($sibling->getId())->getId());
		$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $this->requests->getById($sibling->getId())->getStatusEnum());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->files->getById($fileId)->getStatus());
		$this->assertNotNull($deliveryId);
		$this->assertFalse($jobs->has(DeliverNotification::class, ['id' => $deliveryId]));
		$query = $this->db->getQueryBuilder();
		$query->select('id')->from('libresign_notification_delivery')
			->where($query->expr()->eq('id', $query->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)));
		$this->assertFalse($query->executeQuery()->fetchOne(), 'Rolled-back cancellation outbox must not survive');
		$this->assertSame(0, $transportCalls);
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
