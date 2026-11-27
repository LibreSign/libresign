<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationDeliveryKeyFactory;
use OCA\Libresign\Service\Notification\NotificationDeliveryRegistrar;
use OCA\Libresign\Service\Notification\NotificationEnvelopeCodec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignedCopyMail\SignedCopyMailPolicy;
use OCA\Libresign\Service\SignedCopyAttachmentProvider;
use OCA\Libresign\Service\SignedCopyMailService;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;

final class SignedCopyMailServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IUserManager&MockObject $userManager;
	private PolicyService&MockObject $policyService;
	private NotificationEnvelopeCodec $codec;
	private NotificationDeliveryRegistrar $registrar;
	/** @var array<int, list<IdentifyMethod>> */
	private array $identifyMethods = [];
	/** @var array<int, SignRequest[]> */
	private array $signRequests = [];
	/** @var array<string, NotificationDelivery> Stored deliveries by event key, like the unique index. */
	private array $deliveries = [];
	/** @var list<array{delivery: NotificationDelivery, message: string}> */
	private array $registered = [];
	/** @var list<int> */
	private array $queuedJobs = [];

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->policyService = $this->createMock(PolicyService::class);

		$this->signRequestMapper->method('getByFileId')
			->willReturnCallback(fn (int $fileId): array => $this->signRequests[$fileId] ?? []);
		$this->identifyMethodMapper->method('getIdentifyMethodsFromSignRequestId')
			->willReturnCallback(fn (int $id): array => $this->identifyMethods[$id] ?? []);

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $value): string => 'sealed:' . base64_encode($value));
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => (string)base64_decode(substr($value, 7)));
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('secret')->willReturn('test-secret');
		$deliveryMapper = $this->createMock(NotificationDeliveryMapper::class);
		$deliveryMapper->method('register')->willReturnCallback($this->registerDelivery(...));
		$this->codec = new NotificationEnvelopeCodec($crypto, $this->createMock(IManager::class));
		$this->registrar = new NotificationDeliveryRegistrar(
			$deliveryMapper,
			new NotificationDeliveryKeyFactory($config),
			$crypto,
			$this->createMock(IDBConnection::class),
		);
	}

	private function getService(): SignedCopyMailService {
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('has')
			->willReturnCallback(fn (string $job, array $argument): bool => in_array($argument['id'], $this->queuedJobs, true));
		$jobList->method('add')
			->willReturnCallback(function (string $job, array $argument): void {
				$this->assertSame(DeliverNotification::class, $job);
				$this->queuedJobs[] = $argument['id'];
			});
		return new SignedCopyMailService(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->identifyMethodMapper,
			$this->userManager,
			$this->policyService,
			$this->registrar,
			$this->codec,
			new MailChannel($this->createMock(MailService::class), $this->createMock(SignedCopyAttachmentProvider::class)),
			$jobList,
		);
	}

	public function testQueuesNothingWhenPolicyIsDisabled(): void {
		$this->mockPolicy(['enabled' => false, 'archive_addresses' => ['archive@example.com']]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'email', 'signer@example.com', true);

		$this->assertSame(0, $this->getService()->queue($file));
		$this->assertSame([], $this->registered);
	}

	public function testQueuesNothingWhenRequestIsNotSigned(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => ['archive@example.com']]);
		$file = $this->newFile(1, 'contract.pdf');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$this->assertSame(0, $this->getService()->queue($file));
		$this->assertSame([], $this->registered);
	}

	public function testQueuesOneDeliveryPerChosenSignerAndArchiveAddress(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => ['archive@example.com']]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'email', 'chosen@example.com', true, 'Chosen');
		$this->addSigner(1, 11, 'email', 'other@example.com', false);

		$this->assertSame(2, $this->getService()->queue($file));

		$this->assertSame([
			['chosen@example.com', 'Chosen', 10, [1]],
			['archive@example.com', '', null, [1]],
		], $this->registeredSummary());
		foreach ($this->registered as $entry) {
			$this->assertSame('signed_copy', $entry['delivery']->getEventType());
			$this->assertSame('mail', $entry['delivery']->getChannel());
			$this->assertSame(1, $entry['delivery']->getFileId());
		}
		$this->assertSame([1, 2], $this->queuedJobs);
	}

	public function testSecondCompletionOfTheSameRequestQueuesNothing(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => ['archive@example.com']]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'email', 'chosen@example.com', true);

		$this->assertSame(2, $this->getService()->queue($file));
		// The first mails were sent before the event fired again.
		foreach ($this->deliveries as $delivery) {
			$delivery->setState(NotificationDeliveryState::ACCEPTED->value);
		}

		$this->assertSame(0, $this->getService()->queue($file));
		$this->assertCount(2, $this->deliveries);
		$this->assertCount(2, $this->queuedJobs);
	}

	public function testRepeatedQueueDoesNotDuplicateAPendingJob(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => []]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'email', 'chosen@example.com', true);

		$this->getService()->queue($file);
		$this->assertSame(0, $this->getService()->queue($file));

		$this->assertSame([1], $this->queuedJobs);
	}

	public function testResolvesAccountSignerEmail(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => []]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'account', 'jane', true, 'Jane');
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('jane@example.com');
		$this->userManager->method('get')->with('jane')->willReturn($user);

		$this->getService()->queue($file);

		$this->assertSame([['jane@example.com', 'Jane', 10, [1]]], $this->registeredSummary());
	}

	public function testEnvelopeQueuesOneDeliveryPerRecipientWithTheirDocuments(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => ['archive@example.com']]);
		$envelope = $this->newFile(1, 'Envelope');
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$first = $this->newFile(2, 'first.pdf', 1);
		$second = $this->newFile(3, 'second.pdf', 1);
		$this->addSigner(2, 20, 'email', 'both@example.com', true, 'Both');
		$this->addSigner(3, 30, 'email', 'BOTH@example.com', true, 'Both');
		$this->addSigner(2, 21, 'email', 'first-only@example.com', true, 'First');
		$this->addSigner(3, 31, 'email', 'first-only@example.com', false, 'First');
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$first, $second]);

		$this->assertSame(3, $this->getService()->queue($envelope));

		$this->assertSame([
			['both@example.com', 'Both', 20, [2, 3]],
			['first-only@example.com', 'First', 21, [2]],
			['archive@example.com', '', null, [2, 3]],
		], $this->registeredSummary());
	}

	public function testSkipsUnsignedAndDeletedAccountSigners(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => []]);
		$file = $this->newFile(1, 'contract.pdf');
		$this->addSigner(1, 10, 'email', 'unsigned@example.com', true, signed: false);
		$this->addSigner(1, 11, 'email', 'deleted@example.com', true);
		$this->identifyMethods[11][0]->setMetadata(['deleted_account' => ['email' => 'deleted@example.com']]);

		$this->assertSame(0, $this->getService()->queue($file));
		$this->assertSame([], $this->registered);
	}

	public function testArchiveDeliveryCanBeRestoredFromTheQueue(): void {
		$this->mockPolicy(['enabled' => true, 'archive_addresses' => ['archive@example.com']]);
		$file = $this->newFile(1, 'contract.pdf');

		$this->getService()->queue($file);

		$delivery = $this->registered[0]['delivery'];
		$delivery->setMessageEnc($this->registered[0]['message']);
		$restored = $this->codec->decode($delivery);
		$this->assertSame('archive@example.com', $restored->recipient);
		$this->assertSame('signed_copy', $restored->eventType);
		$this->assertInstanceOf(MailPayload::class, $restored->payload);
		$this->assertSame([1], $restored->payload->documentIds);
	}

	/** @return list<array{string, string, ?int, list<int>}> */
	private function registeredSummary(): array {
		return array_map(function (array $entry): array {
			$entry['delivery']->setMessageEnc($entry['message']);
			$message = $this->codec->decode($entry['delivery']);
			$this->assertInstanceOf(MailPayload::class, $message->payload);
			return [
				$message->recipient,
				(string)$message->payload->displayName,
				$entry['delivery']->getSignRequestId(),
				$message->payload->documentIds,
			];
		}, $this->registered);
	}

	private function registerDelivery(
		string $eventKey,
		string $correlationId,
		string $eventType,
		string $channel,
		int $fileId,
		?int $signRequestId,
		string $ownerId,
		string $encryptedRecipient,
		?string $encryptedMessage = null,
	): NotificationDelivery {
		if (isset($this->deliveries[$eventKey])) {
			return $this->deliveries[$eventKey];
		}
		$delivery = new NotificationDelivery();
		$delivery->setId(count($this->deliveries) + 1);
		$delivery->setEventKey($eventKey);
		$delivery->setEventType($eventType);
		$delivery->setChannel($channel);
		$delivery->setFileId($fileId);
		$delivery->setSignRequestId($signRequestId);
		$delivery->setRecipientEnc($encryptedRecipient);
		$delivery->setState(NotificationDeliveryState::PENDING->value);
		$this->deliveries[$eventKey] = $delivery;
		$this->registered[] = ['delivery' => $delivery, 'message' => (string)$encryptedMessage];
		return $delivery;
	}

	private function mockPolicy(array $value): void {
		$this->policyService->method('resolveForUserId')
			->with(SignedCopyMailPolicy::KEY, 'requester')
			->willReturn((new ResolvedPolicy())->setEffectiveValue(json_encode($value)));
	}

	private function newFile(int $id, string $name, ?int $parentId = null): FileEntity {
		$file = new FileEntity();
		$file->setId($id);
		$file->setName($name);
		$file->setUuid('uuid-' . $id);
		$file->setUserId('requester');
		$file->setStatus(FileStatus::SIGNED->value);
		$file->setNodeTypeEnum(NodeType::FILE);
		if ($parentId !== null) {
			$file->setParentFileId($parentId);
		}
		return $file;
	}

	private function addSigner(int $fileId, int $signRequestId, string $method, string $value, bool $sendSignedCopy, string $displayName = '', bool $signed = true): void {
		$signRequest = new SignRequest();
		$signRequest->setId($signRequestId);
		$signRequest->setFileId($fileId);
		$signRequest->setUuid('sign-request-' . $signRequestId);
		$signRequest->setDisplayName($displayName);
		$signRequest->setParticipantRole('signer');
		if ($signed) {
			$signRequest->setSigned(new \DateTime());
		}
		if ($sendSignedCopy) {
			$signRequest->setMetadata([SignedCopyMailService::METADATA_KEY => true]);
		}
		$this->signRequests[$fileId][] = $signRequest;

		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setIdentifierKey($method);
		$identifyMethod->setIdentifierValue($value);
		$this->identifyMethods[$signRequestId] = [$identifyMethod];
	}
}
