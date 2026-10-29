<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Migration\Version19002Date20261025000000;
use OCA\Libresign\Migration\Version19004Date20261029000000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Server;

/** @group DB */
final class NotificationDeliveryMapperTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private NotificationDeliveryMapper $deliveries;
	private NotificationAttemptMapper $attempts;
	/** @var list<NotificationDelivery> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->deliveries = Server::get(NotificationDeliveryMapper::class);
		$this->attempts = Server::get(NotificationAttemptMapper::class);
	}

	public function tearDown(): void {
		foreach ($this->created as $delivery) {
			$this->deliveries->delete($delivery);
		}
		$this->created = [];
		parent::tearDown();
	}

	private function createDelivery(string $event = 'initial', ?string $snapshot = null): NotificationDelivery {
		$delivery = $this->deliveries->register(
			hash('sha256', 'synthetic-test-' . $event . '-' . bin2hex(random_bytes(12))),
			hash('sha256', 'correlation-for-' . $event),
			'sign_request',
			'mail',
			100,
			200,
			'test-owner',
			'encrypted-synthetic@example.invalid',
			$snapshot,
		);
		self::assertNotNull($delivery);
		$this->created[] = $delivery;
		return $delivery;
	}

	public function testTemporaryFailureWithoutRetryScheduleIsRejected(): void {
		$delivery = $this->createDelivery('missing-retry-time');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->expectException(\InvalidArgumentException::class);
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::TEMPORARY_FAILURE);
	}

	public function testTerminalStateCannotRetainAReplaySchedule(): void {
		$delivery = $this->createDelivery('terminal-with-retry-time');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->expectException(\InvalidArgumentException::class);
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::UNKNOWN, new \DateTime('+2 minutes', new \DateTimeZone('UTC')));
	}

	public function testDueQueriesUseStablePaginationAndExcludeFutureRetries(): void {
		for ($index = 0; $index < 55; $index++) {
			$this->createDelivery('pagination-' . $index);
		}
		$first = $this->deliveries->findDue(10, 0);
		$this->assertTrue($this->deliveries->claim($first[0]->getId()));
		$second = $this->deliveries->findDue(10, $first[9]->getId());
		self::assertCount(10, $first);
		self::assertCount(10, $second);
		self::assertSame([], array_intersect(
			array_map(static fn (NotificationDelivery $row): int => $row->getId(), $first),
			array_map(static fn (NotificationDelivery $row): int => $row->getId(), $second),
		));
		self::assertCount(1, $this->deliveries->findPending(1));
	}

	public function testSafeRetryIsScheduledAndCannotBeClaimedBeforeDueTime(): void {
		$delivery = $this->createDelivery('safe-retry', 'encrypted-handoff-test-data');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$future = new \DateTime('+1 hour', new \DateTimeZone('UTC'));
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::TEMPORARY_FAILURE, $future);
		$this->assertFalse($this->deliveries->claim($delivery->getId()));
		$this->assertSame(NotificationDeliveryState::TEMPORARY_FAILURE->value, $this->deliveries->findById($delivery->getId())->getState());
		$this->assertNotNull($this->deliveries->findById($delivery->getId())->getNextAttemptAt());
		$this->assertSame('encrypted-handoff-test-data', $this->deliveries->findById($delivery->getId())->getMessageEnc());
		$this->assertFalse(in_array($delivery->getId(), array_map(static fn (NotificationDelivery $row): int => $row->getId(), $this->deliveries->findDue(500)), true));
	}

	public function testDueRetryHasOneAtomicWinnerAndPreservesEventIdentity(): void {
		$delivery = $this->createDelivery('due-retry', 'encrypted-handoff-test-data');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::TEMPORARY_FAILURE, new \DateTime('-1 minute', new \DateTimeZone('UTC')));
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->assertFalse($this->deliveries->claim($delivery->getId()));
		$this->assertSame(2, $this->deliveries->findById($delivery->getId())->getAttemptCount());
		$this->assertSame($delivery->getEventKey(), $this->deliveries->findById($delivery->getId())->getEventKey());
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::ACCEPTED);
		$this->assertNull($this->deliveries->findById($delivery->getId())->getMessageEnc());
		$this->assertFalse($this->deliveries->claim($delivery->getId()));
	}

	public function testCancelAlsoInvalidatesScheduledInvitation(): void {
		$delivery = $this->createDelivery('cancel-scheduled', 'encrypted-handoff-test-data');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::TEMPORARY_FAILURE, new \DateTime('-1 minute', new \DateTimeZone('UTC')));
		$this->assertSame(1, $this->deliveries->cancelPendingInvitations(200));
		$this->assertSame(NotificationDeliveryState::CANCELED->value, $this->deliveries->findById($delivery->getId())->getState());
		$this->assertNull($this->deliveries->findById($delivery->getId())->getMessageEnc());
		$this->assertFalse($this->deliveries->claim($delivery->getId()));
	}

	public function testUniqueEventIsInsertedOnlyOnce(): void {
		$key = hash('sha256', 'synthetic-test-' . bin2hex(random_bytes(16)));
		$delivery = $this->deliveries->register($key, hash('sha256', $key), 'sign_request', 'mail', 101, 201, 'test-owner', 'ciphertext');
		self::assertNotNull($delivery);
		$this->created[] = $delivery;
		$reused = $this->deliveries->register($key, hash('sha256', $key), 'sign_request', 'mail', 101, 201, 'test-owner', 'ciphertext-other');
		self::assertSame($delivery->getId(), $reused->getId());
		// Only PENDING may be claimed, and only once even with two dispatchers.
		self::assertTrue($this->deliveries->claim($reused->getId()));
		self::assertFalse($this->deliveries->claim($delivery->getId()));
		self::assertSame($delivery->getId(), $this->deliveries->findByEventKey($key)->getId());
	}

	public function testDuplicateWithinDomainTransactionDoesNotAbortFurtherWrites(): void {
		$db = Server::get(\OCP\IDBConnection::class);
		$key = hash('sha256', 'transactional-duplicate-' . bin2hex(random_bytes(16)));
		$keyTwo = hash('sha256', 'transactional-control-' . bin2hex(random_bytes(16)));
		$db->beginTransaction();
		try {
			$first = $this->deliveries->register($key, hash('sha256', $key), 'sign_request', 'mail', 101, 201, 'owner', 'encrypted');
			$same = $this->deliveries->register($key, hash('sha256', $key), 'sign_request', 'mail', 101, 201, 'owner', 'different');
			$this->assertSame($first->getId(), $same->getId());
			$control = $this->deliveries->register($keyTwo, hash('sha256', $keyTwo), 'sign_request', 'mail', 102, 202, 'owner', 'encrypted');
			$this->assertSame($control->getId(), $this->deliveries->findByEventKey($keyTwo)->getId());
			$db->commit();
			$this->created[] = $first;
			$this->created[] = $control;
		} finally {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
		}
	}

	public function testCancellationInvalidatesOnlyPendingInvitations(): void {
		$pending = $this->createDelivery('pending-to-cancel');
		$accepted = $this->createDelivery('already-claimed');
		$this->deliveries->claim($accepted->getId());
		$this->assertSame(1, $this->deliveries->cancelPendingInvitations(200));
		$this->assertSame(NotificationDeliveryState::CANCELED->value, $this->deliveries->findById($pending->getId())->getState());
		$this->assertNull($this->deliveries->findById($pending->getId())->getMessageEnc());
		$this->assertSame(NotificationDeliveryState::SENDING->value, $this->deliveries->findById($accepted->getId())->getState());
		$this->assertFalse($this->deliveries->claim($pending->getId()));
	}

	public function testCancellationRollsBackWithDomainTransaction(): void {
		$delivery = $this->createDelivery('cancel-rollback');
		$db = Server::get(\OCP\IDBConnection::class);
		$db->beginTransaction();
		try {
			$this->assertSame(1, $this->deliveries->cancelPendingInvitations(200));
			$this->assertSame(NotificationDeliveryState::CANCELED->value, $this->deliveries->findById($delivery->getId())->getState());
		} finally {
			$db->rollBack();
		}
		$this->assertSame(NotificationDeliveryState::PENDING->value, $this->deliveries->findById($delivery->getId())->getState());
	}

	public function testSameSourceEventHasIndependentChannelDeliveries(): void {
		$key = hash('sha256', 'channel-' . bin2hex(random_bytes(16)));
		$email = $this->deliveries->register($key, hash('sha256', $key), 'sign_request', 'mail', 101, 201, 'owner', 'encrypted');
		$this->created[] = $email;
		$otherKey = hash('sha256', $key . ':gateway:sms');
		$gateway = $this->deliveries->register($otherKey, hash('sha256', $key), 'sign_request', 'gateway:sms', 101, 201, 'owner', 'encrypted');
		$this->created[] = $gateway;
		$this->assertSame('mail', $email->getChannel());
		$this->assertSame('gateway:sms', $gateway->getChannel());
		$this->assertCount(2, $this->deliveries->findForEvent(hash('sha256', $key)));
		$this->assertTrue($this->deliveries->claim($email->getId()));
		$this->assertTrue($this->deliveries->claim($gateway->getId()));
		$this->assertFalse($this->deliveries->claim($email->getId()));
	}

	public function testClaimIsAtomicAndAttemptRetainsItsResult(): void {
		$delivery = $this->createDelivery();
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->assertFalse($this->deliveries->claim($delivery->getId()));
		$attempt = $this->attempts->begin($delivery->getId());
		$this->attempts->finish($attempt->getId(), NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown');
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::UNKNOWN);
		$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $this->deliveries->findByEventKey($delivery->getEventKey())->getState());
		$persisted = $this->attempts->findForDelivery($delivery->getId());
		$this->assertCount(1, $persisted);
		$this->assertSame('smtp_outcome_unknown', $persisted[0]->getReasonCode());
		$this->assertNotNull($persisted[0]->getFinishedAt());
	}

	public function testCannotFinishUnclaimedDelivery(): void {
		$delivery = $this->createDelivery();
		$this->expectException(\LogicException::class);
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::ACCEPTED);
	}

	public function testInterruptedSmtpRemainsUnknownAndNeverBecomesPending(): void {
		$delivery = $this->createDelivery('interrupt');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->attempts->begin($delivery->getId());
		$delivery->setState(NotificationDeliveryState::SENDING->value);
		$delivery->setUpdatedAt(new \DateTime('-3 days'));
		$this->deliveries->update($delivery);

		$cutoff = new \DateTime('-2 days');
		$stale = $this->deliveries->findStaleSending($cutoff);
		$this->assertContains($delivery->getId(), array_map(static fn (NotificationDelivery $item): int => $item->getId(), $stale));
		$this->assertTrue($this->deliveries->markStaleUnknown($delivery->getId(), $cutoff));
		$this->assertSame(1, $this->attempts->finishInterrupted($delivery->getId()));
		$this->assertFalse($this->deliveries->markStaleUnknown($delivery->getId(), $cutoff));
		$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $this->deliveries->findByEventKey($delivery->getEventKey())->getState());
		// A delayed worker must not overwrite the reconciled unknown outcome.
		try {
			$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::ACCEPTED);
			$this->fail('Stale worker must not complete after reconciliation');
		} catch (\LogicException) {
			$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $this->deliveries->findById($delivery->getId())->getState());
		}
		$this->assertSame('submission_interrupted', $this->attempts->findForDelivery($delivery->getId())[0]->getReasonCode());
	}

	public function testRetentionDoesNotDeletePendingEvents(): void {
		$delivery = $this->createDelivery('pending-retention');
		$delivery->setUpdatedAt(new \DateTime('-2 years'));
		$this->deliveries->update($delivery);

		$this->assertSame(0, $this->deliveries->redactOlderThan(new \DateTime('-365 days')));
		$this->assertSame(NotificationDeliveryState::PENDING->value, $this->deliveries->findByEventKey($delivery->getEventKey())->getState());
	}

	public function testRetentionRedactsRecipientButKeepsEventTombstone(): void {
		$delivery = $this->createDelivery('retention');
		$this->assertTrue($this->deliveries->claim($delivery->getId()));
		$this->deliveries->finish($delivery->getId(), NotificationDeliveryState::ACCEPTED);
		$delivery->setState(NotificationDeliveryState::ACCEPTED->value);
		$delivery->setUpdatedAt(new \DateTime('-2 years'));
		$this->deliveries->update($delivery);
		$this->assertSame(1, $this->deliveries->redactOlderThan(new \DateTime('-365 days')));
		$persisted = $this->deliveries->findByEventKey($delivery->getEventKey());
		$this->assertSame('', $persisted->getRecipientEnc());
		$this->assertSame('', $persisted->getOwnerId());
		$this->assertSame(NotificationDeliveryState::ACCEPTED->value, $persisted->getState());
		$this->assertFalse($this->deliveries->claim($persisted->getId()));
	}

	public function testAttemptCounterMigrationIsIdempotent(): void {
		$table = $this->createMock(ITable::class);
		$table->expects($this->exactly(2))->method('hasColumn')->with('attempt_count')
			->willReturnOnConsecutiveCalls(false, true);
		$table->expects($this->once())->method('addColumn')
			->with('attempt_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_notification_delivery')->willReturn(true);
		$schema->method('getTable')->with('libresign_notification_delivery')->willReturn($table);
		$migration = new Version19004Date20261029000000();
		$schemaClosure = static fn () => $schema;
		$output = $this->createMock(IOutput::class);
		$this->assertSame($schema, $migration->changeSchema($output, $schemaClosure, []));
		$this->assertNull($migration->changeSchema($output, $schemaClosure, []));
	}

	public function testSchemaMigrationIsIdempotentAfterInstall(): void {
		// The table creation was verified using real inserts above. A second
		// migration pass should not request any schema modifications.
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnMap([
			['libresign_notification_delivery', true],
			['libresign_notification_attempt', true],
		]);
		$schema->expects($this->never())->method('createTable');
		$migration = new Version19002Date20261025000000();
		$this->assertNull($migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn () => $schema,
			[],
		));

	}
}
